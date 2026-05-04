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
function rate_limiter($key, $limit, $period) {
    // Create a secure file name based on the key using SHA-256 hashing
    $filename = 'config/' . hash('sha256', $key) . '.txt';

    // Get the IP address of the client, handling proxy headers if present
    $ip = $_SERVER['REMOTE_ADDR'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }

    // Ensure the IP address is a valid IPv4 or IPv6 address
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)) {
        die('Error: Invalid IP address');
    }

    // Initialize the data array
    $data = array();

    // Check if the file exists and read its contents
    if (file_exists($filename)) {
        $data = json_decode(file_get_contents($filename), true);
    }

    // Get the current time and reset the count if the period has elapsed
    $current_time = time();
    if (isset($data[$ip]) && $current_time - $data[$ip]['last_access_time'] >= $period) {
        $data[$ip]['count'] = 0;
    }

    // Check if the limit has been exceeded
    if (isset($data[$ip]) && $data[$ip]['count'] >= $limit) {
        // Return an error message or redirect to an error page
        http_response_code(429);
        header('Retry-After: ' . $period);
        die('Error: Rate limit exceeded');
    }

    // Increment the count and save the data to the file
    if (!isset($data[$ip])) {
        $data[$ip] = array('count' => 0, 'last_access_time' => 0);
    }
    $data[$ip]['count']++;
    $data[$ip]['last_access_time'] = $current_time;
    file_put_contents($filename, json_encode($data));

    // Return the remaining time until the limit resets (in seconds)
    return $period - ($current_time - $data[$ip]['last_access_time']);
}
