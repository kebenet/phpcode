<?php

require_once 'config.php'; 


$action = $_GET['action'] ?? '';
$path = $_GET['path'] ?? '/'; // Default to root of BASE_DIRECTORY

// Sanitize and resolve the path
// 1. Prepend BASE_DIRECTORY
// 2. Normalize the path (remove '.', '..')
// 3. Ensure the path is still within BASE_DIRECTORY
$requested_path_raw = BASE_DIRECTORY . '/' . trim($path, '/');
$resolved_path = realpath($requested_path_raw);

if (!$resolved_path || strpos($resolved_path, BASE_DIRECTORY) !== 0) {
    // Path is outside the BASE_DIRECTORY or invalid
    if ($action === 'list') {
        echo json_encode([
            'path' => $path,
            'parentPath' => dirname($path) === '.' ? '/' : dirname($path), // Basic parent path
            'items' => [],
            'error' => 'Access denied or path not found.'
        ]);
    } elseif ($action === 'get_content') {
        echo json_encode([
            'filePath' => $path,
            'content' => '',
            'error' => 'Access denied or file not found.'
        ]);
    }
    exit;
}

// Calculate relative path for client-side consistency
$relative_path_from_base = str_replace(BASE_DIRECTORY, '', $resolved_path);
if (empty($relative_path_from_base) || $relative_path_from_base[0] !== '/') {
    $relative_path_from_base = '/' . ltrim($relative_path_from_base, '/');
}
if ($relative_path_from_base === '') $relative_path_from_base = '/';


switch ($action) {
    case 'list':
        list_directory($resolved_path, $relative_path_from_base);
        break;
    case 'get_content':
        get_file_content($resolved_path, $relative_path_from_base);
        break;
    default:
        echo json_encode(['error' => 'Invalid action.']);
}

function list_directory($dir_path, $client_path) {

    $items = [];
    $files = scandir($dir_path);

    if ($files === false) {
        echo json_encode(['error' => 'Could not read directory.']);
        return;
    }

    $folders_list = [];
    $files_list = [];

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $item_path_full = $dir_path . '/' . $file;
        $item_path_client = rtrim($client_path, '/') . '/' . $file;

        $item_info = [
            'name' => $file,
            'path' => $item_path_client,
            // 'full_server_path_debug' => $item_path_full // For debugging only
        ];

        if (is_dir($item_path_full)) {
            $item_info['type'] = 'folder';
            $folders_list[] = $item_info;
        } else {
            $item_info['type'] = 'file';
            $files_list[] = $item_info;
        }
    }
    
    // Sort folders then files, alphabetically
    sort($folders_list);
    sort($files_list);
    $items = array_merge($folders_list, $files_list);

    // Calculate parent path for client
    $parent_client_path = '/';
    if ($client_path !== '/') {
        $path_parts = explode('/', trim($client_path, '/'));
        array_pop($path_parts);
        if (count($path_parts) > 0) {
            $parent_client_path = '/' . implode('/', $path_parts);
        }
    }
    
    echo json_encode([
        'path' => $client_path,
        'parentPath' => $client_path === '/' ? null : $parent_client_path,
        'items' => $items
    ]);
}

function get_file_content($file_path, $client_path) {
    if (is_file($file_path) && is_readable($file_path)) {
        $content = file_get_contents($file_path);
        if ($content === false) {
             echo json_encode(['filePath' => $client_path, 'content' => '', 'error' => 'Could not read file content.']);
        } else {
             echo json_encode(['filePath' => $client_path, 'content' => $content]);
        }
    } else {
        echo json_encode(['filePath' => $client_path, 'content' => '', 'error' => 'File not found or not readable.']);
    }
}
?>
