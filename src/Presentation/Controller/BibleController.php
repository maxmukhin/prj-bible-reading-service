<?php
namespace App\Presentation\Controller;

use App\Domain\Repository\BibleRepositoryInterface;
use App\Domain\Repository\FriendRequestRepositoryInterface;
use App\Domain\Repository\NoteRepositoryInterface;
use App\Presentation\View\BibleHtmlRenderer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BibleController
{
    public function __construct(
        private BibleRepositoryInterface $bibleRepository,
        private NoteRepositoryInterface $noteRepository,
        private FriendRequestRepositoryInterface $friendshipRepository,
        private BibleHtmlRenderer $renderer
    ) {}

    #[Route('/bible/{version}/{bookCode}/{chapter}', name: 'bible_read', methods: ['GET'])]
    public function read(string $version, string $bookCode, int $chapter, Request $request): Response
    {
        $userId = $request->getSession()->get('user_id');

        // Получаем друзей
        $friendIds = $userId ? $this->friendshipRepository->getFriendIds($userId) : [];

        // Вытаскиваем библейский текст и совмещенный массив заметок (свои + друзей)
        $books = $this->bibleRepository->getBooks($version);
        $verses = $this->bibleRepository->getChapterVerses($version, $bookCode, $chapter);
        $allNotes = $userId ? $this->noteRepository->findAllForChapterView($userId, $bookCode, $chapter, $friendIds) : [];

        $hasPrev = $chapter > 1 && $this->bibleRepository->hasChapter($version, $bookCode, $chapter - 1);
        $hasNext = $this->bibleRepository->hasChapter($version, $bookCode, $chapter + 1);

        $currentBookName = $bookCode;
        foreach ($books as $b) {
            if (strcasecmp($b['code'], $bookCode) === 0) { $currentBookName = $b['name']; break; }
        }

        // Делегируем сборку HTML обособленному презентеру
        $html = $this->renderer->render(
            $version, $bookCode, $chapter, $currentBookName,
            $books, $verses, $allNotes, $userId, $hasPrev, $hasNext
        );

        return new Response($html);
    }
}
