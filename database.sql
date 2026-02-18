CREATE DATABASE IF NOT EXISTS db_kasir_majoo;
USE db_kasir_majoo;

-- Tabel User/Karyawan
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nama_lengkap VARCHAR(100) NOT NULL,
    role ENUM('admin', 'kasir') DEFAULT 'kasir',
    foto VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel Kategori Produk
CREATE TABLE kategori (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama_kategori VARCHAR(50) NOT NULL,
    deskripsi TEXT
);

-- Tabel Produk
CREATE TABLE produk (
    id INT PRIMARY KEY AUTO_INCREMENT,
    kode_produk VARCHAR(20) UNIQUE NOT NULL,
    nama_produk VARCHAR(100) NOT NULL,
    kategori_id INT,
    harga DECIMAL(10,2) NOT NULL,
    stok INT DEFAULT 0,
    stok_minimum INT DEFAULT 5,
    foto VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kategori_id) REFERENCES kategori(id) ON DELETE SET NULL
);

-- Tabel Transaksi
CREATE TABLE transaksi (
    id INT PRIMARY KEY AUTO_INCREMENT,
    no_transaksi VARCHAR(20) UNIQUE NOT NULL,
    user_id INT,
    total_harga DECIMAL(10,2) NOT NULL,
    metode_pembayaran ENUM('tunai', 'transfer', 'qris') DEFAULT 'tunai',
    jumlah_bayar DECIMAL(10,2),
    kembalian DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Tabel Detail Transaksi
CREATE TABLE detail_transaksi (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transaksi_id INT,
    produk_id INT,
    jumlah INT NOT NULL,
    harga_satuan DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (transaksi_id) REFERENCES transaksi(id) ON DELETE CASCADE,
    FOREIGN KEY (produk_id) REFERENCES produk(id)
);

-- Insert data awal
INSERT INTO users (username, password, nama_lengkap, role) VALUES
('admin', MD5('admin123'), 'Administrator', 'admin'),
('kasir1', MD5('kasir123'), 'Kasir Satu', 'kasir');

INSERT INTO kategori (nama_kategori, deskripsi) VALUES
('Makanan', 'Kategori produk makanan'),
('Minuman', 'Kategori produk minuman'),
('Snack', 'Kategori makanan ringan');

INSERT INTO produk (kode_produk, nama_produk, kategori_id, harga, stok, stok_minimum) VALUES
('BRG001', 'Nasi Goreng', 1, 25000, 50, 10),
('BRG002', 'Mie Goreng', 1, 20000, 45, 10),
('BRG003', 'Es Teh Manis', 2, 5000, 100, 20),
('BRG004', 'Es Jeruk', 2, 7000, 80, 20),
('BRG005', 'Kentang Goreng', 3, 15000, 30, 8);

CREATE TABLE IF NOT EXISTS pengaturan (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama_toko VARCHAR(100) DEFAULT 'Majoo POS',
    alamat TEXT,
    telepon VARCHAR(20),
    email VARCHAR(100),
    pajak DECIMAL(5,2) DEFAULT 0,
    diskon_default DECIMAL(5,2) DEFAULT 0,
    receipt_header VARCHAR(200) DEFAULT 'Terima Kasih!',
    receipt_footer VARCHAR(200) DEFAULT 'Silahkan datang kembali',
    show_pajak TINYINT(1) DEFAULT 1,
    show_diskon TINYINT(1) DEFAULT 1,
    auto_print TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO pengaturan (id, nama_toko) VALUES (1, 'Majoo POS') 
ON DUPLICATE KEY UPDATE id=1;