<?php
// src/Presentation/Controller/BibleController.php

namespace App\Presentation\Controller;

use App\Domain\Repository\BibleRepositoryInterface;
use App\Infrastructure\Persistence\SqliteNoteRepository;
use App\Application\UseCase\CreateNoteUseCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

class BibleController
{
    public function __construct(
        private BibleRepositoryInterface $bibleRepository,
        private SqliteNoteRepository $noteRepository, // Используем напрямую расширенный метод инфраструктуры
        private CreateNoteUseCase $createNoteUseCase
    ) {}

    #[Route('/bible/{version}/{bookCode}/{chapter}', name: 'bible_read', methods: ['GET', 'POST'])]
    public function read(string $version, string $bookCode, int $chapter, Request $request): Response
    {
        $session = $request->getSession();
        $userId = $session->get('user_id');

        // ОБРАБОТКА POST: Добавление заметки
        if ($request->isMethod('POST')) {
            if (!$userId) {
                return new Response("Аутентификация обязательна для создания заметок.", 403);
            }

            $content = (string)$request->request->get('content');
            $verse = $request->request->get('verse') ? (int)$request->request->get('verse') : null;

            try {
                $this->createNoteUseCase->execute($userId, $bookCode, $chapter, $verse, $content);
                // Делаем Redirect, чтобы избежать повторной отправки формы при обновлении страницы F5
                return new RedirectResponse($request->getUri());
            } catch (\Throwable $e) {
                return new Response("Ошибка бизнес-логики: " . $e->getMessage(), 400);
            }
        }

        // ВЫБОРКА ТЕКСТА И НАВИГАЦИИ
        $books = $this->bibleRepository->getBooks($version);
        $verses = $this->bibleRepository->getChapterVerses($version, $bookCode, $chapter);
        $hasPrev = $chapter > 1 && $this->bibleRepository->hasChapter($version, $bookCode, $chapter - 1);
        $hasNext = $this->bibleRepository->hasChapter($version, $bookCode, $chapter + 1);

        // ВЫБОРКА ЗАМЕТОК (только если юзер залогинен)
        $userNotesByVerse = [];
        $chapterNotesHtml = '';

        if ($userId) {
            $allNotes = $this->noteRepository->findAllForChapterView($userId, $bookCode, $chapter);
            foreach ($allNotes as $note) {
                if ($note->getTarget()->getVerse() !== null) {
                    $userNotesByVerse[$note->getTarget()->getVerse()][] = $note;
                } else {
                    // Заметка на уровень всей главы
                    $chapterNotesHtml .= "<div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 10px; margin-bottom: 10px; font-size: 14px; border-radius: 4px;'>
                        <strong>Моя заметка к главе:</strong> " . htmlspecialchars($note->getContent()) . "
                    </div>";
                }
            }
        }

        // ПОИСК НАЗВАНИЯ КНИГИ
        $currentBookName = $bookCode;
        foreach ($books as $b) {
            if (strcasecmp($b['code'], $bookCode) === 0) { $currentBookName = $b['name']; break; }
        }

        // РЕНДЕРИНГ СТИХОВ С ИНЛАЙН-ЗАМЕТКАМИ
        $versesHtml = '';
        if (empty($verses)) {
            $versesHtml = '<p style="color: #666; font-style: italic;">Текст этой главы отсутствует.</p>';
        } else {
            foreach ($verses as $v) {
                $versesHtml .= "<div style='margin-bottom: 16px;'>
                    <p style='line-height: 1.6; margin: 0;'>
                        <strong style='color: #888; font-size: 0.85em; margin-right: 5px;'>{$v['verse']}</strong>{$v['text']}
                    </p>";

                // Выводим заметки к текущему стиху, если они есть
                if (isset($userNotesByVerse[$v['verse']])) {
                    foreach ($userNotesByVerse[$v['verse']] as $note) {
                        $versesHtml .= "<div style='background: #e2f0d9; border-left: 4px solid #70ad47; padding: 6px 12px; margin: 5px 0 5px 20px; font-size: 13px; border-radius: 4px; color: #385723;'>
                            " . htmlspecialchars($note->getContent()) . "
                        </div>";
                    }
                }
                $versesHtml .= "</div>";
            }
        }

        // МЕНЮ КНИГ И НАВИГАЦИЯ
        $booksMenuHtml = '<div style="margin-bottom: 20px; font-size: 14px; background: #eee; padding: 10px; border-radius: 4px;"><strong>Книги:</strong> ';
        foreach ($books as $b) {
            $weight = (strcasecmp($b['code'], $bookCode) === 0) ? 'bold' : 'normal';
            $booksMenuHtml .= "<a href='/bible/{$version}/{$b['code']}/1' style='margin-right: 15px; font-weight: {$weight};'>{$b['name']}</a>";
        }
        $booksMenuHtml .= '</div>';

        $navHtml = '<div style="margin-top: 30px; display: flex; justify-content: space-between; border-top: 1px solid #eee; padding-top: 20px;">';
        $navHtml .= $hasPrev ? "<a href='/bible/{$version}/{$bookCode}/" . ($chapter - 1) . "'>&larr; Предыдущая глава</a>" : "<span></span>";
        $navHtml .= $hasNext ? "<a href='/bible/{$version}/{$bookCode}/" . ($chapter + 1) . "'>Следующая глава &rarr;</a>" : "<span></span>";
        $navHtml .= '</div>';

        // ФОРМА ДОБАВЛЕНИЯ ЗАМЕТКИ (доступна только авторизованным)
        $formHtml = '';
        if ($userId) {
            $formHtml = "
            <div style='margin-top: 40px; background: #f8f9fa; padding: 20px; border-radius: 6px; border: 1px solid #e9ecef;'>
                <h3 style='margin-top:0; font-size: 16px;'>Добавить новую заметку</h3>
                <form method='POST' style='display: flex; flex-direction: column; gap: 10px;'>
                    <label style='font-size: 13px; color: #666;'>
                        Привязать к стиху: 
                        <select name='verse' style='padding: 4px;'>
                            <option value=''>Вся глава</option>";
            foreach ($verses as $v) {
                $formHtml .= "<option value='{$v['verse']}'>Стих {$v['verse']}</option>";
            }
            $formHtml .= "
                        </select>
                    </label>
                    <textarea name='content' placeholder='Ваши мысли, философский разбор или параллели...' required style='padding: 10px; height: 80px; font-family: inherit; border-radius: 4px; border: 1px solid #ced4da;'></textarea>
                    <button type='submit' style='padding: 8px 15px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; align-self: flex-start;'>Сохранить заметку</button>
                </form>
            </div>";
        } else {
            $formHtml = "<p style='margin-top: 40px; font-style: italic; color: #666; text-align: center;'>Хотите оставлять заметки к стихам? <a href='/login'>Войдите в аккаунт</a>.</p>";
        }

        return $this->renderLayout("
            <div style='display: flex; justify-content: space-between; align-items: center;'>
                <p><a href='/'>&larr; На главную</a></p>
                <p style='font-size: 12px; color: #666;'>" . ($userId ? "Режим редактирования заметок активен" : "Режим чтения (гость)") . "</p>
            </div>
            $booksMenuHtml
            <h2>{$currentBookName}, Глава {$chapter}</h2>
            $chapterNotesHtml
            <div style='background: #fff; padding: 20px; border-radius: 6px; box-shadow: inset 0 1px 3px rgba(0,0,0,0.02);'>
                $versesHtml
            </div>
            $navHtml
            $formHtml
        ");
    }

    private function renderLayout(string $content): Response
    {
        $html = "
        <!DOCTYPE html>
        <html lang='ru'>
        <head>
            <meta charset='UTF-8'>
            <title>Чтение Библии и Заметки</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #f4f6f9; color: #333; margin: 0; padding: 40px; }
                .container { max-width: 750px; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin: 0 auto; }
                h2 { color: #111; margin-top: 0; }
                a { text-decoration: none; color: #007bff; }
                a:hover { text-decoration: underline; }
            </style>
        </head>
        <body>
            <div class='container'>
                $content
            </div>
        </body>
        </html>";
        return new Response($html);
    }
}

