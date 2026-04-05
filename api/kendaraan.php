<?php
// api/kendaraan.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';
require_once __DIR__ . '/../config/supabase.php'; // ← tambahan untuk upload file

setCORSHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$pdo    = getConnection();
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

// ─────────────────────────────────────────────────────────
// Helper: proses upload file dari $_FILES ke Supabase
// Mengembalikan public URL atau null jika tidak ada file
// ─────────────────────────────────────────────────────────
function handleFileUpload(string $fieldName, string $prefix): ?string {
    if (empty($_FILES[$fieldName]['tmp_name'])) {
        return null; // tidak ada file yang dikirim, skip
    }

    $file     = $_FILES[$fieldName];
    $tmpPath  = $file['tmp_name'];
    $origName = $file['name'];
    $mimeType = $file['type'];
    $error    = $file['error'];

    if ($error !== UPLOAD_ERR_OK) {
        sendResponse(400, "Error upload file '$fieldName' (kode: $error)");
    }

    // Validasi ekstensi untuk keamanan
    $ext           = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    $allowedGambar = ['jpg', 'jpeg', 'png', 'webp'];
    $allowedFile   = ['pdf'];

    if ($prefix === 'gambar' && !in_array($ext, $allowedGambar)) {
        sendResponse(400, "Format gambar tidak didukung. Gunakan: jpg, jpeg, png, webp");
    }
    if ($prefix === 'file' && !in_array($ext, $allowedFile)) {
        sendResponse(400, "Format file tidak didukung. Gunakan: pdf");
    }

    // Buat nama file unik supaya tidak tabrakan di bucket
    $fileName = $prefix . '_' . time() . '_' . uniqid() . '.' . $ext;

    // Baca binary content lalu upload
    $fileData = file_get_contents($tmpPath);
    $result   = uploadToSupabase($fileData, $fileName, $mimeType);

    if (isset($result['error'])) {
        sendResponse(500, "Gagal upload $prefix: " . $result['error']);
    }

    return $result['url'];
}

