<?php
// src/Presentation/Controller/HtmxNoteController.php

namespace App\Presentation\Controller;

use App\Application\UseCase\CreateNoteUseCase;
use App\Application\UseCase\UpdateNoteUseCase;
use App\Infrastructure\Persistence\SqliteNoteRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HtmxNoteController
{
    public function __construct(
        private CreateNoteUseCase $createNoteUseCase,
        private UpdateNoteUseCase $updateNoteUseCase,
        private SqliteNoteRepository $noteRepository
    ) {}

    #[Route('/htmx/notes/save', name: 'htmx_note_save', methods: ['POST'])]
    public function save(Request $request): Response
    {
        $session = $request->getSession();
        $userId = $session->get('user_id');

        if (!$userId) {
            return new Response("<span class='text-red-500'>Ошибка: Доступ запрещен</span>", 403);
        }

        $noteId = $request->request->get('note_id') ?: null;
        $bookCode = strtoupper((string)$request->request->get('book_code'));
        $chapter = (int)$request->request->get('chapter');
        $verse = $request->request->get('verse') !== '' ? (int)$request->request->get('verse') : null;
        $content = trim((string)$request->request->get('content'));

        if (empty($content)) {
            return new Response("<span class='text-red-500'>Текст заметки пуст</span>", 400);
        }

        try {
            if ($noteId) {
                $this->updateNoteUseCase->execute($noteId, $userId, $content);
            } else {
                $this->createNoteUseCase->execute($userId, $bookCode, $chapter, $verse, $content);
            }

            // Запрашиваем актуальный срез данных для перерисовки
            $allNotes = $this->noteRepository->findAllForChapterView($userId, $bookCode, $chapter);

            return $this->renderNotesSnippet($allNotes, $verse);

        } catch (\Throwable $e) {
            return new Response("<span class='text-red-500'>Ошибка: {$e->getMessage()}</span>", 400);
        }
    }

    private function renderNotesSnippet(array $allNotes, ?int $targetVerse): Response
    {
        $html = '';
        foreach ($allNotes as $note) {
            $currentVerse = $note->getTarget()->getVerse();
            if ($targetVerse !== null && $currentVerse !== $targetVerse) {
                continue;
            }
            if ($targetVerse === null && $currentVerse !== null) {
                continue;
            }

            $html .= $this->renderSingleNoteHtml($note);
        }
        return new Response($html);
    }

    public function renderSingleNoteHtml(\App\Domain\Model\Note $note): string
    {
        $id = $note->getId();
        $content = htmlspecialchars($note->getContent());
        // Экранируем контент для безопасного хранения внутри HTML-атрибута data-content
        $escapedContent = htmlspecialchars($note->getContent(), ENT_QUOTES, 'UTF-8');
        $verse = $note->getTarget()->getVerse();
        $class = $verse !== null ? 'verse-note' : 'chapter-note';

        return "
        <div id='note-item-{$id}' 
             data-content='{$escapedContent}' 
             data-verse='{$verse}'
             class='note-item {$class} flex justify-between items-start group/note'>
            <span class='flex-1 break-words text-slate-800 dark:text-slate-200'>{$content}</span>
            <button @click.stop='openEditModal(\"{$id}\")' 
                    class='hidden group-hover/note:inline-block text-[11px] text-blue-600 dark:text-blue-400 hover:underline ml-2 cursor-pointer font-semibold border-none bg-none p-0 select-none transition'>
                ред.
            </button>
        </div>";
    }
}

