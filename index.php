<?php
// File: editor.php
// Purpose: Main application file (frontend HTML, Tailwind CSS, JavaScript).
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KEBENET Dapo PHP Code Editor</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.33.0/ace.js" xintegrity="sha512-tr8DJS6DGZ9HRAUeS5uJsM0CUSY3f5N07s20XfQWJ58s9TqSGU43TjT+L8s2C5MdcjV72uEsX0j3Z2oTj2fSA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <style>
        body {
            font-family: 'Lato', sans-serif;
            /* overflow: hidden; Flexbox will handle overflow */
        }
        .ace_editor {
            height: 100%;
            width: 100%;
            border-radius: 0.375rem; /* rounded-md */
        }
        /* Adjust height calculations if navbar height changes from h-16 (4rem) */
        .main-content-area {
            height: calc(100vh - 4rem); /* Full height minus navbar */
        }
        #file-explorer {
            height: 100%; /* Will be controlled by flex parent */
            overflow-y: auto;
        }
        #editor-area-container { 
            height: 100%; /* Will be controlled by flex parent */
        }
        .tab {
            cursor: pointer;
            padding: 0.5rem 1rem;
            border-bottom: 2px solid transparent;
            white-space: nowrap;
        }
        .tab.active {
            border-bottom-color: #2e9578; /* KEBENET PRIMARY color */
            color: #2e9578; /* KEBENET PRIMARY color */
            font-weight: 600;
        }
        .tab-close-btn {
            margin-left: 0.5rem;
            padding: 0.1rem 0.25rem;
            border-radius: 0.25rem;
        }
        .tab-close-btn:hover {
            background-color: #ef4444; /* red-500 */
            color: white;
        }
        .file-item:hover {
            background-color: #f3f4f6; /* gray-100 */
        }
        #file-explorer::-webkit-scrollbar { width: 8px; }
        #file-explorer::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        #file-explorer::-webkit-scrollbar-thumb { background: #c7c7c7; border-radius: 10px; }
        #file-explorer::-webkit-scrollbar-thumb:hover { background: #a3a3a3; }
        
        /* Notification Styling */
        #notification-area {
            position: fixed;
            top: calc(4rem + 1rem); /* Below navbar + some margin */
            right: 1rem;
            z-index: 1050; /* High z-index */
            width: auto;
            max-width: 350px;
        }
        @keyframes fadeInNotification { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes fadeOutNotification { from { opacity: 1; transform: translateY(0); } to { opacity: 0; transform: translateY(-20px); } }
        .notification-item {
            animation-duration: 0.3s;
            animation-fill-mode: forwards;
        }
        .notification-item.fade-in { animation-name: fadeInNotification; }
        .notification-item.fade-out { animation-name: fadeOutNotification; }

    </style>
</head>
<body class="flex flex-col h-screen overflow-hidden">

    <?php include 'navbar.php'; ?>

    <div id="notification-area">
        </div>

    <div class="main-content-area flex flex-grow overflow-hidden">
        <div class="w-1/4 bg-white border-r border-gray-200 flex flex-col overflow-hidden">
            <div class="p-3 border-b border-gray-200 flex items-center space-x-2 bg-gray-50 flex-shrink-0">
                <button id="back-button" class="p-2 rounded-md hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed" title="Go Up">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <div id="current-path-display" class="text-sm font-medium text-gray-700 truncate flex-grow" title="/">/</div>
            </div>
            <div id="file-explorer" class="flex-grow p-2 space-y-1">
                </div>
        </div>

        <div class="w-3/4 flex flex-col bg-gray-50 overflow-hidden">
            <div id="tab-bar" class="flex border-b border-gray-200 overflow-x-auto flex-shrink-0">
                </div>

            <div id="editor-area-container" class="flex-grow relative p-2">
                <div id="editor-container" class="h-full w-full shadow-sm"></div>
                <div id="editor-placeholder" class="absolute inset-0 flex items-center justify-center text-gray-400 text-lg rounded-md pointer-events-none">
                    <div class="text-center">
                        <i class="fas fa-file-code fa-3x mb-2"></i>
                        <p>Select a file to view or edit.</p>
                        <p class="text-sm mt-1">Or navigate folders on the left.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // --- Configuration ---
        const FOLDER_API_ENDPOINT = 'folder.php'; // For list and get_content
        const SAVE_API_ENDPOINT = 'save-file.php';  // For saving files

        // --- Global State ---
        let currentPath = '/';
        let openTabs = []; 
        let activeTabId = null;
        let aceEditor;
        let notificationTimeoutId; // Store timeout ID for notifications

        // --- DOM Elements ---
        const fileExplorerEl = document.getElementById('file-explorer');
        const tabBarEl = document.getElementById('tab-bar');
        const editorContainerEl = document.getElementById('editor-container');
        const editorPlaceholderEl = document.getElementById('editor-placeholder');
        const backButton = document.getElementById('back-button');
        const currentPathDisplayEl = document.getElementById('current-path-display');
        const saveButton = document.getElementById('save-button'); // Save button from navbar
        const notificationAreaEl = document.getElementById('notification-area');

        // --- Helper Functions ---
        function displayFileExplorerError(message) {
            fileExplorerEl.innerHTML = `<div class="p-4 text-red-600 bg-red-100 border border-red-300 rounded-md text-sm">${message}</div>`;
        }

        function showNotification(message, type = 'info', duration = 3000) {
            if (!notificationAreaEl) return;

            const notification = document.createElement('div');
            // Base classes for all notifications
            let baseClasses = 'notification-item p-3 rounded-md shadow-lg mb-2 text-sm flex items-center ';
            let iconHtml = '';

            if (type === 'success') {
                notification.className = baseClasses + 'bg-green-500 text-white fade-in';
                iconHtml = '<i class="fas fa-check-circle mr-2"></i>';
            } else if (type === 'error') {
                notification.className = baseClasses + 'bg-red-500 text-white fade-in';
                iconHtml = '<i class="fas fa-exclamation-circle mr-2"></i>';
            } else { // info
                notification.className = baseClasses + 'bg-blue-500 text-white fade-in';
                iconHtml = '<i class="fas fa-info-circle mr-2"></i>';
            }
            
            notification.innerHTML = iconHtml + message;
            // Prepend to show newest on top
            notificationAreaEl.insertBefore(notification, notificationAreaEl.firstChild); 

            // Clear any existing timeout to prevent premature removal of older notifications if new one comes fast
            // This simple version just clears one global timeout; a more robust system might track timeouts per notification.
            // For now, let's just ensure this notification has its own timeout.
            const currentNotificationTimeout = setTimeout(() => {
                notification.classList.remove('fade-in');
                notification.classList.add('fade-out');
                // Remove the element after the fade-out animation completes
                setTimeout(() => {
                    if (notification.parentNode) { // Check if still in DOM
                        notification.remove();
                    }
                }, 300); // Match animation duration
            }, duration);
        }


        function updateSaveButtonState() {
            if (!saveButton) return;
            const activeTabData = openTabs.find(t => t.id === activeTabId);
            if (activeTabData && activeTabData.unsavedChanges) {
                saveButton.disabled = false;
                saveButton.title = `Save ${activeTabData.name} (Ctrl+S / Cmd+S)`;
            } else {
                saveButton.disabled = true;
                saveButton.title = activeTabData ? `No unsaved changes in ${activeTabData.name}` : "No active file to save";
            }
        }

        // --- Server Communication ---
        async function fetchDirectoryListingFromServer(path) {
            try {
                const response = await fetch(`${FOLDER_API_ENDPOINT}?action=list&path=${encodeURIComponent(path)}`); 
                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({ error: `Failed to fetch directory: ${response.status} ${response.statusText}` }));
                    console.error('Error fetching directory:', errorData.error);
                    displayFileExplorerError(errorData.error || 'Unknown error fetching directory.');
                    return { path: path, parentPath: null, items: [], error: errorData.error };
                }
                const data = await response.json();
                if (data.error) {
                    console.error('Error from server (list):', data.error);
                    displayFileExplorerError(data.error);
                }
                return data;
            } catch (error) {
                console.error('Network or parsing error fetching directory:', error);
                displayFileExplorerError('Network error while fetching directory. Is the server running?');
                return { path: path, parentPath: null, items: [], error: 'Network error.' };
            }
        }

        async function fetchFileContentFromServer(filePath) {
            try {
                const response = await fetch(`${FOLDER_API_ENDPOINT}?action=get_content&path=${encodeURIComponent(filePath)}`);
                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({ error: `Failed to fetch file: ${response.status} ${response.statusText}` }));
                    console.error('Error fetching file content:', errorData.error);
                    return { filePath, content: `// Error loading file: ${errorData.error || 'Unknown error'}`, error: errorData.error };
                }
                const data = await response.json();
                if (data.error) {
                    console.error('Error from server (get_content):', data.error);
                }
                return data;
            } catch (error) {
                console.error('Network or parsing error fetching file content:', error);
                return { filePath, content: `// Network error loading file: ${error.message}`, error: 'Network error.' };
            }
        }
        


