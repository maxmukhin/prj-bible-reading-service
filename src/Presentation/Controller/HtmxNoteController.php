<?php
// src/Presentation/Controller/HtmxNoteController.php

namespace App\Presentation\Controller;

use App\Application\UseCase\SaveNoteUseCase;
use App\Domain\Repository\FriendRequestRepositoryInterface;
use App\Domain\Repository\NoteRepositoryInterface;
use App\Presentation\View\BibleHtmlRenderer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HtmxNoteController
{
    public function __construct(
        private SaveNoteUseCase $saveNoteUseCase,
        private NoteRepositoryInterface $noteRepository,
        private FriendRequestRepositoryInterface $friendshipRepository,
        private BibleHtmlRenderer $renderer
    ) {}

    #[Route('/htmx/notes/save', name: 'htmx_note_save', methods: ['POST'])]
    public function save(Request $request): Response
    {
        $userId = $request->getSession()->get('user_id');
        if (!$userId) {
            return new Response("Unauthorized", 403);
        }

        try {
            // 1. Извлекаем, типизируем и валидируем входные данные
            $input = $this->parseAndValidateInput($request);

            // 2. Выполняем сценарий сохранения в Домене
            $this->saveNoteUseCase->execute(
                $input['note_id'],
                $userId,
                $input['book_code'],
                $input['chapter'],
                $input['verse'],
                $input['content']
            );

            // Получаем друзей
            $friendIds = $this->friendshipRepository->getFriendIds($userId);

            // 3. Получаем свежую Read-модель (включая заметки друзей) для этой главы
            $allNotes = $this->noteRepository->findAllForChapterView($userId, $input['book_code'], $input['chapter'], $friendIds);

            // 4. Делегируем рендеринг списка презентеру, передавая контекст пользователя
            $html = $this->renderer->renderNotesListForTarget($allNotes, $input['verse'], $userId);

            return new Response($html);

        } catch (\InvalidArgumentException $e) {
            return new Response($e->getMessage(), 400);
        }
    }

    /**
     * Инкапсулирует грязную работу с массивом $_POST
     * @throws \InvalidArgumentException
     */
    private function parseAndValidateInput(Request $request): array
    {
        $content = trim((string)$request->request->get('content'));
        if (empty($content)) {
            throw new \InvalidArgumentException("Контент не может быть пустым");
        }

        $rawVerse = $request->request->get('verse');

        return [
            'note_id'   => $request->request->get('note_id'),
            'book_code' => (string)$request->request->get('book_code'),
            'chapter'   => (int)$request->request->get('chapter'),
            'verse'     => ($rawVerse !== '' && $rawVerse !== null) ? (int)$rawVerse : null,
            'content'   => $content,
        ];
    }
}
