<?php
// config/database.php
// Ganti nilai di bawah ini dengan kredensial Supabase kamu

define('DB_HOST', 'aws-1-ap-southeast-1.pooler.supabase.com'); // Host dari Supabase
define('DB_PORT', '6543');
define('DB_NAME', 'postgres');
define('DB_USER', 'postgres.qknthjqazdjyyyhrlpwv');   // Ganti dengan user Supabase kamu
define('DB_PASS', 'eBZ6shRyD2x&ei*');          // Ganti dengan password Supabase kamu

function getConnection() {
    try {
        $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            "meta" => [
                "status"    => 500,
                "message"   => "Koneksi database gagal: " . $e->getMessage(),
                "timestamp" => date('c')
            ],
            "data" => null
        ]);
        exit;
    }
}