switch ($method) {

    // ─────────────────────────────────────────────
    // GET: Ambil semua kendaraan atau by ID
    //      ?status=tersedia untuk filter status
    // ─────────────────────────────────────────────
    case 'GET':
        if ($id) {
            $stmt = $pdo->prepare("SELECT * FROM kendaraan WHERE id_kendaraan = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if ($row) {
                sendResponse(200, "Data kendaraan ditemukan", $row);
            } else {
                sendResponse(404, "Kendaraan dengan ID $id tidak ditemukan");
            }
        } elseif (isset($_GET['status'])) {
            $status = $_GET['status'];
            $stmt = $pdo->prepare("SELECT * FROM kendaraan WHERE status = ? ORDER BY id_kendaraan ASC");
            $stmt->execute([$status]);
            $rows = $stmt->fetchAll();
            sendResponse(200, "Kendaraan dengan status: $status", $rows);
        } else {
            $stmt = $pdo->query("SELECT * FROM kendaraan ORDER BY id_kendaraan ASC");
            $rows = $stmt->fetchAll();
            sendResponse(200, "Daftar semua kendaraan", $rows);
        }
        break;

    // ─────────────────────────────────────────────
    // POST: Tambah kendaraan baru
    //       Mendukung multipart/form-data (Postman)
    //       Field file: 'gambar' (jpg/png) dan 'file' (pdf)
    // ─────────────────────────────────────────────
    case 'POST':
        // form-data dari Postman → pakai $_POST
        // JSON body → pakai php://input
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'multipart/form-data') !== false) {
            $input = $_POST;
        } else {
            $input = json_decode(file_get_contents("php://input"), true) ?? [];
        }

        $required = ['nama_kendaraan', 'jenis', 'merek', 'plat_nomor', 'tahun', 'harga_sewa'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                sendResponse(400, "Field '$field' wajib diisi");
            }
        }

        // Upload gambar dan file ke Supabase (jika ada)
        $gambarUrl = handleFileUpload('gambar', 'gambar'); // nullable
        $fileUrl   = handleFileUpload('file', 'file');     // nullable

        $stmt = $pdo->prepare("
            INSERT INTO kendaraan (nama_kendaraan, jenis, merek, plat_nomor, tahun, harga_sewa, status, gambar, file)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            RETURNING *
        ");
        $stmt->execute([
            $input['nama_kendaraan'],
            $input['jenis'],
            $input['merek'],
            $input['plat_nomor'],
            (int)$input['tahun'],
            (int)$input['harga_sewa'],
            $input['status'] ?? 'tersedia',
            $gambarUrl,
            $fileUrl
        ]);
        $new = $stmt->fetch();
        sendResponse(201, "Kendaraan berhasil ditambahkan", $new);
        break;

    // ─────────────────────────────────────────────
    // PUT: Update kendaraan berdasarkan ID
    //      Mendukung multipart/form-data untuk update gambar/file
    // ─────────────────────────────────────────────
    case 'PUT':
        if (!$id) sendResponse(400, "Parameter ID wajib disertakan");

        $check = $pdo->prepare("SELECT * FROM kendaraan WHERE id_kendaraan = ?");
        $check->execute([$id]);
        $existing = $check->fetch();
        if (!$existing) {
            sendResponse(404, "Kendaraan dengan ID $id tidak ditemukan");
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'multipart/form-data') !== false) {
            $input = $_POST;
        } else {
            $input = json_decode(file_get_contents("php://input"), true) ?? [];
        }

        // Upload file baru jika ada, kalau tidak pakai URL yang sudah ada
        $gambarUrl = handleFileUpload('gambar', 'gambar') ?? ($input['gambar'] ?? $existing['gambar']);
        $fileUrl   = handleFileUpload('file', 'file')     ?? ($input['file']   ?? $existing['file']);

        $stmt = $pdo->prepare("
            UPDATE kendaraan
            SET nama_kendaraan = COALESCE(?, nama_kendaraan),
                jenis          = COALESCE(?, jenis),
                merek          = COALESCE(?, merek),
                plat_nomor     = COALESCE(?, plat_nomor),
                tahun          = COALESCE(?, tahun),
                harga_sewa     = COALESCE(?, harga_sewa),
                status         = COALESCE(?, status),
                gambar         = ?,
                file           = ?
            WHERE id_kendaraan = ?
            RETURNING *
        ");
        $stmt->execute([
            $input['nama_kendaraan'] ?? null,
            $input['jenis']          ?? null,
            $input['merek']          ?? null,
            $input['plat_nomor']     ?? null,
            isset($input['tahun'])      ? (int)$input['tahun']      : null,
            isset($input['harga_sewa']) ? (int)$input['harga_sewa'] : null,
            $input['status']         ?? null,
            $gambarUrl,
            $fileUrl,
            $id
        ]);
        $updated = $stmt->fetch();
        sendResponse(200, "Data kendaraan berhasil diperbarui", $updated);
        break;

    // ─────────────────────────────────────────────
    // DELETE: Hapus kendaraan berdasarkan ID
    // ─────────────────────────────────────────────
    case 'DELETE':
        if (!$id) sendResponse(400, "Parameter ID wajib disertakan");

        $check = $pdo->prepare("SELECT id_kendaraan FROM kendaraan WHERE id_kendaraan = ?");
        $check->execute([$id]);
        if (!$check->fetch()) {
            sendResponse(404, "Kendaraan dengan ID $id tidak ditemukan");
        }

        $stmt = $pdo->prepare("DELETE FROM kendaraan WHERE id_kendaraan = ?");
        $stmt->execute([$id]);
        sendResponse(200, "Kendaraan dengan ID $id berhasil dihapus");
        break;

    default:
        sendResponse(405, "Method tidak diizinkan");
}
