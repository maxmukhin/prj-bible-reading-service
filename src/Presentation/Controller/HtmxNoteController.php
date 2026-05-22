<?php
// src/Presentation/Controller/HtmxNoteController.php

namespace App\Presentation\Controller;

use App\Application\UseCase\SaveNoteUseCase;
use App\Infrastructure\Persistence\SqliteNoteRepository;
use App\Presentation\View\BibleHtmlRenderer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HtmxNoteController
{
    public function __construct(
        private SaveNoteUseCase $saveNoteUseCase,
        private SqliteNoteRepository $noteRepository,
        private BibleHtmlRenderer $renderer
    ) {}

    #[Route('/htmx/notes/save', name: 'htmx_note_save', methods: ['POST'])]
    public function save(Request $request): Response
    {
        $userId = $request->getSession()->get('user_id');
        if (!$userId) {
            return new Response("Unauthorized", 403);
        }

        // Парсим входные данные
        $noteId   = $request->request->get('note_id');
        $bookCode = (string)$request->request->get('book_code');
        $chapter  = (int)$request->request->get('chapter');
        $rawVerse = $request->request->get('verse');
        $verse    = ($rawVerse !== '' && $rawVerse !== null) ? (int)$rawVerse : null;
        $content  = trim((string)$request->request->get('content'));

        if (empty($content)) {
            return new Response("Контент не может быть пустым", 400);
        }

        // Выполняем UseCase — внутри него соберется консистентный NoteTarget
        $this->saveNoteUseCase->execute($noteId, $userId, $bookCode, $chapter, $verse, $content);

        // Возвращаем обновленный список заметок для конкретной точки (стиха или главы)
        // Чтобы HTMX бесшовно сделал OOB или innerHTML своп
        $allNotes = $this->noteRepository->findAllForChapterView($userId, $bookCode, $chapter);

        $html = '';
        foreach ($allNotes as $note) {
            // Фильтруем только те заметки, которые относятся к текущему контексту (этому же стиху или главе)
            if ($note->getTarget()->getVerse() === $verse) {
                $html .= $this->renderer->renderSingleNoteHtml($note, true);
            }
        }

        return new Response($html);
    }
}
