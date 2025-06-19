<?php
$jsonFile = 'data.json';

// Handle AJAX requests for saving FIRST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $postData = json_decode($input, true);

    if (!$postData) {
        $postData = $_POST;
    }

    header('Content-Type: application/json');

    if (isset($postData['action']) && $postData['action'] === 'save') {
        try {
            // Validate data structure
            if (!isset($postData['data'])) {
                echo json_encode(['success' => false, 'message' => 'No data provided']);
                exit;
            }

            $dataToSave = $postData['data'];

            // Save new data
            $jsonData = json_encode($dataToSave, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            if ($jsonData === false) {
                echo json_encode(['success' => false, 'message' => 'Failed to encode JSON: ' . json_last_error_msg()]);
                exit;
            }

            // Atomic write operation
            $tempFile = $jsonFile . '.tmp';
            if (file_put_contents($tempFile, $jsonData, LOCK_EX) === false) {
                echo json_encode(['success' => false, 'message' => 'Failed to write temporary file']);
                exit;
            }

            if (!rename($tempFile, $jsonFile)) {
                unlink($tempFile); // Clean up temp file
                echo json_encode(['success' => false, 'message' => 'Failed to move temporary file']);
                exit;
            }

            // Success response
            echo json_encode([
                'success' => true,
                'message' => 'Data saved successfully',
                'timestamp' => date('Y-m-d H:i:s'),
                'fileSize' => filesize($jsonFile)
            ]);
        } catch (Exception $e) {
            error_log("Save error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        }

        exit;
    }
}

// Check if file exists and is readable
if (!file_exists($jsonFile)) {
    die("Error: data.json file not found in " . __DIR__);
}

if (!is_readable($jsonFile)) {
    die("Error: data.json file is not readable. Check file permissions.");
}

// Get file contents
$jsonContent = file_get_contents($jsonFile);
if ($jsonContent === false) {
    die("Error: Could not read data.json file");
}

// Check if file is empty
if (empty(trim($jsonContent))) {
    die("Error: data.json file is empty");
}

// First, try to decode as-is
$data = json_decode($jsonContent, true);

// If JSON is invalid, create sample data
if ($data === null) {
    $sampleData = [
        'book_id' => 1150,
        'title' => 'Sample Book',
        'chapters' => [
            [
                'id' => 1,
                'title' => 'Sample Chapter',
                'questions' => [
                    [
                        'id' => 1,
                        'title' => 'Sample Question',
                        'answers' => [
                            [
                                'id' => 1,
                                'user_id' => 1,
                                'author_first_name' => 'User',
                                'author_last_name' => '',
                                'answer' => json_encode([
                                    'time' => time() * 1000,
                                    'text' => '<p>Start writing your answer here...</p>'
                                ])
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ];

    file_put_contents($jsonFile, json_encode($sampleData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $data = $sampleData;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Google Docs Style - Book Editor</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@300;400;500;600&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <link rel="stylesheet" href="./assets/styles.css">

</head>

<body>
    <!-- Google Docs Header -->
    <div class="header">
        <div class="header-left">
            <div class="docs-icon">📖</div>
            <input type="text" class="document-name" id="documentName" value="Book Editor" readonly>
        </div>
        <div class="header-right">
            <div class="status-indicator status-saved" id="statusIndicator">
                <span id="statusText">All changes saved</span>
            </div>
            <button class="share-btn" onclick="exportToPDF()" id="exportPdfBtn">📄 Export PDF</button>
            <button class="share-btn" onclick="saveAllChanges()">💾 Save All</button>
        </div>
    </div>

    <!-- Google Docs Toolbar -->
    <div class="toolbar-container">
        <div class="toolbar">
            <!-- Undo/Redo -->
            <div class="toolbar-section">
                <button class="toolbar-btn" onclick="execCommand('undo')" title="Undo (Ctrl+Z)">↶</button>
                <button class="toolbar-btn" onclick="execCommand('redo')" title="Redo (Ctrl+Y)">↷</button>
            </div>

            <!-- Print -->
            <div class="toolbar-section">
                <button class="toolbar-btn" onclick="printDocument()" title="Print (Ctrl+P)">🖨️</button>
            </div>

            <!-- Font Family -->
            <div class="toolbar-section">
                <select class="toolbar-select font-family-select" onchange="changeFontFamily(this.value)">
                    <option value="Times New Roman">Times New Roman</option>
                    <option value="Arial">Arial</option>
                    <option value="Calibri">Calibri</option>
                    <option value="Georgia">Georgia</option>
                    <option value="Helvetica">Helvetica</option>
                </select>
            </div>

            <!-- Font Size -->
            <div class="toolbar-section">
                <select class="toolbar-select font-size-select" onchange="changeFontSize(this.value)">
                    <option value="8">8</option>
                    <option value="9">9</option>
                    <option value="10">10</option>
                    <option value="11">11</option>
                    <option value="12" selected>12</option>
                    <option value="14">14</option>
                    <option value="16">16</option>
                    <option value="18">18</option>
                    <option value="20">20</option>
                    <option value="24">24</option>
                </select>
            </div>

            <!-- Text Formatting -->
            <div class="toolbar-section">
                <button class="toolbar-btn" id="boldBtn" onclick="toggleFormat('bold')" title="Bold (Ctrl+B)"><strong>B</strong></button>
                <button class="toolbar-btn" id="italicBtn" onclick="toggleFormat('italic')" title="Italic (Ctrl+I)"><em>I</em></button>
                <button class="toolbar-btn" id="underlineBtn" onclick="toggleFormat('underline')" title="Underline (Ctrl+U)"><u>U</u></button>
            </div>

            <!-- Alignment -->
            <div class="toolbar-section">
                <button class="toolbar-btn" onclick="alignText('left')" title="Align left">⬅️</button>
                <button class="toolbar-btn" onclick="alignText('center')" title="Center">⬆️</button>
                <button class="toolbar-btn" onclick="alignText('right')" title="Align right">➡️</button>
                <button class="toolbar-btn" onclick="alignText('justify')" title="Justify">⬌</button>
            </div>

            <!-- Lists -->
            <div class="toolbar-section">
                <button class="toolbar-btn" onclick="insertList('ul')" title="Bullet list">•</button>
                <button class="toolbar-btn" onclick="insertList('ol')" title="Numbered list">1.</button>
            </div>

            <!-- Insert -->
            <div class="toolbar-section">
                <button class="toolbar-btn" onclick="insertLink()" title="Insert link">🔗</button>
                <button class="toolbar-btn" onclick="insertImage()" title="Insert image">🖼️</button>
            </div>

            <!-- Styles -->
            <div class="toolbar-section">
                <select class="toolbar-select" onchange="applyStyle(this.value)">
                    <option value="p">Normal text</option>
                    <option value="h1">Heading 1</option>
                    <option value="h2">Heading 2</option>
                    <option value="h3">Heading 3</option>
                    <option value="h4">Heading 4</option>
                    <option value="h5">Heading 5</option>
                    <option value="h6">Heading 6</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Main Layout -->
    <div class="main-layout">
        <!-- Sidebar -->
        <div class="sidebar">
            <!-- <div class="sidebar-header">
                <div class="sidebar-title">Document Outline</div>
                <input type="text" class="search-input" id="searchInput" placeholder="Search questions...">
            </div> -->
            <div class="sidebar-header">
                <div class="sidebar-title">Document Outline</div>
                <input type="text" class="search-input" id="searchInput" placeholder="Search questions...">
                <button class="share-btn" id="addQuestionBtn" style="margin-top:10px;width:100%;">Add New Question</button>
            </div>
            <div class="questions-container" id="questionsContainer">
                <div class="loading">
                    <div class="spinner"></div>
                    Loading questions...
                </div>
            </div>
        </div>

        <!-- Editor Area -->
        <div class="editor-area">
            <div class="document-wrapper" id="documentWrapper">
                <!-- Pages will be dynamically created here -->
                <div class="document-page" id="page-1">
                    <div class="page-header">
                        <input type="text" class="question-title-input" id="questionTitle" placeholder="Enter question title...">
                    </div>
                    <div class="page-content" id="pageContent-1" contenteditable="true">
                        <p>Select a question from the sidebar to start editing...</p>
                    </div>
                    <div class="page-number">Page 1</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function addNewQuestion() {
            let chapterIdx = currentChapter >= 0 ? currentChapter : 0;
            if (!data.chapters || !data.chapters[chapterIdx]) return;

            const questions = data.chapters[chapterIdx].questions;
            const newId = questions.length > 0 ? Math.max(...questions.map(q => q.id || 0)) + 1 : 1;

            const newQuestion = {
                id: newId,
                title: 'Untitled Question',
                answers: [{
                    id: 1,
                    user_id: 1,
                    author_first_name: "User",
                    author_last_name: "",
                    answer: JSON.stringify({
                        time: Date.now(),
                        text: "<p>Start writing your answer here...</p>"
                    })
                }]
            };

            questions.push(newQuestion);

            // Immediately load the new question for editing
            loadSidebar();
            setTimeout(() => {
                loadQuestion(chapterIdx, questions.length - 1);
                // Highlight the new question
                const items = document.querySelectorAll('.question-item');
                if (items.length) {
                    items[items.length - 1].classList.add('active');
                }
            }, 100);
        }

        function deleteQuestion(chapterIndex, questionIndex) {
            if (!confirm('Are you sure you want to delete this question?')) return;

            if (
                data.chapters &&
                data.chapters[chapterIndex] &&
                data.chapters[chapterIndex].questions &&
                data.chapters[chapterIndex].questions[questionIndex]
            ) {
                data.chapters[chapterIndex].questions.splice(questionIndex, 1);

                // If the deleted question was selected, reset editor
                if (currentChapter === chapterIndex && currentQuestion === questionIndex) {
                    currentChapter = -1;
                    currentQuestion = -1;
                    document.getElementById('documentWrapper').innerHTML = `
                        <div class="document-page" id="page-1">
                            <div class="page-header">
                                <input type="text" class="question-title-input" id="questionTitle" placeholder="Enter question title...">
                            </div>
                            <div class="page-content" id="pageContent-1" contenteditable="true">
                                <p>Select a question from the sidebar to start editing...</p>
                            </div>
                            <div class="page-number">Page 1</div>
                        </div>
                    `;
                }

                saveToServer();
                loadSidebar();

                // Optionally, auto-select the next available question
                setTimeout(() => {
                    const chapter = data.chapters[chapterIndex];
                    if (chapter && chapter.questions.length > 0) {
                        loadQuestion(chapterIndex, 0);
                        // Highlight the first question
                        const items = document.querySelectorAll('.question-item');
                        if (items.length) items[0].classList.add('active');
                    }
                }, 200);
            }
        }
    </script>

    <script>
        // ...existing code for addNewQuestion, deleteQuestion, etc...

        // Global Variables
        let data = <?= json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        let currentChapter = -1;
        let currentQuestion = -1;
        let autoSaveTimer;
        let isLoading = false;
        let currentPageCount = 1;
        let pageBreakTimer;
        let autoSaveCount = 0;
        let lastServerSave = Date.now();

        const MAX_PAGE_HEIGHT = 820; // Maximum height for a page in pixels

        document.addEventListener('DOMContentLoaded', function() {
            initializeApp();
            const addBtn = document.getElementById('addQuestionBtn');
            if (addBtn) addBtn.addEventListener('click', addNewQuestion);
        });

        function initializeApp() {
            if (!data || !data.chapters) {
                updateStatus('Error: No data loaded', 'status-error');
                return;
            }
            loadSidebar();
            setupEventListeners();
            updateStatus('Ready to edit', 'status-saved');
            if (data.chapters.length > 0 && data.chapters[0].questions && data.chapters[0].questions.length > 0) {
                setTimeout(() => loadQuestion(0, 0), 500);
            }
        }

        function loadSidebar() {
            const container = document.getElementById('questionsContainer');
            container.innerHTML = '';
            if (!data.chapters || data.chapters.length === 0) {
                container.innerHTML = '<div class="loading">📝 No chapters available</div>';
                return;
            }
            let totalQuestions = 0;
            data.chapters.forEach((chapter, chapterIndex) => {
                if (chapter.questions && chapter.questions.length > 0) {
                    chapter.questions.forEach((question, questionIndex) => {
                        const questionItem = document.createElement('div');
                        questionItem.className = 'question-item';
                        questionItem.innerHTML = `
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <div>
                                <div class="question-chapter">${chapter.title || `Chapter ${chapterIndex + 1}`}</div>
                                <div class="question-title">${question.title || `Question ${questionIndex + 1}`}</div>
                            </div>
                            <button class="toolbar-btn" title="Delete Question" style="color:#d93025;font-size:18px;margin-left:8px;" onclick="event.stopPropagation(); deleteQuestion(${chapterIndex},${questionIndex});">✖</button>
                        </div>
                    `;
                        questionItem.onclick = () => {
                            loadQuestion(chapterIndex, questionIndex);
                            document.querySelectorAll('.question-item').forEach(item => item.classList.remove('active'));
                            questionItem.classList.add('active');
                        };
                        container.appendChild(questionItem);
                        totalQuestions++;
                    });
                }
            });
            if (totalQuestions === 0) {
                container.innerHTML = '<div class="loading">📝 No questions found</div>';
            }
        }

        function saveCurrentContent() {
            if (currentChapter < 0 || currentQuestion < 0) return;
            const allData = getAllContent();
            const title = allData.title;
            const content = allData.content;
            data.chapters[currentChapter].questions[currentQuestion].title = title;
            const answerObj = {
                time: Date.now(),
                text: content
            };
            if (!data.chapters[currentChapter].questions[currentQuestion].answers) {
                data.chapters[currentChapter].questions[currentQuestion].answers = [{
                    id: 1,
                    user_id: 1,
                    author_first_name: "User",
                    author_last_name: "",
                    answer: JSON.stringify(answerObj)
                }];
            } else {
                data.chapters[currentChapter].questions[currentQuestion].answers[0].answer = JSON.stringify(answerObj);
            }
            updateStatus('Auto-saved locally', 'status-saved');
            autoSaveCount++;
            if (autoSaveCount >= 5 || (Date.now() - lastServerSave) > 30000) saveToServer();
        }

        function saveToServer() {
            updateStatus('Syncing to server...', 'status-saving');
            const saveData = {
                action: 'save',
                data: data
            };
            const xhr = new XMLHttpRequest();
            xhr.open('POST', window.location.href, true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.onload = function() {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        if (xhr.responseText.trim().startsWith('<!DOCTYPE') || xhr.responseText.trim().startsWith('<html')) {
                            updateStatus('Server error: HTML response', 'status-error');
                            return;
                        }
                        const result = JSON.parse(xhr.responseText);
                        if (result.success) {
                            updateStatus('Synced to server', 'status-saved');
                            lastServerSave = Date.now();
                            autoSaveCount = 0;
                        } else {
                            updateStatus('Sync failed: ' + (result.message || 'Unknown error'), 'status-error');
                        }
                    } catch {
                        updateStatus('Server response error', 'status-error');
                    }
                } else {
                    updateStatus('Server error: ' + xhr.status, 'status-error');
                }
            };
            xhr.onerror = function() {
                updateStatus('Network error', 'status-error');
            };
            try {
                xhr.send(JSON.stringify(saveData));
            } catch {
                updateStatus('Send error', 'status-error');
            }
        }

        function saveAllChanges() {
            if (currentChapter >= 0 && currentQuestion >= 0) saveCurrentContent();
            updateStatus('Saving all data...', 'status-saving');
            saveToServer();
        }

        // 1. Pagination: Recursively split until all pages fit
        function checkForOverflow() {
            let splitHua = false;
            for (let pageNum = 1; pageNum <= currentPageCount; pageNum++) {
                const pageContent = document.getElementById(`pageContent-${pageNum}`);
                if (pageContent && pageContent.scrollHeight > MAX_PAGE_HEIGHT) {
                    const nextPageNum = pageNum + 1;
                    if (!document.getElementById(`page-${nextPageNum}`)) createNextPage(nextPageNum);
                    moveContentToNextPage(pageNum, nextPageNum);
                    splitHua = true;
                }
            }
            // Agar split hua hai, firse check karo (recursively)
            if (splitHua) setTimeout(checkForOverflow, 100);
            // Remove empty pages except first
            removeEmptyPages();
        }

        // 2. Move overflow content to next page (block-wise, not character-wise)
        function moveContentToNextPage(fromPageNum, toPageNum) {
            const fromPage = document.getElementById(`pageContent-${fromPageNum}`);
            const toPage = document.getElementById(`pageContent-${toPageNum}`);
            if (!fromPage || !toPage) return;

            let children = Array.from(fromPage.children);
            let totalHeight = 0,
                splitIndex = children.length;
            for (let i = 0; i < children.length; i++) {
                totalHeight += children[i].offsetHeight;
                if (totalHeight > MAX_PAGE_HEIGHT) {
                    splitIndex = i;
                    break;
                }
            }
            // Move all blocks after splitIndex to next page (append at end, not at start)
            while (fromPage.children.length > splitIndex) {
                toPage.appendChild(fromPage.children[splitIndex]);
            }
        }

        // 3. Remove empty pages except first
        function removeEmptyPages() {
            for (let i = currentPageCount; i > 1; i--) {
                const page = document.getElementById(`page-${i}`);
                const content = document.getElementById(`pageContent-${i}`);
                if (page && content && !content.innerText.trim()) {
                    page.parentNode.removeChild(page);
                    currentPageCount--;
                }
            }
        }

        // 3. Create next page (if not exists)
        function createNextPage(pageNumber) {
            const wrapper = document.getElementById('documentWrapper');
            const newPage = document.createElement('div');
            newPage.className = 'document-page';
            newPage.id = `page-${pageNumber}`;
            newPage.innerHTML = `
                <div class="page-content" id="pageContent-${pageNumber}" contenteditable="true"></div>
                <div class="page-number">Page ${pageNumber}</div>
            `;
            wrapper.appendChild(newPage);
            currentPageCount = pageNumber;
            setupPageEventListeners(pageNumber);
        }

        // 4. Setup event listeners for each page (input, paste, etc)
        function setupPageEventListeners(pageNumber) {
            const pageContent = document.getElementById(`pageContent-${pageNumber}`);
            if (!pageContent) return;
            pageContent.addEventListener('input', () => {
                updateStatus('Editing...', 'status-saving');
                clearTimeout(autoSaveTimer);
                clearTimeout(pageBreakTimer);
                pageBreakTimer = setTimeout(checkForOverflow, 200);
                autoSaveTimer = setTimeout(saveCurrentContent, 2000);
            });
            pageContent.addEventListener('paste', (event) => {
                // --- Fix: Handle pasted images and text in order ---
                const items = (event.clipboardData || event.originalEvent.clipboardData).items;
                let handled = false;
                for (let i = 0; i < items.length; i++) {
                    if (items[i].type.indexOf("image") === 0) {
                        event.preventDefault();
                        const blob = items[i].getAsFile();
                        const reader = new FileReader();
                        reader.onload = function (evt) {
                            insertHtmlAtCursor(`<img src="${evt.target.result}" />`);
                        };
                        reader.readAsDataURL(blob);
                        handled = true;
                    }
                }
                // Agar image nahi hai, toh default paste hone dein (text/html)
                if (!handled) {
                    // Default: allow normal paste
                }
                clearTimeout(pageBreakTimer);
                pageBreakTimer = setTimeout(checkForOverflow, 300);
            });
            pageContent.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    setTimeout(checkForOverflow, 100);
                }
            });
            pageContent.addEventListener('keydown', handleKeydown);
        }

        // Utility: Insert HTML at cursor position
        function insertHtmlAtCursor(html) {
            let sel, range;
            if (window.getSelection) {
                sel = window.getSelection();
                if (sel.getRangeAt && sel.rangeCount) {
                    range = sel.getRangeAt(0);
                    range.deleteContents();
                    const el = document.createElement("div");
                    el.innerHTML = html;
                    let frag = document.createDocumentFragment(), node, lastNode;
                    while ((node = el.firstChild)) {
                        lastNode = frag.appendChild(node);
                    }
                    range.insertNode(frag);
                    // Move cursor after inserted node
                    if (lastNode) {
                        range = range.cloneRange();
                        range.setStartAfter(lastNode);
                        range.collapse(true);
                        sel.removeAllRanges();
                        sel.addRange(range);
                    }
                }
            }
        }

        // 5. On question load, reset page count and call checkForOverflow
        function loadQuestion(chapterIndex, questionIndex) {
            if (isLoading) return;
            if (currentChapter >= 0 && currentQuestion >= 0) saveCurrentContent();
            isLoading = true;
            updateStatus('Loading...', 'status-saving');
            currentChapter = chapterIndex;
            currentQuestion = questionIndex;
            const question = data.chapters[chapterIndex].questions[questionIndex];
            let content = '';
            if (question.answers && question.answers[0] && question.answers[0].answer) {
                try {
                    const answerData = JSON.parse(question.answers[0].answer);
                    content = answerData.text || '<p>Start writing your answer here...</p>';
                } catch {
                    content = '<p>Error loading content. Start writing here...</p>';
                }
            } else {
                content = '<p>Start writing your answer here...</p>';
            }
            const wrapper = document.getElementById('documentWrapper');
            wrapper.innerHTML = `
                <div class="document-page" id="page-1">
                    <div class="page-header">
                        <input type="text" class="question-title-input" id="questionTitle" placeholder="Enter question title..." value="${question.title || ''}">
                    </div>
                    <div class="page-content" id="pageContent-1" contenteditable="true">
                        ${content}
                    </div>
                    <div class="page-number">Page 1</div>
                </div>
            `;
            currentPageCount = 1;
            setupPageEventListeners(1);
            setupEventListeners();

            // Only one pagination pass, after DOM is ready
            setTimeout(() => {
                checkForOverflow();
                isLoading = false;
                updateStatus('Loaded successfully', 'status-saved');
            }, 50);
        }
        // 6. On delete/backspace at start/end, merge pages (already in your code)
        function handleKeydown(event) {
            const selection = window.getSelection();
            if (!selection.rangeCount) return;
            const range = selection.getRangeAt(0);
            const currentPage = getCurrentPage(range.startContainer);

            // BACKSPACE at start of page
            if (event.key === 'Backspace') {
                const previousPage = getPreviousPage(currentPage);
                if (previousPage && isAtPageStart(currentPage.querySelector('.page-content'), range)) {
                    event.preventDefault();
                    const prevContent = previousPage.querySelector('.page-content');
                    const currContent = currentPage.querySelector('.page-content');
                    if (prevContent && currContent) {
                        while (currContent.firstChild) {
                            prevContent.appendChild(currContent.firstChild);
                        }
                        if (!currContent.hasChildNodes()) {
                            currentPage.parentNode.removeChild(currentPage);
                            currentPageCount--;
                        }
                        // setTimeout(() => {
                        //     placeCursorAtEnd(prevContent);
                        //     prevContent.scrollIntoView({
                        //         behavior: "smooth",
                        //         block: "end"
                        //     });
                        //     checkForOverflow();
                        // }, 0);
                        setTimeout(() => {
                            if (prevContent && prevContent.lastChild) {
                                let node = prevContent.lastChild;
                                placeCursorAtStart(node);
                            } else {
                                placeCursorAtEnd(prevContent);
                            }
                            prevContent.scrollIntoView({
                                behavior: "smooth",
                                block: "end"
                            });
                            checkForOverflow();
                        }, 0);
                    }
                }
            }
            // DELETE at end of page
            else if (event.key === 'Delete') {
                const nextPage = getNextPage(currentPage);
                if (nextPage && isAtPageEnd(currentPage.querySelector('.page-content'), range)) {
                    event.preventDefault();
                    const currContent = currentPage.querySelector('.page-content');
                    const nextContent = nextPage.querySelector('.page-content');
                    if (currContent && nextContent) {
                        while (nextContent.firstChild) {
                            currContent.appendChild(nextContent.firstChild);
                        }
                        if (!nextContent.hasChildNodes()) {
                            nextPage.parentNode.removeChild(nextPage);
                            currentPageCount--;
                        }
                        setTimeout(() => {
                            placeCursorAtEnd(currContent);
                            currContent.scrollIntoView({
                                behavior: "smooth",
                                block: "end"
                            });
                            checkForOverflow();
                        }, 0);
                    }
                }
            }
        }

        // 7. Utility: Place cursor at end of node
        function placeCursorAtEnd(node) {
            const range = document.createRange();
            range.selectNodeContents(node);
            range.collapse(false);
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
        }

        // 8. Utility: Place cursor at start of node
        function placeCursorAtStart(node) {
            const range = document.createRange();
            if (node.nodeType === Node.TEXT_NODE) {
                range.setStart(node, 0);
            } else {
                range.selectNodeContents(node);
                range.collapse(true);
            }
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
        }

        // 9. Utility: Get current/prev/next page
        function getCurrentPage(container) {
            while (container && !container.classList?.contains('document-page')) {
                container = container.parentNode;
            }
            return container;
        }

        function getPreviousPage(currentPage) {
            if (!currentPage) return null;
            return currentPage.previousElementSibling && currentPage.previousElementSibling.classList.contains('document-page') ?
                currentPage.previousElementSibling : null;
        }

        function getNextPage(currentPage) {
            if (!currentPage) return null;
            return currentPage.nextElementSibling && currentPage.nextElementSibling.classList.contains('document-page') ?
                currentPage.nextElementSibling : null;
        }

        function isAtPageStart(pageContent, range) {
            if (!range.collapsed) return false;
            if (range.startContainer === pageContent && range.startOffset === 0) return true;
            if (pageContent.firstChild && range.startContainer === pageContent.firstChild && range.startOffset === 0) return true;
            let node = range.startContainer;
            while (node && node !== pageContent) {
                if (node.previousSibling) return false;
                node = node.parentNode;
            }
            return true;
        }

        function isAtPageEnd(pageContent, range) {
            if (!range.collapsed) return false;
            let node = range.endContainer;
            let offset = range.endOffset;
            if (node.nodeType === Node.TEXT_NODE) {
                if (offset !== node.length) return false;
            } else if (node.nodeType === Node.ELEMENT_NODE) {
                if (offset !== node.childNodes.length) return false;
            }
            let last = pageContent.lastChild;
            while (last && last.lastChild) last = last.lastChild;
            return node === last || node === pageContent;
        }

        // 10. On save, collect all page contents in order
        function getAllContent() {
            let allContent = '';
            let title = '';
            const titleInput = document.getElementById('questionTitle');
            if (titleInput) title = titleInput.value;
            for (let i = 1; i <= currentPageCount; i++) {
                const pageContent = document.getElementById(`pageContent-${i}`);
                if (pageContent) allContent += pageContent.innerHTML;
            }
            return {
                title,
                content: allContent
            };
        }

        function exportToPDF() {
            updateStatus('Generating PDF...', 'status-saving');
            if (typeof window.jspdf === 'undefined' && typeof window.jsPDF === 'undefined') {
                alert('PDF library not loaded. Please refresh the page and try again.');
                updateStatus('PDF export failed', 'status-error');
                return;
            }
            try {
                let jsPDF;
                if (window.jspdf && window.jspdf.jsPDF) jsPDF = window.jspdf.jsPDF;
                else if (window.jsPDF) jsPDF = window.jsPDF;
                else throw new Error('jsPDF not found');
                const pdf = new jsPDF('p', 'mm', 'a4');
                const pages = document.querySelectorAll('.document-page');
                let y = 20;
                pdf.setFont('Times', 'normal');
                pdf.setFontSize(16);
                pdf.text('Book Export', 20, y);
                y += 10;
                pdf.setFontSize(12);
                pdf.text(`Generated on: ${new Date().toLocaleDateString()}`, 20, y);
                y += 15;
                pages.forEach((page, idx) => {
                    if (idx > 0) {
                        pdf.addPage();
                        pdf.setFont('Times', 'normal');
                        y = 20;
                    }
                    const titleInput = page.querySelector('.question-title-input');
                    if (titleInput && titleInput.value) {
                        pdf.setFont('Times', 'bold');
                        pdf.setFontSize(14);
                        const wrappedTitle = pdf.splitTextToSize(titleInput.value, 170);
                        pdf.text(wrappedTitle, 20, y);
                        y += wrappedTitle.length * 8;
                    }
                    const contentDiv = page.querySelector('.page-content');
                    if (contentDiv) {
                        pdf.setFont('Times', 'normal');
                        pdf.setFontSize(12);
                        let text = contentDiv.innerText || contentDiv.textContent || '';
                        let lines = pdf.splitTextToSize(text, 170);
                        pdf.text(lines, 20, y);
                    }
                });
                const fileName = `Book_Export_${new Date().toISOString().split('T')[0]}.pdf`;
                pdf.save(fileName);
                updateStatus('PDF exported successfully', 'status-saved');
            } catch (error) {
                updateStatus('PDF export failed', 'status-error');
            }
        }

        function setupEventListeners() {
            const titleInput = document.getElementById('questionTitle');
            if (titleInput) {
                titleInput.addEventListener('input', () => {
                    updateStatus('Editing...', 'status-saving');
                    clearTimeout(autoSaveTimer);
                    autoSaveTimer = setTimeout(() => {
                        saveCurrentContent();
                        loadSidebar();
                    }, 2000);
                });
                titleInput.addEventListener('keydown', () => {
                    clearTimeout(autoSaveTimer);
                    autoSaveTimer = setTimeout(() => {
                        saveCurrentContent();
                        loadSidebar();
                    }, 2000);
                });
            }
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('input', (e) => filterQuestions(e.target.value));
            }
            document.addEventListener('keydown', (e) => {
                if (e.ctrlKey || e.metaKey) {
                    switch (e.key) {
                        case 'b':
                            e.preventDefault();
                            toggleFormat('bold');
                            break;
                        case 'i':
                            e.preventDefault();
                            toggleFormat('italic');
                            break;
                        case 'u':
                            e.preventDefault();
                            toggleFormat('underline');
                            break;
                        case 's':
                            e.preventDefault();
                            saveAllChanges();
                            break;
                        case 'p':
                            e.preventDefault();
                            printDocument();
                            break;
                    }
                }
            });
            document.addEventListener('selectionchange', updateToolbarState);
            setupPageEventListeners(1);
        }

        function execCommand(command, value = null) {
            document.execCommand(command, false, value);
            updateToolbarState();
        }

        function updateToolbarState() {
            const commands = ['bold', 'italic', 'underline'];
            commands.forEach(command => {
                const button = document.getElementById(command + 'Btn');
                if (button) button.classList.toggle('active', document.queryCommandState(command));
            });
        }

        function toggleFormat(command) {
            execCommand(command);
        }

        function changeFontFamily(fontFamily) {
            execCommand('fontName', fontFamily);
        }

        function changeFontSize(size) {
            execCommand('fontSize', size);
        }

        function alignText(alignment) {
            const commands = {
                'left': 'justifyLeft',
                'center': 'justifyCenter',
                'right': 'justifyRight',
                'justify': 'justifyFull'
            };
            execCommand(commands[alignment]);
        }

        function insertList(listType) {
            const command = listType === 'ul' ? 'insertUnorderedList' : 'insertOrderedList';
            execCommand(command);
        }

        function applyStyle(tag) {
            execCommand('formatBlock', tag);
        }

        function insertLink() {
            const url = prompt('Enter URL:');
            if (url) execCommand('createLink', url);
        }

        function insertImage() {
            const url = prompt('Enter image URL:');
            if (url) execCommand('insertImage', url);
        }

        function printDocument() {
            window.print();
        }

        function filterQuestions(searchTerm) {
            const container = document.getElementById('questionsContainer');
            const items = container.querySelectorAll('.question-item');
            items.forEach(item => {
                const chapterText = item.querySelector('.question-chapter').textContent.toLowerCase();
                const questionText = item.querySelector('.question-title').textContent.toLowerCase();
                const matches = chapterText.includes(searchTerm.toLowerCase()) || questionText.includes(searchTerm.toLowerCase());
                item.style.display = matches ? 'block' : 'none';
            });
        }

        function updateStatus(message, className) {
            const statusText = document.getElementById('statusText');
            const statusIndicator = document.getElementById('statusIndicator');
            statusText.textContent = message;
            statusIndicator.className = 'status-indicator ' + className;
        }
    </script>
</body>

</html>