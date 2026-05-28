<?php
namespace App\Presentation\View;

use App\Domain\Model\Note;

class BibleHtmlRenderer
{
    /**
     * Точка входа для HTMX-контроллера
     */
    public function renderNotesListForTarget(array $notes, ?int $verse, ?string $userId): string
    {
        return $this->renderNotesWidget($notes, $verse, $userId);
    }

    /**
     * ИЗОЛИРОВАННЫЙ ВИДЖЕТ ЗАМЕТОК (Единый источник правды для полного рендера и HTMX)
     * @param Note[] $allNotes
     */
    public function renderNotesWidget(array $allNotes, ?int $verse, ?string $userId): string
    {
        $myNotes = [];
        $friendsNotes = [];

        foreach ($allNotes as $note) {
            // Оставляем только те заметки, которые принадлежат целевому контексту (стиху или главе)
            if ($note->getTarget()->getVerse() !== $verse) {
                continue;
            }

            // Проверяем авторство
            $isMyNote = ($userId && $note->getUserId() === $userId);

            if ($isMyNote) {
                $myNotes[] = $note;
            } else {
                $friendsNotes[] = $note;
            }
        }

        $html = '';

        // Сначала всегда выводим СВОИ заметки
        foreach ($myNotes as $note) {
            $html .= $this->renderSingleNoteHtml($note, true);
        }

        // Затем выводим ЗАМЕТКИ ДРУЗЕЙ
        foreach ($friendsNotes as $note) {
            $html .= $this->renderSingleNoteHtml($note, false);
        }

        return $html;
    }

