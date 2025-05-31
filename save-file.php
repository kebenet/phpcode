<?php

// Attempt to load configuration. Crucial for defining BASE_DIRECTORY.
if (file_exists('config.php')) {
    require_once 'config.php';
} else {
    // Fallback or error if config.php is essential and not found
    // For this script, BASE_DIRECTORY is essential.
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: application/json');
    error_log("Critical: config.php not found.");
    echo json_encode(['error' => 'Server configuration error: Main configuration file missing.']);
    exit;
}

// --- FileWriter Class and IOException Definition ---
if (!class_exists('IOException')) {
    class IOException extends Exception {}
}

class FileWriter
{
    /**
     * Checks if the given path is suitable for writing.
     * If $path is a file path and the file doesn't exist, it checks the directory.
     * Attempts to create the directory if it does not exist.
     *
     * @param string $path The path to check (can be a file or directory path).
     * @throws IOException If the path is not writable or directory cannot be created.
     */
    public static function checkWrite(string $path): void
    {
        $checkPath = $path;
        // If the path is for a file that doesn't exist yet, or if it's explicitly a directory check,
        // we need to ensure the parent directory exists and is writable.
        if (!file_exists($path) || !is_dir($path)) {
            $checkPath = dirname($path);
        }

        // Ensure the directory exists
        if (!is_dir($checkPath)) {
            // Try to create it recursively with 0775 permissions
            // @ suppresses errors from mkdir, we check its return value
            if (!@mkdir($checkPath, 0775, true) && !is_dir($checkPath)) { // Check again if mkdir failed
                throw new IOException(
                    "Directory \"$checkPath\" could not be created. Please check permissions."
                );
            }
        }

        // Check if PHP has write permission for the directory
        if (!is_writable($checkPath)) {
            throw new IOException(
                "Directory is not writable at \"$checkPath\". Please check permissions."
            );
        }
    }

    /**
     * Writes content to a file.
     *
     * @param string $filePath The full path to the file.
     * @param string $content The content to write.
     * @throws IOException If writing to the file fails or directory is not writable.
     */
    public static function writeContentToFile(string $filePath, string $content): void
    {
        // self::checkWrite will ensure the directory exists and is writable.
        self::checkWrite($filePath);

        // file_put_contents will create the file if it doesn't exist.
        // LOCK_EX for exclusive lock during writing.
        if (@file_put_contents($filePath, $content, LOCK_EX) === false) {
            // Construct a more specific error if possible
            $error = error_get_last();
            $errorMessage = $error ? $error['message'] : 'unknown reason';
            throw new IOException("Could not write content to file at \"$filePath\". Reason: $errorMessage");
        }
    }
}
// --- End FileWriter Class ---

// --- Main script logic ---

header('Content-Type: application/json'); // Ensure JSON output for API-like behavior

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['error' => 'Invalid request method. Only POST is allowed for saving.']);
    exit;
}

$content_to_save = $_POST['content'] ?? null;
$file_path_client = $_POST['filePath'] ?? null; // e.g., "myfolder/myfile.html" or "myfile.txt"

if ($content_to_save === null || $file_path_client === null) {
    echo json_encode(['error' => 'Missing "filePath" or "content" parameters.']);
    exit;
}

// --- Path Validation (Crucial for Security) ---
if (!defined('BASE_DIRECTORY') || empty(BASE_DIRECTORY)) {
    error_log("Critical: BASE_DIRECTORY is not defined or is empty in config.php.");
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Server configuration error: Base directory not configured.']);
    exit;
}

// 1. Resolve BASE_DIRECTORY to an absolute, canonical path.
$resolved_base_path = realpath(BASE_DIRECTORY);
if ($resolved_base_path === false) {
    error_log("Critical: BASE_DIRECTORY ('" . BASE_DIRECTORY . "') in config.php is not a valid, existing directory.");
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Server configuration error: Base directory path is invalid.']);
    exit;
}
$resolved_base_path = rtrim($resolved_base_path, DIRECTORY_SEPARATOR); // Ensure no trailing slash for now

// 2. Clean and prepare the client-provided path.
// Remove leading/trailing slashes and backslashes to prevent issues like '//file.txt' or '/../'.
$client_path_sanitized = trim($file_path_client, '/\\');
if (empty($client_path_sanitized)) {
    echo json_encode(['error' => 'File path cannot be empty or consist only of slashes.']);
    exit;
}
// Disallow hidden files or directories (names starting with a dot) at any point in the client path for added safety.
if (preg_match('/(?:^|\/|\\\\)\.[^\/\\\\]+/', $client_path_sanitized)) {
    echo json_encode(['error' => 'File or directory names starting with a dot are not allowed.']);
    exit;
}


