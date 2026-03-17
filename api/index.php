<?php
// index.php - Halaman utama dashboard Rental Kendaraan
// Data diambil dari API sendiri secara server-side
require_once __DIR__ . '/../config/database.php';
$pdo = getConnection();

// Statistik ringkas
$totalPelanggan  = $pdo->query("SELECT COUNT(*) FROM pelanggan")->fetchColumn();
$totalKendaraan  = $pdo->query("SELECT COUNT(*) FROM kendaraan")->fetchColumn();
$totalTransaksi  = $pdo->query("SELECT COUNT(*) FROM transaksi")->fetchColumn();
$totalPendapatan = $pdo->query("SELECT COALESCE(SUM(total_bayar),0) FROM transaksi WHERE status_bayar='lunas'")->fetchColumn();

// Daftar kendaraan
$kendaraan = $pdo->query("SELECT * FROM kendaraan ORDER BY id_kendaraan")->fetchAll();

// 5 transaksi terbaru dengan JOIN
$transaksiTerbaru = $pdo->query("
    SELECT t.id_transaksi, p.nama AS pelanggan, k.nama_kendaraan, k.plat_nomor,
           t.tanggal_sewa, t.tanggal_kembali, t.total_bayar, t.status_bayar
    FROM transaksi t
    JOIN pelanggan p  ON t.id_pelanggan  = p.id_pelanggan
    JOIN kendaraan k  ON t.id_kendaraan  = k.id_kendaraan
    ORDER BY t.id_transaksi DESC LIMIT 5
")->fetchAll();

function formatRupiah($n) {
    return 'Rp ' . number_format($n, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RentWheels — Sistem Rental Kendaraan</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">
<style>
  :root {
    --bg:        #0d0f14;
    --surface:   #151820;
    --surface2:  #1c2030;
    --border:    #252a3a;
    --accent:    #f4b942;
    --accent2:   #e8603c;
    --green:     #3ecf8e;
    --red:       #e85c5c;
    --blue:      #5c9ee8;
    --text:      #e8eaf0;
    --muted:     #6b7491;
    --radius:    14px;
  }
  * { margin:0; padding:0; box-sizing:border-box; }
  body {
    background: var(--bg);
    color: var(--text);
    font-family: 'DM Sans', sans-serif;
    font-size: 15px;
    min-height: 100vh;
  }

  /* ── NAV ── */
  nav {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 40px;
    height: 64px;
    border-bottom: 1px solid var(--border);
    background: var(--surface);
    position: sticky; top: 0; z-index: 100;
  }
  .logo {
    font-family: 'Syne', sans-serif;
    font-size: 22px;
    font-weight: 800;
    letter-spacing: -0.5px;
    color: var(--text);
  }
  .logo span { color: var(--accent); }
  .nav-badge {
    background: var(--surface2);
    border: 1px solid var(--border);
    padding: 6px 14px;
    border-radius: 99px;
    font-size: 12px;
    color: var(--muted);
    font-family: 'DM Sans', sans-serif;
    letter-spacing: 0.5px;
  }

  /* ── LAYOUT ── */
  main { max-width: 1300px; margin: 0 auto; padding: 48px 32px 80px; }

  .page-header { margin-bottom: 40px; }
  .page-header h1 {
    font-family: 'Syne', sans-serif;
    font-size: 38px;
    font-weight: 800;
    line-height: 1.1;
    letter-spacing: -1px;
  }
  .page-header h1 em { color: var(--accent); font-style: normal; }
  .page-header p {
    margin-top: 10px;
    color: var(--muted);
    font-size: 15px;
  }

  /* ── STATS GRID ── */
  .stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 40px;
  }
  .stat-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 24px;
    position: relative;
    overflow: hidden;
    transition: border-color .2s, transform .2s;
  }
  .stat-card:hover { border-color: var(--accent); transform: translateY(-2px); }
  .stat-card::before {
    content: '';
    position: absolute;
    top: 0; right: 0;
    width: 80px; height: 80px;
    border-radius: 0 var(--radius) 0 80px;
    opacity: .07;
  }
  .stat-card:nth-child(1)::before { background: var(--blue); }
  .stat-card:nth-child(2)::before { background: var(--accent); }
  .stat-card:nth-child(3)::before { background: var(--green); }
  .stat-card:nth-child(4)::before { background: var(--accent2); }

  .stat-icon {
    font-size: 26px;
    margin-bottom: 14px;
    display: block;
  }
  .stat-value {
    font-family: 'Syne', sans-serif;
    font-size: 30px;
    font-weight: 800;
    color: var(--text);
    line-height: 1;
  }
  .stat-label {
    margin-top: 6px;
    color: var(--muted);
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: .8px;
  }

  /* ── SECTION TITLE ── */
  .section-title {
    font-family: 'Syne', sans-serif;
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .section-title .pill {
    background: var(--surface2);
    border: 1px solid var(--border);
    padding: 3px 10px;
    border-radius: 99px;
    font-size: 11px;
    color: var(--muted);
    font-family: 'DM Sans', sans-serif;
    font-weight: 400;
  }

  /* ── TWO-COL ── */
  .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 32px; }

  /* ── TABLE ── */
  .card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
  }
  .card-pad { padding: 24px; }
  table { width: 100%; border-collapse: collapse; }
  thead th {
    padding: 12px 16px;
    text-align: left;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .8px;
    color: var(--muted);
    border-bottom: 1px solid var(--border);
    background: var(--surface2);
    font-family: 'DM Sans', sans-serif;
    font-weight: 500;
  }
  tbody td {
    padding: 13px 16px;
    border-bottom: 1px solid var(--border);
    color: var(--text);
    font-size: 14px;
    vertical-align: middle;
  }
  tbody tr:last-child td { border-bottom: none; }
  tbody tr:hover td { background: var(--surface2); }

  /* ── BADGES ── */
  .badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 99px;
    font-size: 11px;
    font-weight: 500;
    letter-spacing: .3px;
  }
  .badge-green  { background: rgba(62,207,142,.15); color: var(--green);  border: 1px solid rgba(62,207,142,.25); }
  .badge-red    { background: rgba(232, 92, 92,.15); color: var(--red);   border: 1px solid rgba(232, 92, 92,.25); }
  .badge-yellow { background: rgba(244,185,66,.15); color: var(--accent); border: 1px solid rgba(244,185,66,.25); }
  .badge-blue   { background: rgba(92,158,232,.15); color: var(--blue);   border: 1px solid rgba(92,158,232,.25); }

  /* ── KENDARAAN LIST ── */
  .kendaraan-list {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    padding: 20px;
  }
  .kendaraan-item {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 16px;
    transition: border-color .2s;
  }
  .kendaraan-item:hover { border-color: var(--accent); }
  .kendaraan-name {
    font-family: 'Syne', sans-serif;
    font-weight: 700;
    font-size: 14px;
    margin-bottom: 4px;
  }
  .kendaraan-meta {
    color: var(--muted);
    font-size: 12px;
    margin-bottom: 10px;
  }
  .kendaraan-price {
    font-size: 13px;
    color: var(--accent);
    font-weight: 500;
    margin-bottom: 8px;
  }

  /* ── API DOCS SECTION ── */
  .api-section {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    margin-bottom: 32px;
    overflow: hidden;
  }
  .api-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
    background: var(--surface2);
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .api-header h3 { font-family: 'Syne', sans-serif; font-size: 16px; font-weight: 700; }
  .api-routes { padding: 20px 24px; display: flex; flex-direction: column; gap: 10px; }
  .api-route {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 14px;
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 13px;
  }
  .method {
    font-family: 'Syne', sans-serif;
    font-weight: 700;
    font-size: 11px;
    padding: 3px 8px;
    border-radius: 4px;
    min-width: 52px;
    text-align: center;
  }
  .method-get    { background: rgba(62,207,142,.2);  color: var(--green); }
  .method-post   { background: rgba(92,158,232,.2);  color: var(--blue); }
  .method-put    { background: rgba(244,185,66,.2);  color: var(--accent); }
  .method-delete { background: rgba(232, 92, 92,.2); color: var(--red); }
  .route-url { font-family: monospace; color: var(--text); font-size: 13px; }
  .route-desc { color: var(--muted); font-size: 12px; margin-left: auto; }

  footer {
    text-align: center;
    color: var(--muted);
    font-size: 12px;
    padding: 24px;
    border-top: 1px solid var(--border);
  }

  @media (max-width: 900px) {
    .stats { grid-template-columns: 1fr 1fr; }
    .two-col { grid-template-columns: 1fr; }
    .kendaraan-list { grid-template-columns: 1fr 1fr; }
  }
  @media (max-width: 600px) {
    main { padding: 24px 16px; }
    .stats { grid-template-columns: 1fr; }
    .kendaraan-list { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>

<nav>
  <div class="logo">Rent<span>Wheels</span></div>
  <span class="nav-badge">🚗 Sistem Rental Kendaraan API</span>
</nav>

<main>
  <div class="page-header">
    <h1>Dashboard <em>Rental</em><br>Kendaraan</h1>
    <p>Sistem manajemen rental kendaraan dengan REST API — GET, POST, PUT, DELETE</p>
  </div>

  <!-- Stats -->
  <div class="stats">
    <div class="stat-card">
      <span class="stat-icon">👥</span>
      <div class="stat-value"><?= $totalPelanggan ?></div>
      <div class="stat-label">Total Pelanggan</div>
    </div>
    <div class="stat-card">
      <span class="stat-icon">🚗</span>
      <div class="stat-value"><?= $totalKendaraan ?></div>
      <div class="stat-label">Total Kendaraan</div>
    </div>
    <div class="stat-card">
      <span class="stat-icon">📋</span>
      <div class="stat-value"><?= $totalTransaksi ?></div>
      <div class="stat-label">Total Transaksi</div>
    </div>
    <div class="stat-card">
      <span class="stat-icon">💰</span>
      <div class="stat-value" style="font-size:20px"><?= formatRupiah($totalPendapatan) ?></div>
      <div class="stat-label">Total Pendapatan</div>
    </div>
  </div>

  <!-- API Routes Reference -->
  <div class="section-title" style="margin-bottom:16px">📡 Endpoint API <span class="pill">REST</span></div>
  <?php
  $resources = [
    ['pelanggan', 'Pelanggan', '👥'],
    ['kendaraan', 'Kendaraan', '🚗'],
    ['transaksi', 'Transaksi', '📋'],
  ];
  $routeDefs = [
    ['GET',    '/api/{r}',       'Ambil semua data'],
    ['GET',    '/api/{r}?id=1',  'Ambil data by ID'],
    ['POST',   '/api/{r}',       'Tambah data baru'],
    ['PUT',    '/api/{r}?id=1',  'Update data by ID'],
    ['DELETE', '/api/{r}?id=1',  'Hapus data by ID'],
  ];
  foreach ($resources as [$slug, $label, $icon]):
  ?>
  <div class="api-section" style="margin-bottom:16px">
    <div class="api-header">
      <span><?= $icon ?></span>
      <h3><?= $label ?></h3>
      <code style="font-size:12px;color:var(--muted);margin-left:auto">/api/<?= $slug ?>.php</code>
    </div>
    <div class="api-routes">
      <?php foreach ($routeDefs as [$m, $url, $desc]):
        $fullUrl = str_replace('{r}', $slug, $url);
      ?>
      <div class="api-route">
        <span class="method method-<?= strtolower($m) ?>"><?= $m ?></span>
        <span class="route-url"><?= $fullUrl ?></span>
        <span class="route-desc"><?= $desc ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>

  <div class="two-col">
    <!-- Daftar Kendaraan -->
    <div>
      <div class="section-title">🚗 Daftar Kendaraan <span class="pill"><?= count($kendaraan) ?> unit</span></div>
      <div class="card">
        <div class="kendaraan-list">
          <?php foreach ($kendaraan as $k): ?>
          <div class="kendaraan-item">
            <div class="kendaraan-name"><?= htmlspecialchars($k['nama_kendaraan']) ?></div>
            <div class="kendaraan-meta"><?= $k['merek'] ?> · <?= $k['plat_nomor'] ?> · <?= $k['tahun'] ?></div>
            <div class="kendaraan-price"><?= formatRupiah($k['harga_sewa']) ?> / hari</div>
            <?php
              $badge = match($k['status']) {
                'tersedia'  => 'badge-green',
                'disewa'    => 'badge-red',
                'perawatan' => 'badge-yellow',
                default     => 'badge-blue'
              };
            ?>
            <span class="badge <?= $badge ?>"><?= ucfirst($k['status']) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Transaksi Terbaru -->
    <div>
      <div class="section-title">📋 Transaksi Terbaru <span class="pill">5 terbaru</span></div>
      <div class="card">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Pelanggan</th>
              <th>Kendaraan</th>
              <th>Total</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($transaksiTerbaru as $t): ?>
            <tr>
              <td style="color:var(--muted)"><?= $t['id_transaksi'] ?></td>
              <td><?= htmlspecialchars($t['pelanggan']) ?></td>
              <td>
                <div style="font-size:13px"><?= $t['nama_kendaraan'] ?></div>
                <div style="font-size:11px;color:var(--muted)"><?= $t['plat_nomor'] ?></div>
              </td>
              <td style="color:var(--accent)"><?= formatRupiah($t['total_bayar']) ?></td>
              <td>
                <?php if ($t['status_bayar'] === 'lunas'): ?>
                  <span class="badge badge-green">Lunas</span>
                <?php else: ?>
                  <span class="badge badge-red">Belum Lunas</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</main>

<footer>
  RentWheels API &mdash; Tugas Pemrograman Web &copy; <?= date('Y') ?>
</footer>

</body>
</html>
