-- ============================================================
-- DATABASE: Rental Kendaraan
-- Tabel: pelanggan, kendaraan, transaksi
-- ============================================================

-- Tabel 1: pelanggan
CREATE TABLE pelanggan (
    id_pelanggan SERIAL PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    nik VARCHAR(20) UNIQUE NOT NULL,
    no_hp VARCHAR(15) NOT NULL,
    alamat TEXT NOT NULL,
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel 2: kendaraan
CREATE TABLE kendaraan (
    id_kendaraan SERIAL PRIMARY KEY,
    nama_kendaraan VARCHAR(100) NOT NULL,
    jenis VARCHAR(50) NOT NULL,  -- mobil / motor
    merek VARCHAR(50) NOT NULL,
    plat_nomor VARCHAR(15) UNIQUE NOT NULL,
    tahun INT NOT NULL,
    harga_sewa INT NOT NULL,     -- per hari dalam rupiah
    status VARCHAR(20) DEFAULT 'tersedia', -- tersedia / disewa / perawatan
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel 3: transaksi
CREATE TABLE transaksi (
    id_transaksi SERIAL PRIMARY KEY,
    id_pelanggan INT NOT NULL REFERENCES pelanggan(id_pelanggan) ON DELETE CASCADE,
    id_kendaraan INT NOT NULL REFERENCES kendaraan(id_kendaraan) ON DELETE CASCADE,
    tanggal_sewa DATE NOT NULL,
    tanggal_kembali DATE NOT NULL,
    durasi_hari INT NOT NULL,
    total_bayar INT NOT NULL,
    status_bayar VARCHAR(20) DEFAULT 'belum lunas', -- lunas / belum lunas
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- SEED DATA: pelanggan (12 baris)
-- ============================================================
INSERT INTO pelanggan (nama, nik, no_hp, alamat, email) VALUES
('Budi Santoso',       '7371010101800001', '081234567890', 'Jl. Veteran No. 12, Makassar',       'budi.santoso@gmail.com'),
('Sari Dewi',          '7371010202850002', '082345678901', 'Jl. Perintis No. 5, Makassar',        'sari.dewi@gmail.com'),
('Andi Faisal',        '7371010303900003', '083456789012', 'Jl. Sultan Alauddin No. 20, Gowa',    'andi.faisal@gmail.com'),
('Nurhayati',          '7371010404880004', '084567890123', 'Jl. Rappocini No. 8, Makassar',       'nurhayati@yahoo.com'),
('Deni Kurniawan',     '7371010505920005', '085678901234', 'Jl. Abdullah Dg. Sirua No. 3, Makassar','deni.k@gmail.com'),
('Fatimah Zahra',      '7371010606870006', '086789012345', 'Jl. Nusantara No. 17, Makassar',      'fatimah.z@gmail.com'),
('Rizky Pratama',      '7371010707950007', '087890123456', 'Jl. Racing Centre No. 9, Makassar',   'rizky.p@gmail.com'),
('Indah Permata',      '7371010808910008', '088901234567', 'Jl. Tamalate No. 25, Makassar',       'indah.p@gmail.com'),
('Hendra Wijaya',      '7371010909890009', '089012345678', 'Jl. AP Pettarani No. 11, Makassar',   'hendra.w@gmail.com'),
('Lestari Putri',      '7371011010960010', '080123456789', 'Jl. Hertasning No. 33, Makassar',     'lestari.p@gmail.com'),
('Muh. Akbar',         '7371011111930011', '081234509876', 'Jl. Landak Baru No. 7, Makassar',     'akbar@gmail.com'),
('Rini Rahayu',        '7371011212940012', '082345609877', 'Jl. Tinumbu No. 14, Makassar',        'rini.r@gmail.com');

-- ============================================================
-- SEED DATA: kendaraan (12 baris)
-- ============================================================
INSERT INTO kendaraan (nama_kendaraan, jenis, merek, plat_nomor, tahun, harga_sewa, status) VALUES
('Avanza G',        'mobil', 'Toyota',   'DD 1234 AA', 2021, 350000, 'tersedia'),
('Xpander Cross',   'mobil', 'Mitsubishi','DD 5678 BB', 2022, 450000, 'disewa'),
('Beat Street',     'motor', 'Honda',    'DD 1111 CC', 2020, 75000,  'tersedia'),
('NMAX',            'motor', 'Yamaha',   'DD 2222 DD', 2021, 100000, 'disewa'),
('Innova Reborn',   'mobil', 'Toyota',   'DD 3333 EE', 2020, 500000, 'tersedia'),
('Sigra R',         'mobil', 'Daihatsu', 'DD 4444 FF', 2022, 300000, 'tersedia'),
('Vario 160',       'motor', 'Honda',    'DD 5555 GG', 2022, 90000,  'tersedia'),
('Aerox 155',       'motor', 'Yamaha',   'DD 6666 HH', 2021, 95000,  'perawatan'),
('Fortuner VRZ',    'mobil', 'Toyota',   'DD 7777 II', 2021, 800000, 'tersedia'),
('Mobilio E',       'mobil', 'Honda',    'DD 8888 JJ', 2020, 320000, 'disewa'),
('Mio M3',          'motor', 'Yamaha',   'DD 9999 KK', 2019, 70000,  'tersedia'),
('CBR 150R',        'motor', 'Honda',    'DD 1010 LL', 2022, 120000, 'tersedia');

-- ============================================================
-- SEED DATA: transaksi (12 baris)
-- ============================================================
INSERT INTO transaksi (id_pelanggan, id_kendaraan, tanggal_sewa, tanggal_kembali, durasi_hari, total_bayar, status_bayar) VALUES
(1,  1,  '2025-03-01', '2025-03-03', 2, 700000,  'lunas'),
(2,  4,  '2025-03-02', '2025-03-04', 2, 200000,  'lunas'),
(3,  2,  '2025-03-05', '2025-03-08', 3, 1350000, 'lunas'),
(4,  3,  '2025-03-06', '2025-03-07', 1, 75000,   'belum lunas'),
(5,  5,  '2025-03-10', '2025-03-13', 3, 1500000, 'lunas'),
(6,  7,  '2025-03-11', '2025-03-12', 1, 90000,   'lunas'),
(7,  6,  '2025-03-12', '2025-03-15', 3, 900000,  'belum lunas'),
(8,  10, '2025-03-13', '2025-03-16', 3, 960000,  'lunas'),
(9,  9,  '2025-03-15', '2025-03-17', 2, 1600000, 'lunas'),
(10, 11, '2025-03-16', '2025-03-17', 1, 70000,   'belum lunas'),
(11, 12, '2025-03-17', '2025-03-19', 2, 240000,  'lunas'),
(12, 1,  '2025-03-18', '2025-03-20', 2, 700000,  'belum lunas');
