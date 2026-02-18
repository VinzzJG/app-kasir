<?php
require_once 'config.php';

// Cek login
if (!isLoggedIn()) {
    sendError('Unauthorized', 401);
}

// Cek role
if (!hasRole(['admin', 'kasir'])) {
    sendError('Forbidden', 403);
}

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    sendError('Invalid request data');
}

$action = $_GET['action'] ?? $data['action'] ?? 'process';

switch ($action) {
    case 'process':
        processTransaction($conn, $data);
        break;
    default:
        sendError('Invalid action');
}

function processTransaction($conn, $data) {
    // Validate required data
    if (!isset($data['cart']) || !isset($data['total']) || !isset($data['metode']) || !isset($data['bayar'])) {
        sendError('Missing required data');
    }
    
    $cart = $data['cart'];
    $total = (float)$data['total'];
    $metode = trim($data['metode']);
    $bayar = (float)$data['bayar'];
    
    if (empty($cart)) {
        sendError('Cart is empty');
    }
    
    if ($bayar < $total) {
        sendError('Insufficient payment');
    }
    
    // ========== FIX: Validasi metode pembayaran ==========
    // Bersihkan input
    $metode = strtolower(trim($metode));
    
    // Validasi metode pembayaran yang diperbolehkan
    $valid_methods = ['cash', 'qris', 'transfer'];
    
    // Jika metode kosong atau tidak valid, set default ke 'cash'
    if (empty($metode) || !in_array($metode, $valid_methods)) {
        $metode = 'cash';
        // Log untuk debugging
        error_log("Payment method set to default 'cash' (original: " . ($data['metode'] ?? 'null') . ")");
    }
    
    // Escape untuk keamanan
    $metode = mysqli_real_escape_string($conn, $metode);
    // =====================================================
    
    $user_id = $_SESSION['user_id'];
    $kembalian = $bayar - $total;
    
    // ========== GENERATE NOMOR TRANSAKSI ==========
    // Format: TRX202602160028
    $date = date('Ymd');
    $query = mysqli_query($conn, "SELECT COUNT(*) as total FROM transaksi WHERE DATE(created_at) = CURDATE()");
    
    if (!$query) {
        sendError('Failed to generate invoice number');
    }
    
    $row = mysqli_fetch_assoc($query);
    $counter = str_pad(($row['total'] ?? 0) + 1, 4, '0', STR_PAD_LEFT);
    $no_transaksi = "TRX{$date}{$counter}";
    
    // Begin transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Insert transaksi
        $query = "INSERT INTO transaksi (
            no_transaksi, 
            user_id, 
            total_harga, 
            grand_total, 
            metode_pembayaran, 
            jumlah_bayar, 
            kembalian, 
            status,
            created_at
        ) VALUES (
            '$no_transaksi', 
            '$user_id', 
            '$total', 
            '$total', 
            '$metode', 
            '$bayar', 
            '$kembalian', 
            'selesai',
            NOW()
        )";
        
        if (!mysqli_query($conn, $query)) {
            throw new Exception('Failed to insert transaction: ' . mysqli_error($conn));
        }
        
        $transaksi_id = mysqli_insert_id($conn);
        
        // Insert details and update stock
        foreach ($cart as $item) {
            // Validasi item
            if (!isset($item['id']) || !isset($item['qty']) || !isset($item['price'])) {
                throw new Exception('Invalid cart item data');
            }
            
            $produk_id = (int)$item['id'];
            $jumlah = (int)$item['qty'];
            $harga = (float)$item['price'];
            $subtotal = $harga * $jumlah;
            
            // Check stock again
            $stock_query = mysqli_query($conn, "SELECT stok, nama_produk FROM produk WHERE id = '$produk_id'");
            if (!$stock_query) {
                throw new Exception('Failed to check stock');
            }
            
            $stock_data = mysqli_fetch_assoc($stock_query);
            
            if (!$stock_data) {
                throw new Exception('Product not found: ID ' . $produk_id);
            }
            
            if ($stock_data['stok'] < $jumlah) {
                throw new Exception("Insufficient stock for {$stock_data['nama_produk']}. Available: {$stock_data['stok']}, Requested: $jumlah");
            }
            
            // Insert detail transaksi
            $query = "INSERT INTO detail_transaksi (
                transaksi_id, 
                produk_id, 
                jumlah, 
                harga_satuan, 
                subtotal
            ) VALUES (
                '$transaksi_id', 
                '$produk_id', 
                '$jumlah', 
                '$harga', 
                '$subtotal'
            )";
            
            if (!mysqli_query($conn, $query)) {
                throw new Exception('Failed to insert transaction detail: ' . mysqli_error($conn));
            }
            
            // Update stock produk (berkurang 1x)
            $query = "UPDATE produk SET stok = stok - $jumlah WHERE id = '$produk_id'";
            if (!mysqli_query($conn, $query)) {
                throw new Exception('Failed to update stock: ' . mysqli_error($conn));
            }
            
            // Catat di stok_keluar untuk history
            $keterangan = "Sold in transaction: $no_transaksi";
            $query_stok_keluar = "INSERT INTO stok_keluar (produk_id, jumlah, keterangan, user_id, created_at) 
                                  VALUES ('$produk_id', '$jumlah', '$keterangan', '$user_id', NOW())";
            mysqli_query($conn, $query_stok_keluar);
        }
        
        // Log aktivitas
        $log = "INSERT INTO log_aktivitas (user_id, aktivitas, detail, ip_address, created_at) 
                VALUES ('$user_id', 'Transaction', 'New transaction: $no_transaksi - Total: Rp " . number_format($total,0,',','.') . "', '{$_SERVER['REMOTE_ADDR']}', NOW())";
        mysqli_query($conn, $log);
        
        // Commit
        mysqli_commit($conn);
        
        // Clear cart from session
        unset($_SESSION['cart']);
        
        // ========== FIX: RECEIPT_URL DENGAN PATH YANG BENAR ==========
        // Untuk akses dari folder api ke folder pages
        $receipt_url = '../pages/struk.php?no=' . urlencode($no_transaksi);
        
        // Untuk debugging (bisa dihapus nanti)
        error_log("Transaction success: $no_transaksi - URL: $receipt_url - Metode: $metode");
        // ==============================================================
        
        sendResponse(true, 'Transaction successful', [
            'no_transaksi' => $no_transaksi,
            'total' => $total,
            'kembalian' => $kembalian,
            'metode' => $metode,
            'receipt_url' => $receipt_url
        ]);
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        sendError($e->getMessage());
    }
}
?>