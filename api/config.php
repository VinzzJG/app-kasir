<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

// Cek login via session
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function hasRole($allowed_roles) {
    if (!isLoggedIn()) return false;
    return in_array($_SESSION['role'], $allowed_roles);
}

function sendResponse($success, $message, $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => time()
    ]);
    exit();
}

function sendError($message, $code = 400) {
    http_response_code($code);
    sendResponse(false, $message);
}

function getPostData() {
    return json_decode(file_get_contents('php://input'), true);
}
?>