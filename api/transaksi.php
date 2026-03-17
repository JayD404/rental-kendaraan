<?php
// api/transaksi.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/response.php';

setCORSHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$pdo    = getConnection();
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Query JOIN untuk tampil data lengkap
$joinQuery = "
    SELECT
        t.id_transaksi,
        t.tanggal_sewa,
        t.tanggal_kembali,
        t.durasi_hari,
        t.total_bayar,
        t.status_bayar,
        t.created_at,
        p.id_pelanggan,
        p.nama      AS nama_pelanggan,
        p.no_hp     AS no_hp_pelanggan,
        k.id_kendaraan,
        k.nama_kendaraan,
        k.plat_nomor,
        k.jenis,
        k.harga_sewa
    FROM transaksi t
    JOIN pelanggan  p ON t.id_pelanggan  = p.id_pelanggan
    JOIN kendaraan  k ON t.id_kendaraan  = k.id_kendaraan
";

switch ($method) {

    // ─────────────────────────────────────────────
    // GET: Semua transaksi (dengan JOIN) atau by ID
    // ─────────────────────────────────────────────
    case 'GET':
        if ($id) {
            $stmt = $pdo->prepare($joinQuery . " WHERE t.id_transaksi = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if ($row) {
                sendResponse(200, "Data transaksi ditemukan", $row);
            } else {
                sendResponse(404, "Transaksi dengan ID $id tidak ditemukan");
            }
        } elseif (isset($_GET['id_pelanggan'])) {
            $stmt = $pdo->prepare($joinQuery . " WHERE t.id_pelanggan = ? ORDER BY t.id_transaksi ASC");
            $stmt->execute([(int)$_GET['id_pelanggan']]);
            $rows = $stmt->fetchAll();
            sendResponse(200, "Transaksi milik pelanggan ID " . (int)$_GET['id_pelanggan'], $rows);
        } else {
            $stmt = $pdo->query($joinQuery . " ORDER BY t.id_transaksi ASC");
            $rows = $stmt->fetchAll();
            sendResponse(200, "Daftar semua transaksi", $rows);
        }
        break;

    // ─────────────────────────────────────────────
    // POST: Buat transaksi baru
    // ─────────────────────────────────────────────
    case 'POST':
        $input = json_decode(file_get_contents("php://input"), true);

        $required = ['id_pelanggan', 'id_kendaraan', 'tanggal_sewa', 'tanggal_kembali'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                sendResponse(400, "Field '$field' wajib diisi");
            }
        }

        // Validasi pelanggan ada
        $cekPelanggan = $pdo->prepare("SELECT id_pelanggan FROM pelanggan WHERE id_pelanggan = ?");
        $cekPelanggan->execute([$input['id_pelanggan']]);
        if (!$cekPelanggan->fetch()) {
            sendResponse(404, "Pelanggan dengan ID {$input['id_pelanggan']} tidak ditemukan");
        }

        // Validasi kendaraan ada dan tersedia
        $cekKendaraan = $pdo->prepare("SELECT id_kendaraan, harga_sewa, status FROM kendaraan WHERE id_kendaraan = ?");
        $cekKendaraan->execute([$input['id_kendaraan']]);
        $kendaraan = $cekKendaraan->fetch();
        if (!$kendaraan) {
            sendResponse(404, "Kendaraan dengan ID {$input['id_kendaraan']} tidak ditemukan");
        }
        if ($kendaraan['status'] !== 'tersedia') {
            sendResponse(409, "Kendaraan tidak tersedia saat ini (status: {$kendaraan['status']})");
        }

        // Hitung durasi dan total
        $tglSewa    = new DateTime($input['tanggal_sewa']);
        $tglKembali = new DateTime($input['tanggal_kembali']);
        $durasi     = $tglSewa->diff($tglKembali)->days;
        if ($durasi <= 0) {
            sendResponse(400, "Tanggal kembali harus lebih besar dari tanggal sewa");
        }
        $totalBayar = $durasi * $kendaraan['harga_sewa'];

        // Simpan transaksi
        $stmt = $pdo->prepare("
            INSERT INTO transaksi (id_pelanggan, id_kendaraan, tanggal_sewa, tanggal_kembali, durasi_hari, total_bayar, status_bayar)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            RETURNING id_transaksi
        ");
        $stmt->execute([
            (int)$input['id_pelanggan'],
            (int)$input['id_kendaraan'],
            $input['tanggal_sewa'],
            $input['tanggal_kembali'],
            $durasi,
            $totalBayar,
            $input['status_bayar'] ?? 'belum lunas'
        ]);
        $newId = $stmt->fetchColumn();

        // Update status kendaraan
        $pdo->prepare("UPDATE kendaraan SET status = 'disewa' WHERE id_kendaraan = ?")
            ->execute([$input['id_kendaraan']]);

        // Kembalikan data lengkap dengan JOIN
        $fetch = $pdo->prepare($joinQuery . " WHERE t.id_transaksi = ?");
        $fetch->execute([$newId]);
        sendResponse(201, "Transaksi berhasil dibuat", $fetch->fetch());
        break;

    // ─────────────────────────────────────────────
    // PUT: Update transaksi berdasarkan ID
    // ─────────────────────────────────────────────
    case 'PUT':
        if (!$id) sendResponse(400, "Parameter ID wajib disertakan");

        $input = json_decode(file_get_contents("php://input"), true);

        $check = $pdo->prepare("SELECT id_transaksi FROM transaksi WHERE id_transaksi = ?");
        $check->execute([$id]);
        if (!$check->fetch()) {
            sendResponse(404, "Transaksi dengan ID $id tidak ditemukan");
        }

        $stmt = $pdo->prepare("
            UPDATE transaksi
            SET tanggal_sewa    = COALESCE(?, tanggal_sewa),
                tanggal_kembali = COALESCE(?, tanggal_kembali),
                durasi_hari     = COALESCE(?, durasi_hari),
                total_bayar     = COALESCE(?, total_bayar),
                status_bayar    = COALESCE(?, status_bayar)
            WHERE id_transaksi = ?
            RETURNING *
        ");
        $stmt->execute([
            $input['tanggal_sewa']    ?? null,
            $input['tanggal_kembali'] ?? null,
            isset($input['durasi_hari'])  ? (int)$input['durasi_hari']  : null,
            isset($input['total_bayar'])  ? (int)$input['total_bayar']  : null,
            $input['status_bayar']    ?? null,
            $id
        ]);
        $updated = $stmt->fetch();
        sendResponse(200, "Transaksi berhasil diperbarui", $updated);
        break;

    // ─────────────────────────────────────────────
    // DELETE: Hapus transaksi berdasarkan ID
    // ─────────────────────────────────────────────
    case 'DELETE':
        if (!$id) sendResponse(400, "Parameter ID wajib disertakan");

        // Ambil id_kendaraan dulu sebelum hapus
        $fetch = $pdo->prepare("SELECT id_kendaraan FROM transaksi WHERE id_transaksi = ?");
        $fetch->execute([$id]);
        $trx = $fetch->fetch();
        if (!$trx) {
            sendResponse(404, "Transaksi dengan ID $id tidak ditemukan");
        }

        $pdo->prepare("DELETE FROM transaksi WHERE id_transaksi = ?")->execute([$id]);

        // Kembalikan status kendaraan jadi tersedia
        $pdo->prepare("UPDATE kendaraan SET status = 'tersedia' WHERE id_kendaraan = ?")
            ->execute([$trx['id_kendaraan']]);

        sendResponse(200, "Transaksi dengan ID $id berhasil dihapus");
        break;

    default:
        sendResponse(405, "Method tidak diizinkan");
}
