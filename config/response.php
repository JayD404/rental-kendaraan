<?php
// config/response.php

function sendResponse($status, $message, $data = null) {
    http_response_code($status);
    echo json_encode([
        "meta" => [
            "status"    => $status,
            "message"   => $message,
            "timestamp" => date('c')
        ],
        "data" => $data
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

function setCORSHeaders() {
    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json; charset=UTF-8");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    // Tambah X-API-Key dan key agar Postman bisa kirim header ini
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, key");
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}