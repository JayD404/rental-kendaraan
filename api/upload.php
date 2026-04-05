<?php
// api/upload.php
//
// Endpoint khusus untuk upload/update gambar dan file (PDF)
// pada kendaraan yang sudah ada di database, berdasarkan ID.
//
// Cara pakai di Postman:
//   Method : POST
//   URL    : https://rental-kendaraan-alpha.vercel.app/api/upload.php?id=8
//   Body   : form-data
//              - gambar  → File  (opsional, jpg/jpeg/png/webp)
//              - file    → File  (opsional, pdf)
//
// Minimal kirim salah satu (gambar atau file atau keduanya).

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';
require_once __DIR__ . '/../config/supabase.php';

setCORSHeaders();

// Hanya izinkan POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(405, "Method tidak diizinkan. Gunakan POST.");
}

// Wajib ada parameter ?id=
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
if (!$id) {
    sendResponse(400, "Parameter 'id' wajib disertakan. Contoh: /api/upload.php?id=8");
}

// Cek apakah minimal ada satu file yang dikirim
$adaGambar = !empty($_FILES['gambar']['tmp_name']);
$adaFile   = !empty($_FILES['file']['tmp_name']);

if (!$adaGambar && !$adaFile) {
    sendResponse(400, "Minimal kirim satu file: 'gambar' (jpg/png) atau 'file' (pdf)");
}

// Ambil data kendaraan yang ada
$pdo   = getConnection();
$check = $pdo->prepare("SELECT * FROM kendaraan WHERE id_kendaraan = ?");
$check->execute([$id]);
$existing = $check->fetch();

if (!$existing) {
    sendResponse(404, "Kendaraan dengan ID $id tidak ditemukan");
}

// ─────────────────────────────────────────────
// Proses upload gambar (jika ada)
// ─────────────────────────────────────────────
$gambarUrl = $existing['gambar']; // default: tetap pakai yang lama

if ($adaGambar) {
    $file     = $_FILES['gambar'];
    $tmpPath  = $file['tmp_name'];
    $origName = $file['name'];
    $mimeType = $file['type'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        sendResponse(400, "Error saat upload gambar (kode: " . $file['error'] . ")");
    }

    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
        sendResponse(400, "Format gambar tidak didukung. Gunakan: jpg, jpeg, png, webp");
    }

    $fileName = 'gambar_' . $id . '_' . time() . '.' . $ext;
    $fileData = file_get_contents($tmpPath);
    $result   = uploadToSupabase($fileData, $fileName, $mimeType);

    if (isset($result['error'])) {
        sendResponse(500, "Gagal upload gambar ke Supabase: " . $result['error']);
    }

    $gambarUrl = $result['url'];
}

// ─────────────────────────────────────────────
// Proses upload file PDF (jika ada)
// ─────────────────────────────────────────────
$fileUrl = $existing['file']; // default: tetap pakai yang lama

if ($adaFile) {
    $file     = $_FILES['file'];
    $tmpPath  = $file['tmp_name'];
    $origName = $file['name'];
    $mimeType = $file['type'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        sendResponse(400, "Error saat upload file (kode: " . $file['error'] . ")");
    }

    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if (!in_array($ext, ['pdf'])) {
        sendResponse(400, "Format file tidak didukung. Gunakan: pdf");
    }

    $fileName = 'file_' . $id . '_' . time() . '.' . $ext;
    $fileData = file_get_contents($tmpPath);
    $result   = uploadToSupabase($fileData, $fileName, $mimeType);

    if (isset($result['error'])) {
        sendResponse(500, "Gagal upload file ke Supabase: " . $result['error']);
    }

    $fileUrl = $result['url'];
}

// ─────────────────────────────────────────────
// Simpan URL baru ke database
// ─────────────────────────────────────────────
$stmt = $pdo->prepare("
    UPDATE kendaraan
    SET gambar = ?,
        file   = ?
    WHERE id_kendaraan = ?
    RETURNING *
");
$stmt->execute([$gambarUrl, $fileUrl, $id]);
$updated = $stmt->fetch();

// Susun pesan response yang informatif
$uploaded = [];
if ($adaGambar) $uploaded[] = 'gambar';
if ($adaFile)   $uploaded[] = 'file';
$msg = implode(' dan ', $uploaded);

sendResponse(200, "Berhasil mengupload $msg untuk kendaraan ID $id", $updated);
