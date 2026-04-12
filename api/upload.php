<?php
// api/upload.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/supabase.php';

setCORSHeaders();

$pdo = getConnection();
validateApiKey($pdo); // ← Security: wajib ada API Key yang valid

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(405, "Method tidak diizinkan. Gunakan POST.");
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
if (!$id) {
    sendResponse(400, "Parameter 'id' wajib disertakan. Contoh: /api/upload.php?id=8");
}

$adaGambar = !empty($_FILES['gambar']['tmp_name']);
$adaFile   = !empty($_FILES['file']['tmp_name']);

if (!$adaGambar && !$adaFile) {
    sendResponse(400, "Minimal kirim satu file: 'gambar' (jpg/png) atau 'file' (pdf)");
}

$check = $pdo->prepare("SELECT * FROM kendaraan WHERE id_kendaraan = ?");
$check->execute([$id]);
$existing = $check->fetch();
if (!$existing) sendResponse(404, "Kendaraan dengan ID $id tidak ditemukan");

$gambarUrl = $existing['gambar'];
if ($adaGambar) {
    $file     = $_FILES['gambar'];
    $tmpPath  = $file['tmp_name'];
    $origName = $file['name'];
    $mimeType = $file['type'];

    if ($file['error'] !== UPLOAD_ERR_OK) sendResponse(400, "Error saat upload gambar (kode: " . $file['error'] . ")");
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) sendResponse(400, "Format gambar tidak didukung. Gunakan: jpg, jpeg, png, webp");

    $fileName = 'gambar_' . $id . '_' . time() . '.' . $ext;
    $result   = uploadToSupabase(file_get_contents($tmpPath), $fileName, $mimeType);
    if (isset($result['error'])) sendResponse(500, "Gagal upload gambar: " . $result['error']);
    $gambarUrl = $result['url'];
}

$fileUrl = $existing['file'];
if ($adaFile) {
    $file     = $_FILES['file'];
    $tmpPath  = $file['tmp_name'];
    $origName = $file['name'];
    $mimeType = $file['type'];

    if ($file['error'] !== UPLOAD_ERR_OK) sendResponse(400, "Error saat upload file (kode: " . $file['error'] . ")");
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if (!in_array($ext, ['pdf'])) sendResponse(400, "Format file tidak didukung. Gunakan: pdf");

    $fileName = 'file_' . $id . '_' . time() . '.' . $ext;
    $result   = uploadToSupabase(file_get_contents($tmpPath), $fileName, $mimeType);
    if (isset($result['error'])) sendResponse(500, "Gagal upload file: " . $result['error']);
    $fileUrl = $result['url'];
}

$stmt = $pdo->prepare("UPDATE kendaraan SET gambar = ?, file = ? WHERE id_kendaraan = ? RETURNING *");
$stmt->execute([$gambarUrl, $fileUrl, $id]);
$updated = $stmt->fetch();

$uploaded = [];
if ($adaGambar) $uploaded[] = 'gambar';
if ($adaFile)   $uploaded[] = 'file';

sendResponse(200, "Berhasil mengupload " . implode(' dan ', $uploaded) . " untuk kendaraan ID $id", $updated);