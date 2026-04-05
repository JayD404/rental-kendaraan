<?php
// api/kendaraan.php
require_once '../config/database.php';
require_once '../config/response.php';
require_once '../config/supabase.php';

setCORSHeaders();
$method = $_SERVER['REQUEST_METHOD'];
$pdo    = getConnection();
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

// ─────────────────────────────────────────────
// Helper: Upload file ke Supabase Storage via base64
// ─────────────────────────────────────────────
function uploadToSupabase($fileInput, $allowedMimes, $folder) {
    if (!isset($_FILES[$fileInput]) || $_FILES[$fileInput]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $file     = $_FILES[$fileInput];
    $mimeType = mime_content_type($file['tmp_name']);

    if (!in_array($mimeType, $allowedMimes)) {
        sendResponse(400, "Tipe file '$fileInput' tidak diizinkan. Allowed: " . implode(', ', $allowedMimes));
    }

    // Baca file -> encode base64 -> decode kembali ke binary untuk upload
    $rawContent = file_get_contents($file['tmp_name']);
    $base64     = base64_encode($rawContent);
    $binary     = base64_decode($base64);

    // Generate nama file unik
    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $folder . '/' . $folder . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

    // Upload ke Supabase Storage via REST API
    $url = SUPABASE_URL . '/storage/v1/object/' . SUPABASE_BUCKET . '/' . $filename;

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_POSTFIELDS     => $binary,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . SUPABASE_KEY,
            'Content-Type: ' . $mimeType,
            'x-upsert: true'
        ]
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        sendResponse(500, "Gagal upload '$fileInput' ke Supabase Storage: " . $response);
    }

    return SUPABASE_URL . '/storage/v1/object/public/' . SUPABASE_BUCKET . '/' . $filename;
}

// ─────────────────────────────────────────────
// Helper: Hapus file dari Supabase Storage
// ─────────────────────────────────────────────
function deleteFromSupabase($publicUrl) {
    if (!$publicUrl) return;

    $prefix = SUPABASE_URL . '/storage/v1/object/public/' . SUPABASE_BUCKET . '/';
    if (strpos($publicUrl, $prefix) !== 0) return;

    $filePath = substr($publicUrl, strlen($prefix));
    $url = SUPABASE_URL . '/storage/v1/object/' . SUPABASE_BUCKET . '/' . $filePath;

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'DELETE',
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . SUPABASE_KEY,
        ]
    ]);
    curl_exec($ch);
    curl_close($ch);
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
    // POST: Tambah kendaraan baru (form-data atau JSON)
    // ─────────────────────────────────────────────
    case 'POST':
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (strpos($contentType, 'multipart/form-data') !== false) {
            $input = $_POST;
        } else {
            $input = json_decode(file_get_contents("php://input"), true);
        }

        $required = ['nama_kendaraan', 'jenis', 'merek', 'plat_nomor', 'tahun', 'harga_sewa'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                sendResponse(400, "Field '$field' wajib diisi");
            }
        }

        // Upload gambar & file spesifikasi ke Supabase via base64
        $gambarUrl = uploadToSupabase('gambar', [
            'image/jpeg', 'image/png', 'image/webp', 'image/gif'
        ], 'gambar');

        $fileUrl = uploadToSupabase('file', [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ], 'spesifikasi');

        $stmt = $pdo->prepare("
            INSERT INTO kendaraan (nama_kendaraan, jenis, merek, plat_nomor, tahun, harga_sewa, status, gambar_url, file_url)
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
            $input = json_decode(file_get_contents("php://input"), true);
        }

        // Upload file baru jika ada
        $gambarUrl = uploadToSupabase('gambar', [
            'image/jpeg', 'image/png', 'image/webp', 'image/gif'
        ], 'gambar');

        $fileUrl = uploadToSupabase('file', [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ], 'spesifikasi');

        // Hapus file lama dari Supabase jika ada file baru
        if ($gambarUrl && !empty($existing['gambar_url'])) {
            deleteFromSupabase($existing['gambar_url']);
        }
        if ($fileUrl && !empty($existing['file_url'])) {
            deleteFromSupabase($existing['file_url']);
        }

        $stmt = $pdo->prepare("
            UPDATE kendaraan
            SET nama_kendaraan = COALESCE(?, nama_kendaraan),
                jenis          = COALESCE(?, jenis),
                merek          = COALESCE(?, merek),
                plat_nomor     = COALESCE(?, plat_nomor),
                tahun          = COALESCE(?, tahun),
                harga_sewa     = COALESCE(?, harga_sewa),
                status         = COALESCE(?, status),
                gambar_url     = COALESCE(?, gambar_url),
                file_url       = COALESCE(?, file_url)
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

        $check = $pdo->prepare("SELECT * FROM kendaraan WHERE id_kendaraan = ?");
        $check->execute([$id]);
        $existing = $check->fetch();
        if (!$existing) {
            sendResponse(404, "Kendaraan dengan ID $id tidak ditemukan");
        }

        // Hapus file dari Supabase Storage
        deleteFromSupabase($existing['gambar_url'] ?? null);
        deleteFromSupabase($existing['file_url'] ?? null);

        $stmt = $pdo->prepare("DELETE FROM kendaraan WHERE id_kendaraan = ?");
        $stmt->execute([$id]);
        sendResponse(200, "Kendaraan dengan ID $id berhasil dihapus");
        break;

    default:
        sendResponse(405, "Method tidak diizinkan");
}
