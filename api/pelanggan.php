<?php
// api/pelanggan.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';
require_once __DIR__ . '/../config/auth.php';

setCORSHeaders();

$pdo = getConnection();
validateApiKey($pdo); // ← Security: wajib ada API Key yang valid

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

switch ($method) {

    case 'GET':
        if ($id) {
            $stmt = $pdo->prepare("SELECT * FROM pelanggan WHERE id_pelanggan = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if ($row) sendResponse(200, "Data pelanggan ditemukan", $row);
            else sendResponse(404, "Pelanggan dengan ID $id tidak ditemukan");
        } else {
            $stmt = $pdo->query("SELECT * FROM pelanggan ORDER BY id_pelanggan ASC");
            sendResponse(200, "Daftar semua pelanggan", $stmt->fetchAll());
        }
        break;

    case 'POST':
        $input = json_decode(file_get_contents("php://input"), true);
        if (empty($input['nama']) || empty($input['nik']) || empty($input['no_hp']) || empty($input['alamat'])) {
            sendResponse(400, "Field nama, nik, no_hp, dan alamat wajib diisi");
        }
        $stmt = $pdo->prepare("
            INSERT INTO pelanggan (nama, nik, no_hp, alamat, email)
            VALUES (?, ?, ?, ?, ?)
            RETURNING *
        ");
        $stmt->execute([$input['nama'], $input['nik'], $input['no_hp'], $input['alamat'], $input['email'] ?? null]);
        sendResponse(201, "Pelanggan berhasil ditambahkan", $stmt->fetch());
        break;

    case 'PUT':
        if (!$id) sendResponse(400, "Parameter ID wajib disertakan");
        $input = json_decode(file_get_contents("php://input"), true);
        $check = $pdo->prepare("SELECT id_pelanggan FROM pelanggan WHERE id_pelanggan = ?");
        $check->execute([$id]);
        if (!$check->fetch()) sendResponse(404, "Pelanggan dengan ID $id tidak ditemukan");
        $stmt = $pdo->prepare("
            UPDATE pelanggan
            SET nama   = COALESCE(?, nama),
                nik    = COALESCE(?, nik),
                no_hp  = COALESCE(?, no_hp),
                alamat = COALESCE(?, alamat),
                email  = COALESCE(?, email)
            WHERE id_pelanggan = ?
            RETURNING *
        ");
        $stmt->execute([
            $input['nama'] ?? null, $input['nik'] ?? null,
            $input['no_hp'] ?? null, $input['alamat'] ?? null,
            $input['email'] ?? null, $id
        ]);
        sendResponse(200, "Data pelanggan berhasil diperbarui", $stmt->fetch());
        break;

    case 'DELETE':
        if (!$id) sendResponse(400, "Parameter ID wajib disertakan");
        $check = $pdo->prepare("SELECT id_pelanggan FROM pelanggan WHERE id_pelanggan = ?");
        $check->execute([$id]);
        if (!$check->fetch()) sendResponse(404, "Pelanggan dengan ID $id tidak ditemukan");
        $pdo->prepare("DELETE FROM pelanggan WHERE id_pelanggan = ?")->execute([$id]);
        sendResponse(200, "Pelanggan dengan ID $id berhasil dihapus");
        break;

    default:
        sendResponse(405, "Method tidak diizinkan");
}