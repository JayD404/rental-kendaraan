<?php
// api/kendaraan.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';

setCORSHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$pdo    = getConnection();
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

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
    // ─────────────────────────────────────────────
    case 'POST':
        $input = json_decode(file_get_contents("php://input"), true);

        $required = ['nama_kendaraan', 'jenis', 'merek', 'plat_nomor', 'tahun', 'harga_sewa'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                sendResponse(400, "Field '$field' wajib diisi");
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO kendaraan (nama_kendaraan, jenis, merek, plat_nomor, tahun, harga_sewa, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            RETURNING *
        ");
        $stmt->execute([
            $input['nama_kendaraan'],
            $input['jenis'],
            $input['merek'],
            $input['plat_nomor'],
            (int)$input['tahun'],
            (int)$input['harga_sewa'],
            $input['status'] ?? 'tersedia'
        ]);
        $new = $stmt->fetch();
        sendResponse(201, "Kendaraan berhasil ditambahkan", $new);
        break;

    // ─────────────────────────────────────────────
    // PUT: Update kendaraan berdasarkan ID
    // ─────────────────────────────────────────────
    case 'PUT':
        if (!$id) sendResponse(400, "Parameter ID wajib disertakan");

        $input = json_decode(file_get_contents("php://input"), true);

        $check = $pdo->prepare("SELECT id_kendaraan FROM kendaraan WHERE id_kendaraan = ?");
        $check->execute([$id]);
        if (!$check->fetch()) {
            sendResponse(404, "Kendaraan dengan ID $id tidak ditemukan");
        }

        $stmt = $pdo->prepare("
            UPDATE kendaraan
            SET nama_kendaraan = COALESCE(?, nama_kendaraan),
                jenis          = COALESCE(?, jenis),
                merek          = COALESCE(?, merek),
                plat_nomor     = COALESCE(?, plat_nomor),
                tahun          = COALESCE(?, tahun),
                harga_sewa     = COALESCE(?, harga_sewa),
                status         = COALESCE(?, status)
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
