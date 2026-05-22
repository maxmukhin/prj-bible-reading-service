<?php
namespace App\Presentation\Controller;

use App\Domain\Repository\FriendRequestRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HtmxFriendController
{
    public function __construct(private FriendRequestRepositoryInterface $friendshipRepository) {}

    #[Route('/htmx/friends/add', name: 'htmx_friend_add', methods: ['POST'])]
    public function add(Request $request): Response
    {
        $userId = $request->getSession()->get('user_id');
        $friendId = trim((string)$request->request->get('friend_id'));

        if (!$userId) {
            return new Response("Unauthorized", 403);
        }

        if (!empty($friendId) && $userId !== $friendId) {
            $this->friendshipRepository->addFriendRequest($userId, $friendId);
            return new Response("", 200); // Возвращаем пустой ответ, Alpine покажет алерт
        }

        return new Response("Invalid ID", 400);
    }
}