// 3. Construct the full intended path by appending sanitized client path to resolved base path.
$intended_full_path = $resolved_base_path . DIRECTORY_SEPARATOR . $client_path_sanitized;

// 4. Lexically normalize the intended full path to resolve '..' and '.' segments.
// This creates a canonical version of the path *before* checking against the base directory.
$path_segments = explode(DIRECTORY_SEPARATOR, $intended_full_path);
$normalized_segments = [];
foreach ($path_segments as $segment) {
    if ($segment === '.' || $segment === '') { // Handle '.' and empty segments (from multiple slashes)
        continue;
    }
    if ($segment === '..') {
        if (count($normalized_segments) > 0) {
            // Basic pop. More advanced would be to ensure we don't pop parts of $resolved_base_path,
            // but the strpos check below is the primary safeguard.
            array_pop($normalized_segments);
        }
        // If $normalized_segments is empty, '..' at the start of a relative path or trying to go above root.
        // e.g. /../../file -> /file. This is fine as strpos check will catch it if it leaves base.
    } else {
        $normalized_segments[] = $segment;
    }
}
$final_canonical_path = implode(DIRECTORY_SEPARATOR, $normalized_segments);

// For absolute paths on Unix-like systems, ensure the leading slash if it was there and got lost by explode.
if (strpos($intended_full_path, DIRECTORY_SEPARATOR) === 0 && strpos($final_canonical_path, DIRECTORY_SEPARATOR) !== 0) {
    $final_canonical_path = DIRECTORY_SEPARATOR . $final_canonical_path;
}
// On Windows, if $intended_full_path started C:\ and $final_canonical_path lost it (e.g. C:\..\foo -> foo)
// then $final_canonical_path might be like "foo". `strpos` check will fail correctly if base was C:\base_dir.

// 5. Security Check: The final canonical path MUST start with the resolved base path.
// This prevents directory traversal.
if (strpos($final_canonical_path, $resolved_base_path . DIRECTORY_SEPARATOR) !== 0 && $final_canonical_path !== $resolved_base_path) {
     // The second condition ($final_canonical_path !== $resolved_base_path) is for the edge case where client_path is empty
     // or refers to the base directory itself, which could be valid for listing but not for writing a file named as the directory.
     // However, empty client_path_sanitized is checked earlier.
    error_log(
        "Path validation failed: Canonical path '$final_canonical_path' is outside BASE_DIRECTORY '$resolved_base_path'." .
        " Client path: '$file_path_client', Intended full path: '$intended_full_path'"
    );
    echo json_encode(['error' => 'Access denied. Target path is outside the allowed base directory.']);
    exit;
}

// 6. Validate the final basename.
$target_basename = basename($final_canonical_path);
if ($target_basename === '.' || $target_basename === '..') {
    echo json_encode(['error' => 'Invalid file name (cannot be "." or "..").']);
    exit;
}
if (empty($target_basename) || $final_canonical_path === $resolved_base_path) {
    // This means the path effectively points to the base directory itself, not a file within it.
    echo json_encode(['error' => 'Invalid file path: must specify a filename.']);
    exit;
}

// 7. Check if the target path already exists and is a directory (we can't overwrite a directory with a file).
// This check is done before FileWriter attempts to create directories, as FileWriter::checkWrite
// would target dirname($final_canonical_path) if $final_canonical_path itself doesn't exist.
if (is_dir($final_canonical_path)) {
    echo json_encode(['error' => 'Cannot save file: path points to an existing directory.']);
    exit;
}
// --- End Path Validation ---

// At this point, $final_canonical_path is considered safe to use.
try {
    FileWriter::writeContentToFile($final_canonical_path, $content_to_save);
    echo json_encode([
        'success'   => true,
        'message'   => 'File saved successfully.',
        'filePath'  => $file_path_client, // Return the original client path for reference
        'savedPath' => $final_canonical_path // Return the actual server path where it was saved
    ]);
} catch (IOException $e) {
    error_log("FileWriter IOException for path '$final_canonical_path': " . $e->getMessage());
    echo json_encode(['error' => 'Failed to save file: ' . $e->getMessage()]);
} catch (Exception $e) { // Catch any other unexpected errors
    error_log("Unexpected error during file save for path '$final_canonical_path': " . $e->getMessage());
    // Be cautious about echoing $e->getMessage() from unexpected exceptions to the client.
    echo json_encode(['error' => 'An unexpected server error occurred while saving the file.']);
}

?>