async function saveFileToServer(filePath, content) {
    const originalButtonContent = saveButton.innerHTML;
    saveButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
    saveButton.disabled = true;

    let notificationMessage = '';
    let notificationType = 'error'; // Default to error

    try {
        const formData = new URLSearchParams();
        formData.append('filePath', filePath);
        formData.append('content', content);

        const response = await fetch(SAVE_API_ENDPOINT, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                // Add any other headers your API might require, like CSRF tokens or Authorization
            },
            body: formData.toString()
        });

        // Check if the HTTP request itself was successful (e.g., status 200-299)
        if (!response.ok) {
            const status = response.status;
            const statusText = response.statusText;
            let errorDetail = `HTTP Error ${status}: ${statusText}`;

            try {
                // Attempt to get the raw response body for more details
                // This is crucial for capturing HTML error pages (like a 403 Forbidden page)
                const errorBody = await response.text();
                // Display a snippet of the error body to avoid overly long messages
                const snippet = errorBody.substring(0, 200) + (errorBody.length > 200 ? '...' : '');
                errorDetail += `\nServer response snippet: ${snippet}`;
                console.error(`Server Error Response (Status ${status}):`, errorBody); // Log the full error body
            } catch (textError) {
                console.error('Could not read error response body:', textError);
                errorDetail += '\n(Could not retrieve detailed server response body)';
            }
            // Throw an error to be caught by the main catch block
            throw new Error(errorDetail);
        }

        // If response.ok is true, proceed to handle the expected JSON response
        const contentType = response.headers.get("content-type");
        if (contentType && contentType.includes("application/json")) {
            const result = await response.json();

            if (result.success) {
                notificationMessage = `File "${filePath.split('/').pop()}" saved successfully!`;
                notificationType = 'success'; // Corresponds to Bulma's 'is-success'
                const tab = openTabs.find(t => t.path === filePath);
                if (tab) {
                    tab.unsavedChanges = false;
                    updateTabAppearance(tab.id);
                }
            } else {
                // Application-level error from the server (e.g., PHP script validation)
                notificationMessage = `Error saving file: ${result.error || 'Unknown server error'}`;
                console.error('Application error from server:', result);
            }
        } else {
            // Response was OK (2xx), but not JSON as expected.
            const unexpectedBody = await response.text();
            notificationMessage = 'Unexpected response format from server. Expected JSON.';
            console.error('Unexpected response from server:', {
                status: response.status,
                contentType: contentType,
                body: unexpectedBody
            });
        }

    } catch (error) { // Catches network errors, errors thrown from !response.ok, or JSON parsing if content-type was wrong
        console.error('Error during save operation:', error); // Logs the full error object
        notificationMessage = `Failed to save file: ${error.message}`; // error.message will include the detailed HTTP error
    } finally {
        if (notificationMessage) {
            showNotification(notificationMessage, notificationType);
        }
        saveButton.innerHTML = originalButtonContent;
        updateSaveButtonState();
        
    }
}

        // --- Ace Editor Setup ---
        function initAceEditor() {
            aceEditor = ace.edit(editorContainerEl);
            aceEditor.setTheme("ace/theme/tomorrow_night_eighties");
            aceEditor.session.setMode("ace/mode/php");
            aceEditor.setShowPrintMargin(false);
            aceEditor.setFontSize("14px");


            aceEditor.on('change', () => {
                if (activeTabId) {
                    const tab = openTabs.find(t => t.id === activeTabId);
                    if (tab && !tab.unsavedChanges) {
                        tab.unsavedChanges = true;
                        updateTabAppearance(tab.id);
                    }
                    updateSaveButtonState();
                }
            });

            aceEditor.commands.addCommand({
                name: 'saveFile',
                bindKey: {win: 'Ctrl-S', mac: 'Command-S'},
                exec: function(editor) {
                    if (saveButton && !saveButton.disabled) { // Check if saveButton exists
                        saveButton.click();
                    }
                },
                readOnly: false
            });
        }
        
        // --- File Explorer Logic ---
        async function loadDirectory(path) {
            currentPath = path;
            currentPathDisplayEl.textContent = path;
            currentPathDisplayEl.title = path;
            fileExplorerEl.innerHTML = '<div class="text-center text-gray-400 p-4"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';

            const data = await fetchDirectoryListingFromServer(path);
            
            fileExplorerEl.innerHTML = ''; 
            
            if (data.error) {
                backButton.disabled = path === '/';
                return;
            }

            if (!data.items || data.items.length === 0) {
                 if (path !== '/') { 
                    fileExplorerEl.innerHTML = '<div class="text-center text-gray-400 p-4">Folder is empty or not accessible.</div>';
                 } else if (!data.items) { 
                    fileExplorerEl.innerHTML = '<div class="text-center text-gray-400 p-4">Could not load directory contents.</div>';
                 }
            }

            if (data.items) {
                data.items.forEach(item => {
                    const itemEl = document.createElement('div');
                    itemEl.className = 'file-item p-2 rounded-md cursor-pointer flex items-center space-x-2 text-sm text-gray-700 hover:bg-gray-100';
                    itemEl.title = item.name; // Already sanitized by PHP
                    
                    const icon = document.createElement('i');
                    // item.name is already htmlspecialchar'd, so getFileIcon needs the raw name if it relies on extension
                    // For now, assuming getFileIcon is robust or item.name is fine
                    icon.className = `fas ${item.type === 'folder' ? 'fa-folder text-yellow-500' : getFileIcon(item.name)} mr-2 w-4 text-center`;
                    itemEl.appendChild(icon);

                    const nameSpan = document.createElement('span');
                    nameSpan.textContent = item.name; // Already sanitized by PHP
                    nameSpan.className = 'truncate';
                    itemEl.appendChild(nameSpan);

                    itemEl.addEventListener('click', () => {
                        if (item.type === 'folder') {
                            loadDirectory(item.path); // item.path is already sanitized by PHP
                        } else {
                            openFile(item.path, item.name); // item.path & item.name are sanitized
                        }
                    });
                    fileExplorerEl.appendChild(itemEl);
                });
            }
            backButton.disabled = path === '/' || data.parentPath === null;
        }

        function getFileIcon(fileName) { 
            
            const decodedFileName = document.createElement('textarea'); // Temp element to decode
            decodedFileName.innerHTML = fileName;
            const rawFileName = decodedFileName.value;

            const ext = rawFileName.split('.').pop().toLowerCase();
            switch (ext) {
                case 'php': return 'fa-brands fa-php text-indigo-500';
                case 'js': return 'fa-brands fa-js-square text-yellow-400';
                case 'css': return 'fa-brands fa-css3-alt text-blue-500';
                case 'html': return 'fa-brands fa-html5 text-orange-500';
                case 'md': return 'fa-brands fa-markdown text-gray-600';
                case 'json': return 'fa-solid fa-file-code text-green-500';
                case 'ini': return 'fa-solid fa-file-invoice text-purple-500';
                case 'txt': return 'fa-solid fa-file-alt text-gray-500';
                default: return 'fa-solid fa-file text-gray-500';
            }
        }

        function goUpOneLevel() {
            if (currentPath === '/') return;
            fetchDirectoryListingFromServer(currentPath).then(data => {
                if (data && data.parentPath) {
                    loadDirectory(data.parentPath);
                } else if (currentPath !== '/') { 
                    const pathParts = currentPath.split('/').filter(p => p);
                    pathParts.pop();
                    const parentPathFallback = pathParts.length > 0 ? '/' + pathParts.join('/') : '/';
                    loadDirectory(parentPathFallback);
                }
            });
        }

        // --- Tab Management ---
        function generateTabId(filePath) { // filePath is sanitized from server
            return 'tab-' + filePath.replace(/[^a-zA-Z0-9_-]/g, '_');
        }

        async function openFile(filePath, fileName) { // filePath & fileName are sanitized from server
            const tabId = generateTabId(filePath);

            let tab = openTabs.find(t => t.id === tabId);
            if (!tab) {
                editorPlaceholderEl.innerHTML = '<div class="text-center text-gray-400 p-4"><i class="fas fa-spinner fa-spin"></i> Loading file...</div>';
                editorPlaceholderEl.style.display = 'flex'; 
                editorContainerEl.style.visibility = 'hidden';

                const fileData = await fetchFileContentFromServer(filePath);

                if (fileData.error || typeof fileData.content !== 'string') {
                    editorPlaceholderEl.innerHTML = `<div class="p-4 text-red-600 bg-red-100 border border-red-300 rounded-md text-sm">Error loading ${fileName}: ${fileData.error || 'Invalid file content.'}</div>`;
                    setTimeout(() => { 
                        if (!activeTabId) setActiveTab(null);
                    }, 3000);
                    return; 
                }
                
                const decodedFileNameForMode = document.createElement('textarea');
                decodedFileNameForMode.innerHTML = fileName;
                const rawFileNameForMode = decodedFileNameForMode.value;
                let mode = "ace/mode/text";
                const ext = rawFileNameForMode.split('.').pop().toLowerCase();

                if (ext === 'php') mode = "ace/mode/php";
                else if (ext === 'js') mode = "ace/mode/javascript";
                else if (ext === 'css') mode = "ace/mode/css";
                else if (ext === 'html') mode = "ace/mode/html";
                else if (ext === 'md') mode = "ace/mode/markdown";
                else if (ext === 'json') mode = "ace/mode/json";
                else if (ext === 'ini') mode = "ace/mode/ini";

                const session = ace.createEditSession(fileData.content, mode); // fileData.content is raw
                session.setUndoManager(new ace.UndoManager());
                tab = { id: tabId, name: fileName, path: filePath, session: session, unsavedChanges: false };
                openTabs.push(tab);
                addTabToUI(tab);
            }
            setActiveTab(tabId);
        }

        function addTabToUI(tab) { // tab.name and tab.path are sanitized from server
            const tabEl = document.createElement('div');
            tabEl.id = tab.id;
            tabEl.className = 'tab flex items-center relative group text-sm'; 
            tabEl.title = tab.path;

            const nameSpan = document.createElement('span');
            nameSpan.textContent = tab.name; 
            nameSpan.className = 'tab-name'; 
            tabEl.appendChild(nameSpan);

            const unsavedIndicator = document.createElement('span');
            unsavedIndicator.innerHTML = '&nbsp;&#8226;'; 
            unsavedIndicator.className = 'unsaved-indicator text-blue-500 hidden ml-1';
            tabEl.appendChild(unsavedIndicator);

            const closeBtn = document.createElement('button');
            closeBtn.innerHTML = '<i class="fas fa-times text-xs"></i>';
            closeBtn.className = 'tab-close-btn opacity-50 group-hover:opacity-100 focus:opacity-100';
            closeBtn.title = 'Close tab';
            
            closeBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                closeTab(tab.id);
            });
            tabEl.appendChild(closeBtn);

            tabEl.addEventListener('click', () => setActiveTab(tab.id));
            tabBarEl.appendChild(tabEl);
        }
        
        function updateTabAppearance(tabId) {
            const tabEl = document.getElementById(tabId);
            const tabData = openTabs.find(t => t.id === tabId);
            if (tabEl && tabData) {
                tabEl.querySelector('.unsaved-indicator').classList.toggle('hidden', !tabData.unsavedChanges);
            }
        }

        function setActiveTab(tabId) {
             if (activeTabId === tabId && aceEditor.session === openTabs.find(t => t.id === tabId)?.session) {
                if (editorPlaceholderEl.style.display !== 'none') {
                     editorPlaceholderEl.style.display = 'none';
                     editorContainerEl.style.visibility = 'visible';
                }
                updateSaveButtonState();
                return;
            }
            
            activeTabId = tabId;
            Array.from(tabBarEl.children).forEach(el => {
                el.classList.toggle('active', el.id === tabId);
            });

            const activeTabData = openTabs.find(t => t.id === tabId);
            if (activeTabData) {
                aceEditor.setSession(activeTabData.session);
                aceEditor.focus();
                editorPlaceholderEl.style.display = 'none';
                editorContainerEl.style.visibility = 'visible';
            } else {
                aceEditor.setSession(ace.createEditSession('', "ace/mode/text"));
                editorPlaceholderEl.innerHTML = `
                    <div class="text-center">
                        <i class="fas fa-file-code fa-3x mb-2"></i>
                        <p>Select a file to view or edit.</p>
                        <p class="text-sm mt-1">Or navigate folders on the left.</p>
                    </div>`;
                editorPlaceholderEl.style.display = 'flex';
                editorContainerEl.style.visibility = 'hidden';
            }
            updateSaveButtonState();
        }

        function closeTab(tabIdToClose) {
            const tabToCloseData = openTabs.find(t => t.id === tabIdToClose);
            if(tabToCloseData && tabToCloseData.unsavedChanges) {
                if (!confirm(`File "${tabToCloseData.name}" has unsaved changes. Close anyway?`)) {
                    return;
                }
            }

            openTabs = openTabs.filter(t => t.id !== tabIdToClose);
            const tabEl = document.getElementById(tabIdToClose);
            if (tabEl) tabEl.remove();

            if (activeTabId === tabIdToClose) {
                activeTabId = null;
                if (openTabs.length > 0) {
                    setActiveTab(openTabs[openTabs.length - 1].id);
                } else {
                    setActiveTab(null);
                }
            }
        }

        // --- Initialization ---
        document.addEventListener('DOMContentLoaded', () => {
            initAceEditor();
            loadDirectory('/'); 
            if (backButton) { // Ensure elements exist before adding listeners
                backButton.addEventListener('click', goUpOneLevel);
            }
            if(saveButton) {
                saveButton.addEventListener('click', () => {
                    const activeTabData = openTabs.find(t => t.id === activeTabId);
                    if (activeTabData && activeTabData.unsavedChanges) {
                        const currentContent = activeTabData.session.getValue();
                        saveFileToServer(activeTabData.path, currentContent);
                    } else if (activeTabData && !activeTabData.unsavedChanges){
                        showNotification("No unsaved changes to save.", "info", 2000);
                    }
                });
            }
            setActiveTab(null); 
        });

    </script>
</body>
</html>
