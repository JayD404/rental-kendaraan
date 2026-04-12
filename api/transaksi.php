<?php
// api/transaksi.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';
require_once __DIR__ . '/../config/auth.php';

setCORSHeaders();

$pdo = getConnection();
validateApiKey($pdo); // ← Security: wajib ada API Key yang valid

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

$joinQuery = "
    SELECT
        t.id_transaksi, t.tanggal_sewa, t.tanggal_kembali,
        t.durasi_hari, t.total_bayar, t.status_bayar, t.created_at,
        p.id_pelanggan, p.nama AS nama_pelanggan, p.no_hp AS no_hp_pelanggan,
        k.id_kendaraan, k.nama_kendaraan, k.plat_nomor, k.jenis, k.harga_sewa
    FROM transaksi t
    JOIN pelanggan p ON t.id_pelanggan = p.id_pelanggan
    JOIN kendaraan k ON t.id_kendaraan = k.id_kendaraan
";

switch ($method) {

    case 'GET':
        if ($id) {
            $stmt = $pdo->prepare($joinQuery . " WHERE t.id_transaksi = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if ($row) sendResponse(200, "Data transaksi ditemukan", $row);
            else sendResponse(404, "Transaksi dengan ID $id tidak ditemukan");
        } elseif (isset($_GET['id_pelanggan'])) {
            $stmt = $pdo->prepare($joinQuery . " WHERE t.id_pelanggan = ? ORDER BY t.id_transaksi ASC");
            $stmt->execute([(int)$_GET['id_pelanggan']]);
            sendResponse(200, "Transaksi milik pelanggan ID " . (int)$_GET['id_pelanggan'], $stmt->fetchAll());
        } else {
            $stmt = $pdo->query($joinQuery . " ORDER BY t.id_transaksi ASC");
            sendResponse(200, "Daftar semua transaksi", $stmt->fetchAll());
        }
        break;

    case 'POST':
        $input = json_decode(file_get_contents("php://input"), true);
        foreach (['id_pelanggan', 'id_kendaraan', 'tanggal_sewa', 'tanggal_kembali'] as $field) {
            if (empty($input[$field])) sendResponse(400, "Field '$field' wajib diisi");
        }

        $cekPelanggan = $pdo->prepare("SELECT id_pelanggan FROM pelanggan WHERE id_pelanggan = ?");
        $cekPelanggan->execute([$input['id_pelanggan']]);
        if (!$cekPelanggan->fetch()) sendResponse(404, "Pelanggan ID {$input['id_pelanggan']} tidak ditemukan");

        $cekKendaraan = $pdo->prepare("SELECT id_kendaraan, harga_sewa, status FROM kendaraan WHERE id_kendaraan = ?");
        $cekKendaraan->execute([$input['id_kendaraan']]);
        $kendaraan = $cekKendaraan->fetch();
        if (!$kendaraan) sendResponse(404, "Kendaraan ID {$input['id_kendaraan']} tidak ditemukan");
        if ($kendaraan['status'] !== 'tersedia') sendResponse(409, "Kendaraan tidak tersedia (status: {$kendaraan['status']})");

        $durasi = (new DateTime($input['tanggal_sewa']))->diff(new DateTime($input['tanggal_kembali']))->days;
        if ($durasi <= 0) sendResponse(400, "Tanggal kembali harus lebih besar dari tanggal sewa");
        $totalBayar = $durasi * $kendaraan['harga_sewa'];

        $stmt = $pdo->prepare("
            INSERT INTO transaksi (id_pelanggan, id_kendaraan, tanggal_sewa, tanggal_kembali, durasi_hari, total_bayar, status_bayar)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            RETURNING id_transaksi
        ");
        $stmt->execute([
            (int)$input['id_pelanggan'], (int)$input['id_kendaraan'],
            $input['tanggal_sewa'], $input['tanggal_kembali'],
            $durasi, $totalBayar, $input['status_bayar'] ?? 'belum lunas'
        ]);
        $newId = $stmt->fetchColumn();

        $pdo->prepare("UPDATE kendaraan SET status = 'disewa' WHERE id_kendaraan = ?")->execute([$input['id_kendaraan']]);

        $fetch = $pdo->prepare($joinQuery . " WHERE t.id_transaksi = ?");
        $fetch->execute([$newId]);
        sendResponse(201, "Transaksi berhasil dibuat", $fetch->fetch());
        break;

    case 'PUT':
        if (!$id) sendResponse(400, "Parameter ID wajib disertakan");
        $input = json_decode(file_get_contents("php://input"), true);
        $check = $pdo->prepare("SELECT id_transaksi FROM transaksi WHERE id_transaksi = ?");
        $check->execute([$id]);
        if (!$check->fetch()) sendResponse(404, "Transaksi dengan ID $id tidak ditemukan");

        $pdo->prepare("
            UPDATE transaksi
            SET tanggal_sewa    = COALESCE(?, tanggal_sewa),
                tanggal_kembali = COALESCE(?, tanggal_kembali),
                durasi_hari     = COALESCE(?, durasi_hari),
                total_bayar     = COALESCE(?, total_bayar),
                status_bayar    = COALESCE(?, status_bayar)
            WHERE id_transaksi = ?
        ")->execute([
            $input['tanggal_sewa'] ?? null, $input['tanggal_kembali'] ?? null,
            isset($input['durasi_hari']) ? (int)$input['durasi_hari'] : null,
            isset($input['total_bayar']) ? (int)$input['total_bayar'] : null,
            $input['status_bayar'] ?? null, $id
        ]);

        $fetch = $pdo->prepare($joinQuery . " WHERE t.id_transaksi = ?");
        $fetch->execute([$id]);
        sendResponse(200, "Transaksi berhasil diperbarui", $fetch->fetch());
        break;

    case 'DELETE':
        if (!$id) sendResponse(400, "Parameter ID wajib disertakan");
        $fetch = $pdo->prepare("SELECT id_kendaraan FROM transaksi WHERE id_transaksi = ?");
        $fetch->execute([$id]);
        $trx = $fetch->fetch();
        if (!$trx) sendResponse(404, "Transaksi dengan ID $id tidak ditemukan");

        $pdo->prepare("DELETE FROM transaksi WHERE id_transaksi = ?")->execute([$id]);
        $pdo->prepare("UPDATE kendaraan SET status = 'tersedia' WHERE id_kendaraan = ?")->execute([$trx['id_kendaraan']]);
        sendResponse(200, "Transaksi dengan ID $id berhasil dihapus");
        break;

    default:
        sendResponse(405, "Method tidak diizinkan");
}