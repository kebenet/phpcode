<?php


// Set a default timezone if not already set (good practice for any date/time functions)
if (!ini_get('date.timezone')) {
    // As your location is Kuala Lumpur, Selangor, Malaysia
    date_default_timezone_set('Asia/Kuala_Lumpur');
}

// --- Centralized Configuration ---
$configured_base_path = '/home/u765565545/domains/img.kebenet.com/'; 
define('BASE_DIRECTORY', realpath($configured_base_path));

// --- Initial Security Check & Setup ---
// Basic security: Ensure BASE_DIRECTORY is set and accessible
if (!BASE_DIRECTORY || !is_dir(BASE_DIRECTORY)) {
    // Log a more detailed error on the server for the administrator
    error_log("CRITICAL SERVER CONFIGURATION ERROR: BASE_DIRECTORY ('" . $configured_base_path . "') is not accessible or does not resolve correctly. Resolved to: " . (BASE_DIRECTORY ?: 'null/false'));
    
    // Send a generic error to the client
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Server configuration error. Please contact the administrator.']);
    exit;
}

// Common header for all API responses from scripts that include this config
header('Content-Type: application/json');

// You could also include other common utility functions here in the future if needed.
// For example, a function to sanitize input or validate paths more deeply, though
// path validation often needs context from the specific operation (read vs. write).
?>