    public function render(
        string $version, string $bookCode, int $chapter, string $currentBookName,
        array $books, array $verses, array $allNotes, ?string $userId, bool $hasPrev, bool $hasNext
    ): string {

        // Рендерим заметки к главе (передаем null вместо номера стиха)
        $chapterNotesHtml = $this->renderNotesWidget($allNotes, null, $userId);

        // 1. Рендеринг сетки книг
        $booksGridHtml = $this->renderBooksGrid($books, $bookCode, $version);

        // 2. Рендеринг стихов
        $versesHtml = '';
        foreach ($verses as $v) {
            $vNum = $v['verse'];

            // Вызываем наш виджет прямо внутри цикла генерации стихов
            $notesWidgetHtml = $this->renderNotesWidget($allNotes, $vNum, $userId);

            $versesHtml .= "
            <div class='verse-row group py-2 px-3 rounded-lg hover:bg-blue-50/40 dark:hover:bg-slate-800/30 transition relative mb-1'>
                <p class='text-gray-900 dark:text-slate-200 leading-relaxed m-0'>
                    <span @click='openModal($vNum)' class='verse-badge text-gray-400 dark:text-slate-500 font-bold text-xs mr-2 cursor-pointer select-none bg-gray-100 dark:bg-slate-800 px-1.5 py-0.5 rounded group-hover:bg-blue-600 group-hover:text-white dark:group-hover:bg-blue-500 transition'>
                        {$vNum}
                    </span>
                    <span id='verse-text-{$vNum}'>" . htmlspecialchars($v['text']) . "</span>
                </p>
                <div id='notes-container-{$vNum}' class='notes-list mt-1 space-y-1 pl-6'>
                    {$notesWidgetHtml}
                </div>
            </div>";
        }

        // 3. Навигация
        $navHtml = $this->renderNavigation($version, $bookCode, $chapter, $hasPrev, $hasNext);

        // Возвращаем финальную сборку (остается без изменений)
        return $this->renderLayout("
            <div x-data='{ 
                modalOpen: false, isAuthenticated: " . ($userId ? 'true' : 'false') . ", isEditMode: false,
                noteId: \"\", currentVerse: null, currentVerseLabel: \"Вся глава\", currentVerseText: \"\", noteContent: \"\",
                openModal(verse) {
                    if(!this.isAuthenticated) return alert(\"Авторизуйтесь для добавления заметок.\");
                    this.isEditMode = false; this.noteId = \"\"; this.currentVerse = verse;
                    this.currentVerseLabel = verse ? \"Стих \" + verse : \"Вся глава\";
                    this.currentVerseText = verse ? document.getElementById(\"verse-text-\" + verse)?.innerText : \"\";
                    this.noteContent = \"\"; this.modalOpen = true;
                },
                openEditModal(id) {
                    if(!this.isAuthenticated) return;
                    const el = document.getElementById(\"note-item-\" + id); if(!el) return;
                    this.isEditMode = true; this.noteId = id;
                    const rawVerse = el.getAttribute(\"data-verse\");
                    this.currentVerse = rawVerse !== \"\" ? parseInt(rawVerse) : null;
                    this.currentVerseLabel = this.currentVerse ? \"Редактирование (Стих \" + this.currentVerse + \")\" : \"Редактирование главы\";
                    this.currentVerseText = this.currentVerse ? document.getElementById(\"verse-text-\" + this.currentVerse)?.innerText : \"\";
                    this.noteContent = el.getAttribute(\"data-content\"); this.modalOpen = true;
                }
            }'>
                <div class='flex justify-between items-center mb-6'>
                    <a href='/' class='text-sm text-blue-600 dark:text-blue-400 hover:underline'>&larr; На главную</a>
                    
                    <div class='flex items-center gap-3 text-xs text-gray-500 dark:text-slate-400'>
                        " . ($userId ? "
                        <form hx-post='/htmx/friends/add' hx-swap='none' @htmx:after-request='if(event.detail.successful) alert(\"Друг успешно добавлен!\")' class='flex items-center gap-1 bg-white dark:bg-slate-900 border dark:border-slate-800 rounded-lg p-1 shadow-sm'>
                            <input type='text' name='friend_id' placeholder='ID друга' required class='px-2 py-0.5 bg-transparent border-none text-xs focus:outline-none w-24 text-slate-800 dark:text-slate-200'>
                            <button type='submit' class='bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-medium px-2 py-0.5 rounded cursor-pointer transition'>+ Друг</button>
                        </form>
                        " : "") . "

                        <button @click='toggleDark()' class='p-1.5 px-3 rounded-lg bg-gray-100 dark:bg-slate-900 hover:bg-gray-200 dark:hover:bg-slate-800 text-gray-700 dark:text-slate-300 transition flex items-center gap-1 cursor-pointer font-medium border border-transparent dark:border-slate-800'>
                            <span x-show='!darkMode'>🌙 Ночной скин</span>
                            <span x-show='darkMode'>☀️ Дневной скин</span>
                        </button>
                        
                        " . ($userId
                ? "<span class='bg-green-100 dark:bg-green-950/30 text-green-800 dark:text-green-400 px-2 py-1 rounded-full font-medium'>Режим экзегета (Ваш ID: " . htmlspecialchars($userId) . ")</span>
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

                <div x-show='modalOpen' class='fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/40 dark:bg-black/60 backdrop-blur-sm' style='display: none;' @keydown.escape.window='modalOpen = false'>
                    <div @click.away='modalOpen = false' class='bg-white dark:bg-slate-900 rounded-xl shadow-xl border border-gray-100 dark:border-slate-800 w-full max-w-md overflow-hidden transform transition-all'>
                        <div class='bg-gray-50 dark:bg-slate-800/40 px-6 py-4 border-b dark:border-slate-800 flex justify-between items-center'>
                            <h3 class='text-base font-semibold text-gray-800 dark:text-slate-200' x-text='currentVerseLabel'></h3>
                            <button @click='modalOpen = false' class='text-gray-400 hover:text-gray-600 dark:hover:text-slate-300 text-xl font-light cursor-pointer'>&times;</button>
                        </div>
                        <form hx-post='/htmx/notes/save' :hx-target='currentVerse ? \"#notes-container-\" + currentVerse : \"#notes-container-blank\"' hx-swap='innerHTML' @htmx:after-request='if(event.detail.successful) { modalOpen = false; noteContent = \"\"; }' class='p-6 space-y-4'>
                            <input type='hidden' name='book_code' value='{$bookCode}'>
                            <input type='hidden' name='chapter' value='{$chapter}'>
                            <input type='hidden' :value='currentVerse' name='verse'>
                            <input type='hidden' :value='noteId' name='note_id'>
                            <div x-show='currentVerseText' class='p-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800/80 rounded-lg text-xs italic text-slate-600 dark:text-slate-400 max-h-24 overflow-y-auto leading-relaxed border-l-4 border-l-blue-500/50 dark:border-l-blue-500'><span x-text='currentVerseText'></span></div>
                            <div>
                                <label class='block text-xs font-medium text-gray-500 dark:text-slate-400 mb-1.5'>Ваш разбор, размышление или параллель:</label>
                                <textarea x-model='noteContent' name='content' required placeholder='Впишите глубокий смысл текста...' class='w-full p-3 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-slate-900 dark:text-slate-100 rounded-lg text-sm font-sans focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition h-32 resize-none'></textarea>
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

    public function renderSingleNoteHtml(Note $note, bool $isMyNote): string
    {
        $id = $note->getId();
        $content = htmlspecialchars($note->getContent());
        $escapedContent = htmlspecialchars($note->getContent(), ENT_QUOTES, 'UTF-8');
        $verse = $note->getTarget()->getVerse();

        if ($isMyNote) {
            $class = $verse !== null ? 'verse-note' : 'chapter-note';
            $authorBadge = "";
            $editButton = "<button @click.stop='openEditModal(\"{$id}\")' class='hidden group-hover/note:inline-block text-[11px] text-blue-600 dark:text-blue-400 hover:underline ml-2 cursor-pointer font-semibold'>ред.</button>";
        } else {
            $class = 'friend-note';
            $shortId = substr($note->getUserId(), 0, 6);
            $authorBadge = "<span class='text-[10px] bg-purple-200 dark:bg-purple-900 text-purple-800 dark:text-purple-300 font-bold px-1 py-0.2 rounded mr-1.5 uppercase tracking-wider'>Экзегет #{$shortId}</span>";
            $editButton = "";
        }

        return "
        <div id='note-item-{$id}' data-content='{$escapedContent}' data-verse='{$verse}' class='note-item {$class} flex justify-between items-start group/note'>
            <span class='flex-1 break-words whitespace-pre-wrap text-slate-800 dark:text-slate-200'>{$authorBadge}{$content}</span>
            {$editButton}
        </div>";
    }

    private function renderBooksGrid(array $books, string $bookCode, string $version): string
    {
        $html = "
        <div x-data='{ open: false, search: \"\" }' class='mb-6'>
            <button @click='open = !open' class='w-full bg-gray-100 dark:bg-slate-900 hover:bg-gray-200 dark:hover:bg-slate-800 text-gray-700 dark:text-slate-300 text-sm font-medium py-2 px-4 rounded-xl border border-transparent dark:border-slate-800 transition flex justify-between items-center cursor-pointer'>
                <span>📚 Библиотека книг (Нажмите для выбора)</span><span x-text='open ? \"▲\" : \"▼\"'>▼</span>
            </button>
            <div x-show='open' x-collapse class='bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-xl p-4 mt-2 shadow-sm' style='display: none;'>
                <input x-model='search' type='text' placeholder='Быстрый поиск...' class='w-full p-2 mb-3 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm text-slate-900 dark:text-slate-100'>
                <div class='grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 gap-2 text-xs'>";
        foreach ($books as $b) {
            $isActive = (strcasecmp($b['code'], $bookCode) === 0) ? 'bg-blue-600 text-white font-semibold' : 'bg-gray-50 dark:bg-slate-800/40 hover:bg-gray-100 dark:hover:bg-slate-800 text-gray-800 dark:text-slate-300';
            $html .= "<a x-show='\"" . mb_strtolower($b['name']) . "\".includes(search.toLowerCase())' href='/bible/{$version}/{$b['code']}/1' class='p-2 rounded-lg text-center truncate transition {$isActive}'>{$b['name']}</a>";
        }
        $html .= "</div></div></div>";
        return $html;
    }

    private function renderNavigation(string $version, string $bookCode, int $chapter, bool $hasPrev, bool $hasNext): string
    {
        return "
        <div class='flex justify-between items-center mt-8 pt-4 border-t border-gray-100 dark:border-slate-800 text-sm font-medium text-blue-600 dark:text-blue-400'>
            " . ($hasPrev ? "<a href='/bible/{$version}/{$bookCode}/" . ($chapter - 1) . "' class='hover:underline'>&larr; Назад</a>" : "<span></span>") . "
            <span class='text-gray-500 dark:text-slate-400 font-normal'>Глава {$chapter}</span>
            " . ($hasNext ? "<a href='/bible/{$version}/{$bookCode}/" . ($chapter + 1) . "' class='hover:underline'>Вперед &rarr;</a>" : "<span></span>") . "
        </div>";
    }

    private function renderLayout(string $content): string
    {
        return "
    <!DOCTYPE html>
    <html lang='ru'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'><title>Экзегетический Дневник</title>
        <script src='https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4'></script>
        <script src='https://unpkg.com/htmx.org@1.9.10'></script>
        <script defer src='https://unpkg.com/@alpinejs/collapse@3.x.x/dist/cdn.min.js'></script>
        <script defer src='https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js'></script>
        <style>
            .note-item { font-size: 13px; border-radius: 6px; padding: 6px 12px; margin-top: 4px; border-left-width: 4px; }
            .verse-note { background-color: #f0fdf4; border-left-color: #22c55e; color: #166534; }
            .chapter-note { background-color: #fef9c3; border-left-color: #eab308; color: #713f12; font-size: 14px; padding: 10px; border-radius: 8px; }
            .friend-note { background-color: #f3e8ff; border-left-color: #a855f7; color: #581c87; }
            .dark .verse-note { background-color: rgba(34, 197, 94, 0.15) !important; border-left-color: #4ade80 !important; color: #86efac !important; }
            .dark .chapter-note { background-color: rgba(234, 179, 8, 0.15) !important; border-left-color: #fde047 !important; color: #fef08a !important; }
            .dark .friend-note { background-color: rgba(168, 85, 247, 0.15) !important; border-left-color: #c084fc !important; color: #e9d5ff !important; }
            
            /* МАГИЧЕСКИЙ КАСКАД: Если у body есть класс hide-friends, скрываем заметки друзей */
            .hide-friends .friend-note { display: none !important; }
        </style>
    </head>
    <body x-data=\"{ 
        darkMode: localStorage.getItem('darkMode') === 'true', 
        showFriends: localStorage.getItem('showFriends') !== 'false',
        toggleDark() { this.darkMode = !this.darkMode; localStorage.setItem('darkMode', this.darkMode); },
        toggleFriends() { this.showFriends = !this.showFriends; localStorage.setItem('showFriends', this.showFriends); }
    }\" :class=\"(darkMode ? 'dark bg-slate-950 text-slate-100' : 'bg-slate-50 text-slate-800') + (showFriends ? '' : ' hide-friends')\" class='font-sans antialiased min-h-screen py-10 px-4 transition-colors duration-200'>
        <div class='max-w-4xl mx-auto'>
            
            <header class='flex flex-col sm:flex-row justify-between items-center border-b border-slate-200 dark:border-slate-800 pb-4 mb-6 gap-4'>
                <div class='flex items-center space-x-2'>
                    <span class='text-xl font-bold tracking-tight text-slate-900 dark:text-slate-50'>📖 Экзегет.Дневник</span>
                </div>
                <div class='flex items-center space-x-3'>
                    
                    <button @click='toggleFriends()' 
                            :class=\"showFriends ? 'bg-purple-100 dark:bg-purple-950/40 text-purple-700 dark:text-purple-300 border-purple-300 dark:border-purple-800' : 'bg-slate-100 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400 border-slate-200 dark:border-slate-700'\" 
                            class='px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors cursor-pointer flex items-center space-x-1.5 select-none'>
                        <span>👥 Заметки друзей:</span>
                        <span x-text=\"showFriends ? 'Показаны' : 'Скрыты'\" class='font-bold'></span>
                    </button>
                    
                    <button @click='toggleDark()' 
                            class='px-3 py-1.5 text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-lg cursor-pointer text-xs font-medium flex items-center space-x-1 hover:bg-slate-200 dark:hover:bg-slate-700/60 transition-colors select-none'>
                        <span x-text=\"darkMode ? '☀️ Светлая' : '🌙 Тёмная'\"></span>
                    </button>
                    
                </div>
            </header>

            $content
            
        </div>
    </body></html>";
    }
}

