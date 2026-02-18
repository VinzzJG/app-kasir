<?php
// Konfigurasi database
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'db_kasir_majoo'; // Perbaiki nama database sesuai dengan yang kita buat

// Membuat koneksi
$conn = mysqli_connect($host, $username, $password, $database);

// Cek koneksi
if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Set charset ke UTF-8
mysqli_set_charset($conn, "utf8");

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Fungsi untuk menjalankan query
function query($sql) {
    global $conn;
    $result = mysqli_query($conn, $sql);
    $rows = [];
    while($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    return $rows;
}

// Fungsi untuk mengecek apakah database sudah ada
function cekDatabase() {
    global $host, $username, $password, $database;
    
    // Koneksi tanpa memilih database
    $conn = mysqli_connect($host, $username, $password);
    
    // Cek apakah database ada
    $result = mysqli_query($conn, "SHOW DATABASES LIKE '$database'");
    
    if(mysqli_num_rows($result) == 0) {
        // Database tidak ada, tampilkan pesan error
        die("
            <div style='font-family: Arial; padding: 20px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px;'>
                <h2><i class='fas fa-exclamation-triangle'></i> Database Tidak Ditemukan!</h2>
                <p>Database <strong>'$database'</strong> tidak ditemukan. Silakan import file <strong>database.sql</strong> terlebih dahulu.</p>
                <p>Cara mengatasi:</p>
                <ol>
                    <li>Buka phpMyAdmin (http://localhost/phpmyadmin)</li>
                    <li>Buat database baru dengan nama <strong>'$database'</strong></li>
                    <li>Import file <strong>database.sql</strong> yang sudah disediakan</li>
                    <li>Atau jalankan SQL dari file tersebut di tab SQL</li>
                </ol>
                <p><a href='#' onclick='window.location.reload()' style='color: #721c24;'>Refresh halaman setelah import database</a></p>
            </div>
        ");
    }
    
    mysqli_close($conn);
}

// Panggil fungsi cek database
cekDatabase();
?>