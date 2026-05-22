<?php
// src/Presentation/ApiController/ApiNoteController.php

namespace App\Presentation\ApiController;

use App\Application\UseCase\CreateNoteUseCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class ApiNoteController
{
    public function __construct(
        private CreateNoteUseCase $createNoteUseCase
    ) {}

    #[Route('/api/notes', name: 'api_note_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $userId = $request->getSession()->get('user_id');
        if (!$userId) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        // Принимаем как обычный POST, так и JSON-body
        $data = json_decode($request->getContent(), true) ?? $request->request->all();

        $bookCode = (string)($data['bookCode'] ?? '');
        $chapter = (int)($data['chapter'] ?? 0);
        $verse = isset($data['verse']) && $data['verse'] !== '' ? (int)$data['verse'] : null;
        $content = (string)($data['content'] ?? '');

        try {
            $this->createNoteUseCase->execute($userId, $bookCode, $chapter, $verse, $content);

            return new JsonResponse([
                'success' => true,
                'message' => 'Заметка успешно сохранена'
            ], 201);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
}

