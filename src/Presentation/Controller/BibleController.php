<?php
// src/Presentation/Controller/BibleController.php

namespace App\Presentation\Controller;

use App\Domain\Repository\BibleRepositoryInterface;
use App\Infrastructure\Persistence\SqliteNoteRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BibleController
{
    public function __construct(
        private BibleRepositoryInterface $bibleRepository,
        private SqliteNoteRepository $noteRepository
    ) {}

    #[Route('/bible/{version}/{bookCode}/{chapter}', name: 'bible_read', methods: ['GET'])]
    public function read(string $version, string $bookCode, int $chapter, Request $request): Response
    {
        $session = $request->getSession();
        $userId = $session->get('user_id');

        $books = $this->bibleRepository->getBooks($version);
        $verses = $this->bibleRepository->getChapterVerses($version, $bookCode, $chapter);
        $hasPrev = $chapter > 1 && $this->bibleRepository->hasChapter($version, $bookCode, $chapter - 1);
        $hasNext = $this->bibleRepository->hasChapter($version, $bookCode, $chapter + 1);

        // Сбор существующих заметок
        $userNotesByVerse = [];
        $chapterNotesHtml = '';
        if ($userId) {
            $allNotes = $this->noteRepository->findAllForChapterView($userId, $bookCode, $chapter);
            foreach ($allNotes as $note) {
                if ($note->getTarget()->getVerse() !== null) {
                    $userNotesByVerse[$note->getTarget()->getVerse()][] = $note;
                } else {
                    $chapterNotesHtml .= $this->renderSingleNoteHtml($note);
                }
            }
        }

        $currentBookName = $bookCode;
        foreach ($books as $b) {
            if (strcasecmp($b['code'], $bookCode) === 0) { $currentBookName = $b['name']; break; }
        }

        // Сетка книг (Alpine.js)
        $booksGridHtml = "
        <div x-data='{ open: false, search: \"\" }' class='mb-6'>
            <button @click='open = !open' class='w-full bg-gray-100 dark:bg-slate-900 hover:bg-gray-200 dark:hover:bg-slate-800 text-gray-700 dark:text-slate-300 text-sm font-medium py-2 px-4 rounded-xl border border-transparent dark:border-slate-800 transition flex justify-between items-center cursor-pointer'>
                <span>📚 Библиотека книг (Нажмите для выбора)</span>
                <span x-text='open ? \"▲\" : \"▼\"'>▼</span>
            </button>
            <div x-show='open' x-collapse class='bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-xl p-4 mt-2 shadow-sm' style='display: none;'>
                <input x-model='search' type='text' placeholder='Быстрый поиск книги...' class='w-full p-2 mb-3 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 text-slate-900 dark:text-slate-100'>
                <div class='grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 gap-2 text-xs'>
                ";
        foreach ($books as $b) {
            $isActive = (strcasecmp($b['code'], $bookCode) === 0) ? 'bg-blue-600 text-white font-semibold shadow-sm' : 'bg-gray-50 dark:bg-slate-800/40 hover:bg-gray-100 dark:hover:bg-slate-800 text-gray-800 dark:text-slate-300';
            $booksGridHtml .= "<a x-show='\"" . mb_strtolower($b['name']) . "\".includes(search.toLowerCase())' href='/bible/{$version}/{$b['code']}/1' class='p-2 rounded-lg text-center truncate transition {$isActive}'>{$b['name']}</a>";
        }
        $booksGridHtml .= "
                </div>
            </div>
        </div>";

        // Рендеринг стихов (Добавили id='verse-text-{$vNum}')
        $versesHtml = '';
        foreach ($verses as $v) {
            $vNum = $v['verse'];
            $versesHtml .= "
            <div class='verse-row group py-2 px-3 rounded-lg hover:bg-blue-50/40 dark:hover:bg-slate-800/30 transition relative mb-1'>
                <p class='text-gray-900 dark:text-slate-200 leading-relaxed m-0'>
                    <span @click='openModal($vNum)' class='verse-badge text-gray-400 dark:text-slate-500 font-bold text-xs mr-2 cursor-pointer select-none bg-gray-100 dark:bg-slate-800 px-1.5 py-0.5 rounded group-hover:bg-blue-600 group-hover:text-white dark:group-hover:bg-blue-500 transition'>
                        {$vNum}
                    </span>
                    <span id='verse-text-{$vNum}'>" . htmlspecialchars($v['text']) . "</span>
                </p>
                <div id='notes-container-{$vNum}' class='notes-list mt-1 space-y-1 pl-6'>";
            if (isset($userNotesByVerse[$vNum])) {
                foreach ($userNotesByVerse[$vNum] as $note) {
                    $versesHtml .= $this->renderSingleNoteHtml($note);
                }
            }
            $versesHtml .= "</div>
            </div>";
        }

        $navHtml = "
        <div class='flex justify-between items-center mt-8 pt-4 border-t border-gray-100 dark:border-slate-800 text-sm font-medium text-blue-600 dark:text-blue-400'>
            " . ($hasPrev ? "<a href='/bible/{$version}/{$bookCode}/" . ($chapter - 1) . "' class='hover:underline'>&larr; Назад</a>" : "<span></span>") . "
            <span class='text-gray-500 dark:text-slate-400 font-normal'>Глава {$chapter}</span>
            " . ($hasNext ? "<a href='/bible/{$version}/{$bookCode}/" . ($chapter + 1) . "' class='hover:underline'>Вперед &rarr;</a>" : "<span></span>") . "
        </div>";

        return $this->renderLayout("
            <div x-data='{ 
                modalOpen: false, 
                isAuthenticated: " . ($userId ? 'true' : 'false') . ",
                isEditMode: false,
                noteId: \"\",
                currentVerse: null,
                currentVerseLabel: \"Вся глава\",
                currentVerseText: \"\",
                noteContent: \"\",
                openModal(verse) {
                    if(!this.isAuthenticated) return alert(\"Авторизуйтесь для добавления заметок.\");
                    this.isEditMode = false;
                    this.noteId = \"\";
                    this.currentVerse = verse;
                    this.currentVerseLabel = verse ? \"Стих \" + verse : \"Вся глава\";
                    this.currentVerseText = verse ? document.getElementById(\"verse-text-\" + verse)?.innerText : \"\";
                    this.noteContent = \"\";
                    this.modalOpen = true;
                },
                openEditModal(id) {
                    if(!this.isAuthenticated) return;
                    const el = document.getElementById(\"note-item-\" + id);
                    if(!el) return;
                    this.isEditMode = true;
                    this.noteId = id;
                    const rawVerse = el.getAttribute(\"data-verse\");
                    this.currentVerse = rawVerse !== \"\" ? parseInt(rawVerse) : null;
                    this.currentVerseLabel = this.currentVerse ? \"Редактирование (Стих \" + this.currentVerse + \")\" : \"Редактирование главы\";
                    this.currentVerseText = this.currentVerse ? document.getElementById(\"verse-text-\" + this.currentVerse)?.innerText : \"\";
                    this.noteContent = el.getAttribute(\"data-content\");
                    this.modalOpen = true;
                }
            }'>
                <div class='flex justify-between items-center mb-6'>
                    <a href='/' class='text-sm text-blue-600 dark:text-blue-400 hover:underline'>&larr; На главную</a>
                    
                    <div class='flex items-center gap-3 text-xs text-gray-500 dark:text-slate-400'>
                        <button @click='toggleDark()' class='p-1.5 px-3 rounded-lg bg-gray-100 dark:bg-slate-900 hover:bg-gray-200 dark:hover:bg-slate-800 text-gray-700 dark:text-slate-300 transition flex items-center gap-1 cursor-pointer font-medium border border-transparent dark:border-slate-800'>
                            <span x-show='!darkMode'>🌙 Ночной скин</span>
                            <span x-show='darkMode'>☀️ Дневной скин</span>
                        </button>
                        
                        " . ($userId
                ? "<span class='bg-green-100 dark:bg-green-950/30 text-green-800 dark:text-green-400 px-2 py-1 rounded-full font-medium'>Режим экзегета</span>
                               <button @click='openModal(null)' class='bg-blue-500 hover:bg-blue-600 dark:bg-blue-600 dark:hover:bg-blue-700 text-white px-2.5 py-1 rounded-lg shadow-sm font-medium transition cursor-pointer'>+ Заметка к главе</button>"
                : "<span class='bg-gray-100 dark:bg-slate-900 text-gray-600 dark:text-slate-400 px-2 py-1 rounded-full'>Режим чтения (гость)</span>") . "
                    </div>
                </div>

                $booksGridHtml

                <div class='bg-white dark:bg-slate-900 border border-gray-100 dark:border-slate-800 rounded-xl p-6 shadow-sm shadow-gray-100/50'>
                    <h2 class='text-2xl font-bold text-gray-900 dark:text-slate-100 mb-6 flex items-center gap-2'>
                        <span>{$currentBookName}</span>
                        <span class='text-gray-400 dark:text-slate-600 font-light'>|</span>
                        <span class='text-blue-600 dark:text-blue-400 font-medium'>Глава {$chapter}</span>
                    </h2>

                    <div id='notes-container-blank' class='mb-4 space-y-2'>
                        $chapterNotesHtml
                    </div>

                    <div class='divide-y divide-gray-50 dark:divide-slate-800/50'>
                        $versesHtml
                    </div>

                    $navHtml
                </div>

                <!-- УНИФИЦИРОВАННОЕ МОДАЛЬНОЕ ОКНО С ЦИТИРОВАНИЕМ СТИХА -->
                <div x-show='modalOpen' 
                     class='fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/40 dark:bg-black/60 backdrop-blur-sm' 
                     style='display: none;'
                     @keydown.escape.window='modalOpen = false'>
                    
                    <div @click.away='modalOpen = false' class='bg-white dark:bg-slate-900 rounded-xl shadow-xl border border-gray-100 dark:border-slate-800 w-full max-w-md overflow-hidden transform transition-all'>
                        <div class='bg-gray-50 dark:bg-slate-800/40 px-6 py-4 border-b dark:border-slate-800 flex justify-between items-center'>
                            <h3 class='text-base font-semibold text-gray-800 dark:text-slate-200' x-text='currentVerseLabel'></h3>
                            <button @click='modalOpen = false' class='text-gray-400 hover:text-gray-600 dark:hover:text-slate-300 text-xl font-light cursor-pointer'>&times;</button>
                        </div>
                        
                        <form hx-post='/htmx/notes/save'
                              hx-rows='3'
                              :hx-target='currentVerse ? \"#notes-container-\" + currentVerse : \"#notes-container-blank\"'
                              hx-swap='innerHTML'
                              @htmx:after-request='if(event.detail.successful) { modalOpen = false; noteContent = \"\"; }'
                              class='p-6 space-y-4'>
                            
                            <input type='hidden' name='book_code' value='{$bookCode}'>
                            <input type='hidden' name='chapter' value='{$chapter}'>
                            <input type='hidden' :value='currentVerse' name='verse'>
                            <input type='hidden' :value='noteId' name='note_id'>

                            <!-- Интерактивный блок цитирования священного текста -->
                            <div x-show='currentVerseText' 
                                 class='p-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800/80 rounded-lg text-xs italic text-slate-600 dark:text-slate-400 max-h-24 overflow-y-auto leading-relaxed border-l-4 border-l-blue-500/50 dark:border-l-blue-500'>
                                <span x-text='currentVerseText'></span>
                            </div>

                            <div>
                                <label class='block text-xs font-medium text-gray-500 dark:text-slate-400 mb-1.5'>Ваш разбор, размышление или параллель:</label>
                                <textarea x-model='noteContent' name='content' required 
                                          placeholder='Впишите глубокий смысл текста...'
                                          class='w-full p-3 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-slate-900 dark:text-slate-100 rounded-lg text-sm font-sans focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition h-32 resize-none'></textarea>
                            </div>

                            <div class='flex justify-end gap-2 text-sm font-medium pt-2'>
                                <button type='button' @click='modalOpen = false' class='px-4 py-2 border border-gray-200 dark:border-slate-700 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-800 text-gray-600 dark:text-slate-300 transition cursor-pointer'>Отмена</button>
                                <button type='submit' class='px-4 py-2 bg-blue-600 hover:bg-blue-700 dark:bg-blue-600 dark:hover:bg-blue-700 text-white rounded-lg shadow-sm transition cursor-pointer'>Сохранить</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        ");
    }

    private function renderSingleNoteHtml(\App\Domain\Model\Note $note): string
    {
        $id = $note->getId();
        $content = nl2br(htmlspecialchars($note->getContent()));
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
                    class='hidden group-hover/note:inline-block text-[11px] text-blue-600 dark:text-blue-400 hover:underline ml-2 cursor-pointer font-semibold border-none bg-none p-0 select-none'>
                ред.
            </button>
        </div>";
    }

    private function renderLayout(string $content): Response
    {
        $html = "
        <!DOCTYPE html>
        <html lang='ru'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Экзегетический Дневник</title>
            <script src='https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4'></script>
            <script src='https://unpkg.com/htmx.org@1.9.10'></script>
            <script defer src='https://unpkg.com/@alpinejs/collapse@3.x.x/dist/collapse.min.js'></script>
            <script defer src='https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js'></script>
            <style>
                .note-item { font-size: 13px; border-radius: 6px; padding: 6px 12px; margin-top: 4px; border-left-width: 4px; }
                .verse-note { background-color: #f0fdf4; border-left-color: #22c55e; color: #166534; }
                .chapter-note { background-color: #fef9c3; border-left-color: #eab308; color: #713f12; font-size: 14px; padding: 10px; border-radius: 8px; }
                
                .dark .verse-note { background-color: rgba(34, 197, 94, 0.15) !important; border-left-color: #4ade80 !important; color: #86efac !important; }
                .dark .chapter-note { background-color: rgba(234, 179, 8, 0.15) !important; border-left-color: #fde047 !important; color: #fef08a !important; }
            </style>
        </head>
        <body x-data=\"{ 
                darkMode: localStorage.getItem('darkMode') === 'true', 
                toggleDark() { this.darkMode = !this.darkMode; localStorage.setItem('darkMode', this.darkMode); } 
              }\"
              :class=\"darkMode ? 'dark bg-slate-950 text-slate-100' : 'bg-slate-50 text-slate-800'\"
              class='font-sans antialiased min-h-screen py-10 px-4 transition-colors duration-200'>
            <div class='max-w-4xl mx-auto'>
                $content
            </div>
        </body>
        </html>";
        return new Response($html);
    }
}

