<?php
// File: editor.php
// Purpose: Main application file for the Lit-based code editor.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KEBENET Dapo PHP Code Editor (Lit)</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Ace Editor (ensure this is loaded before your components try to use it) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.33.0/ace.js"></script>
    <style>
        body {
            font-family: 'Lato', sans-serif;
            margin: 0;
            height: 100vh;
            overflow: hidden; /* Prevent body scrollbars, managed by component */
        }
        /* Global styles for Ace if needed, or specific overrides */
        .ace_editor {
            border-radius: 0.375rem; /* Example: rounded-md */
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <code-editor-app></code-editor-app>
    <script type="module" src="/src/main.ts"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const saveButton = document.getElementById('save-button');

            if (saveButton) {
                // Dispatch event when navbar save button is clicked
                saveButton.addEventListener('click', () => {
                    console.log('Navbar save button clicked, dispatching save-file-triggered event.');
                    window.dispatchEvent(new CustomEvent('save-file-triggered', {
                        bubbles: true,
                        composed: true
                    }));
                });

                // Listen for state changes from the Lit component to update the navbar save button
                window.addEventListener('save-state-changed', (event) => {
                    console.log('Received save-state-changed event:', event.detail);
                    if (event.detail) {
                        saveButton.disabled = !event.detail.canSave;
                        saveButton.title = event.detail.saveTooltip || 'Save current file';
                    }
                });
            } else {
                console.warn('#save-button not found in navbar.php. Save functionality from navbar will not work.');
            }
        });
    </script>
</body>
</html>
