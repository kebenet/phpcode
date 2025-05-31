# phpcode

**Simple code editor and preview for your PHP server.**

A lightweight, web-based code editor designed for quick edits and previews directly on your PHP-enabled server. Ideal for managing and tweaking HTML, CSS, JavaScript, and PHP files for your projects, including those for KEBENET Dapo.

---

## 🌟 Key Features

* **📝 In-Browser Code Editing**: Edit your HTML, CSS, JavaScript, and PHP files directly in your web browser.
* **🖼️ Live Preview Pane**: See changes to your static files (HTML, CSS, JS) rendered in a preview pane. For PHP files, previewing typically involves accessing the file via its URL on your server.
* **💾 Server-Side File Saving**: Securely save your files directly to your server within a designated directory.
* **📑 Tabbed Interface**: Work with multiple files simultaneously using a familiar tabbed layout.
* **🎨 Clean User Interface**: A straightforward and easy-to-use interface.

---

## 🛠️ Tech Stack (Conceptual)

* **Frontend**: HTML, CSS, JavaScript (potentially using Lit web components for a modern structure, and Font Awesome for icons).
* **Backend**: PHP (handles file operations like saving).

---

## 🚀 Setup & Installation

1.  **Prerequisites**:
    * A web server (Apache, Nginx, etc.) with PHP installed and configured to execute `.php` files.
    * Write permissions for the web server user on the intended file storage directory.

2.  **Download/Clone**:
    * Place the `phpcode` application files in a directory accessible via your web server (e.g., `/var/www/html/phpcode` or `htdocs/phpcode`).

3.  **Backend Configuration (Crucial!)**:
    * In the root directory of the `phpcode` application (where the main backend PHP script for saving files resides, e.g., `save_api.php`), you **must** create a `config.php` file.
    * Add the following content to `config.php`:

        ```php
        <?php
        // config.php

        /**
         * Define the absolute path to the directory where files will be saved and managed.
         * IMPORTANT:
         * 1. This directory MUST be writable by your web server user (e.g., www-data, apache).
         * 2. For security, this should ideally be an absolute path.
         * 3. Ensure this path does not expose sensitive system files.
         *
         * Examples:
         * define('BASE_DIRECTORY', '/var/www/my_user_files/phpcode_storage');
         * define('BASE_DIRECTORY', __DIR__ . '/data_files'); // Creates 'data_files' next to your main PHP script
         */
        define('BASE_DIRECTORY', '/path/to/your/writable/storage_folder_for_phpcode');

        // You can add other configurations here if needed in the future.
        ?>
        ```
    * **Replace `/path/to/your/writable/storage_folder_for_phpcode` with the actual, absolute path** on your server where `phpcode` should store and manage files.
    * Ensure the directory specified in `BASE_DIRECTORY` exists and has the correct write permissions for the web server user. For example:
        ```bash
        sudo mkdir -p /path/to/your/writable/storage_folder_for_phpcode
        sudo chown www-data:www-data /path/to/your/writable/storage_folder_for_phpcode # (use your server's user, e.g., apache)
        sudo chmod 755 /path/to/your/writable/storage_folder_for_phpcode # Or 775 if group write is needed and safe
        ```

4.  **Access the Application**:
    * Open your web browser and navigate to the URL where you placed `phpcode` (e.g., `http://yourdomain.com/phpcode/` or `http://localhost/phpcode/`).

---

## 💻 How to Use

1.  **Interface Overview**:
    * The main area will be the code editor.
    * A file explorer or tab bar will allow you to open, create, or switch between files.
    * A preview pane will display the output for static files or allow navigation to PHP scripts.

2.  **Opening Files**:
    * Use the file navigation to browse and open existing files from your `BASE_DIRECTORY`.

3.  **Creating New Files**:
    * Provide a file name (including subdirectories relative to `BASE_DIRECTORY`, e.g., `new_folder/my_page.php`).

4.  **Editing Code**:
    * Type your code in the editor pane for the active tab.
    * Unsaved changes will typically be indicated on the file's tab.

5.  **Saving Files**:
    * Click the "Save" button (or use a keyboard shortcut if available).
    * The file content will be sent to the backend PHP script and saved to the specified `filePath` within your `BASE_DIRECTORY`.

6.  **Previewing**:
    * **Static Files (HTML, CSS referenced by HTML)**: The preview pane should render the HTML content.
    * **PHP Files**: The "preview" for PHP files involves accessing them through your web server so PHP can execute them. The editor might provide a link to open the current PHP file's URL in a new browser tab or display it in an iframe sourced from its server URL.

---

## 🔒 Important Security Considerations

* **`BASE_DIRECTORY` Configuration**: The security of your server heavily relies on correctly configuring `BASE_DIRECTORY`. Ensure it points to a dedicated, safe location and is **not** your web root or a directory containing sensitive system/application files. The PHP save script is designed to prevent traversal outside this directory, but the initial configuration is key.
* **Server Permissions**: Restrict web server user permissions as much as possible. The user only needs write access to the `BASE_DIRECTORY`.
* **Exposure**: Be mindful that files created and edited via `phpcode` will be accessible via HTTP if `BASE_DIRECTORY` is within a web-accessible path. If you need to edit files not meant to be directly web-accessible, ensure `BASE_DIRECTORY` is outside your document root, and use `phpcode` as an administrative tool (though previewing non-web files becomes different).
* **Authentication**: This simple version of `phpcode` does not include user authentication. Anyone who can access the `phpcode` URL can edit files in your `BASE_DIRECTORY`. For use on a public or shared server, **you MUST implement an authentication mechanism** in front of `phpcode` (e.g., `.htaccess` password protection, or integrate a login system).

---

Enjoy using `phpcode` for your development tasks!
