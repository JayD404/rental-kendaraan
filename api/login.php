<?php
// api/login.php
// ─────────────────────────────────────────────────────────────────────────────
// Endpoint PUBLIK (tidak perlu API Key) untuk login dan mendapatkan key_token.
//
// POST /api/login.php
// Body raw JSON:
// {
//   "nama"     : "admin",
//   "password" : "admin123"
// }
// ─────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(405, "Method tidak diizinkan. Gunakan POST.");
}

$input = json_decode(file_get_contents("php://input"), true);

if (empty($input['nama']) || empty($input['password'])) {
    sendResponse(400, "Field 'nama' dan 'password' wajib diisi");
}

$pdo = getConnection();

// Password disimpan sebagai MD5 di database (sesuai modul praktikum)
$stmt = $pdo->prepare("SELECT id_user, nama, key_token FROM admin WHERE nama = ? AND password = MD5(?) LIMIT 1");
$stmt->execute([trim($input['nama']), trim($input['password'])]);
$admin = $stmt->fetch();

if (!$admin) {
    sendResponse(401, "Nama atau password salah");
}

sendResponse(200, "Login berhasil. Gunakan key_token sebagai header 'X-API-Key'.", [
    'id_user'   => $admin['id_user'],
    'nama'      => $admin['nama'],
    'key_token' => $admin['key_token'],
]);