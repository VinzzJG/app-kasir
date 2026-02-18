<?php
session_start();
require_once '../config/database.php';

// Cek login
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$nama_lengkap = $_SESSION['nama_lengkap'];

// Only admin can access
if($role != 'admin') {
    header("Location: dashboard.php");
    exit();
}

// Handle Stock In
if(isset($_POST['stock_in'])) {
    $produk_id = $_POST['produk_id'];
    $jumlah = (int)$_POST['jumlah'];
    $harga_beli = str_replace('.', '', $_POST['harga_beli']);
    $supplier_id = !empty($_POST['supplier_id']) ? $_POST['supplier_id'] : 'NULL';
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);
    $total_harga = $jumlah * $harga_beli;
    
    // Insert stock in record
    $query = "INSERT INTO stok_masuk (produk_id, supplier_id, jumlah, harga_beli, total_harga, keterangan, user_id) 
              VALUES ('$produk_id', $supplier_id, '$jumlah', '$harga_beli', '$total_harga', '$keterangan', '$user_id')";
    
    if(mysqli_query($conn, $query)) {
        // Update product stock
        mysqli_query($conn, "UPDATE produk SET stok = stok + $jumlah WHERE id = '$produk_id'");
        $success = "Stock added successfully!";
    } else {
        $error = "Error: " . mysqli_error($conn);
    }
}

// Handle Stock Out
if(isset($_POST['stock_out'])) {
    $produk_id = $_POST['produk_id'];
    $jumlah = (int)$_POST['jumlah'];
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);
    
    // Check current stock
    $check = mysqli_query($conn, "SELECT stok FROM produk WHERE id = '$produk_id'");
    $produk = mysqli_fetch_assoc($check);
    
    if($produk['stok'] >= $jumlah) {
        // Insert stock out record
        $query = "INSERT INTO stok_keluar (produk_id, jumlah, keterangan, user_id) 
                  VALUES ('$produk_id', '$jumlah', '$keterangan', '$user_id')";
        
        if(mysqli_query($conn, $query)) {
            // Update product stock
            mysqli_query($conn, "UPDATE produk SET stok = stok - $jumlah WHERE id = '$produk_id'");
            $success = "Stock removed successfully!";
        } else {
            $error = "Error: " . mysqli_error($conn);
        }
    } else {
        $error = "Insufficient stock! Current stock: " . $produk['stok'];
    }
}

// Handle Stock Adjustment
if(isset($_POST['adjust'])) {
    $produk_id = $_POST['produk_id'];
    $stok_baru = (int)$_POST['stok_baru'];
    $alasan = mysqli_real_escape_string($conn, $_POST['alasan']);
    
    // Get current stock
    $check = mysqli_query($conn, "SELECT stok FROM produk WHERE id = '$produk_id'");
    $produk = mysqli_fetch_assoc($check);
    $stok_lama = $produk['stok'];
    $selisih = $stok_baru - $stok_lama;
    
    // Update stock
    $query = "UPDATE produk SET stok = '$stok_baru' WHERE id = '$produk_id'";
    
    if(mysqli_query($conn, $query)) {
        // Log adjustment if there's a change
        if($selisih != 0) {
            $keterangan = "Stock adjustment: $stok_lama -> $stok_baru ($alasan)";
            mysqli_query($conn, "INSERT INTO stok_keluar (produk_id, jumlah, keterangan, user_id) 
                                VALUES ('$produk_id', '$selisih', '$keterangan', '$user_id')");
        }
        $success = "Stock adjusted successfully!";
    } else {
        $error = "Error: " . mysqli_error($conn);
    }
}

// Get all products
$query_produk = mysqli_query($conn, "SELECT p.*, k.nama_kategori 
    FROM produk p 
    LEFT JOIN kategori k ON p.kategori_id = k.id 
    ORDER BY p.nama_produk ASC");

// Get stock in history
$query_stok_masuk = mysqli_query($conn, "SELECT sm.*, p.nama_produk, COALESCE(s.nama_supplier, '-') as nama_supplier, u.nama_lengkap 
    FROM stok_masuk sm 
    JOIN produk p ON sm.produk_id = p.id 
    LEFT JOIN supplier s ON sm.supplier_id = s.id 
    JOIN users u ON sm.user_id = u.id 
    ORDER BY sm.created_at DESC 
    LIMIT 10");

// Get stock out history
$query_stok_keluar = mysqli_query($conn, "SELECT sk.*, p.nama_produk, u.nama_lengkap 
    FROM stok_keluar sk 
    JOIN produk p ON sk.produk_id = p.id 
    JOIN users u ON sk.user_id = u.id 
    ORDER BY sk.created_at DESC 
    LIMIT 10");

// Get suppliers for dropdown
$query_supplier = mysqli_query($conn, "SELECT * FROM supplier ORDER BY nama_supplier ASC");

// Get low stock products
$query_low_stock = mysqli_query($conn, "SELECT p.*, k.nama_kategori 
    FROM produk p 
    LEFT JOIN kategori k ON p.kategori_id = k.id 
    WHERE p.stok <= p.stok_minimum 
    ORDER BY p.stok ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Inventory - Kasir Majoo</title>
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #0A0A0A;
            --secondary: #1E1E1E;
            --accent: #00FFB2;
            --accent-glow: rgba(0, 255, 178, 0.3);
            --card-bg: #141414;
            --text-primary: #FFFFFF;
            --text-secondary: #A0A0A0;
            --border: #2A2A2A;
            --success: #00FFB2;
            --warning: #FFB800;
            --danger: #FF4D4D;
            --info: #3B82F6;
        }

        body {
            font-family: 'Space Grotesk', sans-serif;
            background: var(--primary);
            color: var(--text-primary);
            min-height: 100vh;
        }

        /* Top Navigation */
        .mobile-top-nav {
            background: rgba(10, 10, 10, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border);
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .mobile-logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .mobile-logo i {
            font-size: 24px;
            color: var(--accent);
        }

        .mobile-logo span {
            font-weight: 600;
            font-size: 18px;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #fff, var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .menu-toggle {
            width: 42px;
            height: 42px;
            background: var(--secondary);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            cursor: pointer;
            color: var(--text-primary);
            border: 1px solid var(--border);
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: -300px;
            width: 300px;
            height: 100vh;
            background: var(--secondary);
            z-index: 1000;
            transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-y: auto;
            border-right: 1px solid var(--border);
        }

        .sidebar.active {
            left: 0;
        }

        .sidebar-header {
            padding: 30px 24px;
            border-bottom: 1px solid var(--border);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-icon {
            width: 48px;
            height: 48px;
            background: var(--accent);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: var(--primary);
        }

        .logo-text h3 {
            font-size: 20px;
            font-weight: 600;
            letter-spacing: -0.5px;
            color: var(--text-primary);
        }

        .logo-text p {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .user-info {
            padding: 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 52px;
            height: 52px;
            background: linear-gradient(135deg, var(--accent), #00ccff);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: var(--primary);
        }

        .user-details h4 {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .user-details small {
            font-size: 13px;
            color: var(--accent);
            font-weight: 500;
        }

        .sidebar-menu {
            padding: 24px;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 16px;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: 14px;
            font-size: 15px;
            font-weight: 500;
            margin-bottom: 4px;
            transition: all 0.2s;
        }

        .menu-item i {
            width: 22px;
            font-size: 18px;
        }

        .menu-item:hover {
            background: var(--border);
            color: var(--text-primary);
        }

        .menu-item.active {
            background: var(--accent);
            color: var(--primary);
            font-weight: 600;
        }

        .logout-mobile {
            margin: 24px;
            padding: 14px 20px;
            background: rgba(255, 77, 77, 0.1);
            border: 1px solid rgba(255, 77, 77, 0.2);
            color: var(--danger);
            text-decoration: none;
            border-radius: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 15px;
            font-weight: 600;
        }

        /* Overlay */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            backdrop-filter: blur(5px);
            z-index: 999;
            display: none;
        }

        .overlay.active {
            display: block;
        }

        /* Main Content */
        .main-content {
            padding: 20px;
        }

        /* Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .page-header h1 {
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(135deg, #fff, var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn-primary {
            background: var(--accent);
            color: var(--primary);
            border: none;
            padding: 12px 24px;
            border-radius: 40px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px var(--accent-glow);
        }

        .btn-secondary {
            background: var(--secondary);
            color: var(--text-primary);
            border: 1px solid var(--border);
            padding: 12px 24px;
            border-radius: 40px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn-secondary:hover {
            border-color: var(--accent);
        }

        .btn-warning {
            background: var(--warning);
            color: var(--primary);
            border: none;
            padding: 12px 24px;
            border-radius: 40px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Alert */
        .alert {
            background: rgba(0, 255, 178, 0.1);
            border: 1px solid var(--accent);
            color: var(--accent);
            padding: 15px 20px;
            border-radius: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert.error {
            background: rgba(255, 77, 77, 0.1);
            border-color: var(--danger);
            color: var(--danger);
        }

        /* Stats Cards - FIXED */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--secondary);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 20px;
        }

        .stat-icon {
            width: 45px;
            height: 45px;
            background: var(--primary);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin-bottom: 15px;
            border: 1px solid var(--border);
        }

        .stat-icon.warning { color: var(--warning); }
        .stat-icon.success { color: var(--success); }
        .stat-icon.info { color: var(--info); }
        .stat-icon.danger { color: var(--danger); }

        .stat-info h4 {
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }

        .stat-info p {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .stat-info small {
            font-size: 12px;
            color: var(--text-secondary);
        }

        /* Low Stock Section */
        .low-stock-section {
            background: var(--secondary);
            border: 1px solid var(--border);
            border-radius: 30px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-header h2 {
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-header h2 i {
            color: var(--warning);
        }

        .low-stock-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }

        .low-stock-item {
            background: var(--primary);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .item-info h4 {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .item-info p {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .stock-badge {
            background: rgba(255, 184, 0, 0.1);
            border: 1px solid rgba(255, 184, 0, 0.2);
            color: var(--warning);
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
        }

        .stock-badge.critical {
            background: rgba(255, 77, 77, 0.1);
            border-color: rgba(255, 77, 77, 0.2);
            color: var(--danger);
        }

        /* Tables */
        .tables-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .table-card {
            background: var(--secondary);
            border: 1px solid var(--border);
            border-radius: 30px;
            padding: 20px;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .table-header h3 {
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .table-header h3 i {
            color: var(--accent);
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        th {
            text-align: left;
            padding: 12px;
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 12px;
            border-bottom: 1px solid var(--border);
        }

        td {
            padding: 12px;
            color: var(--text-primary);
            font-size: 13px;
            border-bottom: 1px solid var(--border);
        }

        tr:last-child td {
            border-bottom: none;
        }

        .supplier-badge {
            background: var(--primary);
            border: 1px solid var(--border);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            backdrop-filter: blur(10px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: var(--secondary);
            border: 1px solid var(--border);
            border-radius: 40px;
            padding: 40px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .modal-header h2 {
            font-size: 24px;
            font-weight: 600;
            background: linear-gradient(135deg, #fff, var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .close-modal {
            font-size: 24px;
            color: var(--text-secondary);
            cursor: pointer;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }

        .form-control {
            width: 100%;
            background: var(--primary);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 14px 20px;
            color: var(--text-primary);
            font-family: 'Space Grotesk', sans-serif;
            font-size: 14px;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent);
        }

        select.form-control {
            cursor: pointer;
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .modal-footer {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn-save {
            flex: 1;
            background: var(--accent);
            color: var(--primary);
            border: none;
            padding: 14px;
            border-radius: 40px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-cancel {
            flex: 1;
            background: var(--border);
            color: var(--text-primary);
            border: none;
            padding: 14px;
            border-radius: 40px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        /* Floating Action Button */
        .fab {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 60px;
            height: 60px;
            background: var(--accent);
            border-radius: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 26px;
            box-shadow: 0 8px 25px var(--accent-glow);
            z-index: 90;
            cursor: pointer;
            border: none;
        }

        /* Responsive */
        @media (min-width: 768px) {
            .mobile-top-nav,
            .fab {
                display: none;
            }

            .sidebar {
                left: 0;
                width: 280px;
            }

            .main-content {
                margin-left: 280px;
                padding: 30px;
            }
        }

        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .tables-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .action-buttons {
                width: 100%;
            }

            .btn-primary, .btn-secondary {
                flex: 1;
            }
        }
    </style>
</head>
<body>
    <!-- Overlay -->
    <div class="overlay" id="overlay" onclick="closeMenu()"></div>

    <!-- Mobile Top Navigation -->
    <div class="mobile-top-nav">
        <div class="mobile-logo">
            <i class="fas fa-bolt"></i>
            <span>majoo POS</span>
        </div>
        <div class="menu-toggle" onclick="toggleMenu()">
            <i class="fas fa-bars"></i>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-bolt"></i>
                </div>
                <div class="logo-text">
                    <h3>majoo POS</h3>
                    <p>Enterprise v3.0</p>
                </div>
            </div>
        </div>

        <div class="user-info">
            <div class="user-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="user-details">
                <h4><?php echo htmlspecialchars($nama_lengkap); ?></h4>
                <small>⚡ <?php echo ucfirst($role); ?></small>
            </div>
        </div>

        <div class="sidebar-menu">
            <a href="dashboard.php" class="menu-item">
                <i class="fas fa-chart-pie"></i> Dashboard
            </a>
            
            <?php if($role == 'admin' || $role == 'kasir'): ?>
            <a href="kasir.php" class="menu-item">
                <i class="fas fa-credit-card"></i> POS
            </a>
            <?php endif; ?>

            <?php if($role == 'admin'): ?>
            <a href="produk.php" class="menu-item">
                <i class="fas fa-cube"></i> Products
            </a>
            <a href="stok.php" class="menu-item active">
                <i class="fas fa-boxes"></i> Inventory
            </a>
            <?php endif; ?>

            <?php if($role == 'admin' || $role == 'owner'): ?>
            <a href="laporan.php" class="menu-item">
                <i class="fas fa-chart-line"></i> Reports
            </a>
            <?php endif; ?>

            <?php if($role == 'admin'): ?>
            <a href="karyawan.php" class="menu-item">
                <i class="fas fa-users"></i> Employees
            </a>
            <a href="pengaturan.php" class="menu-item">
                <i class="fas fa-cog"></i> Settings
            </a>
            <?php endif; ?>
        </div>

        <a href="logout.php" class="logout-mobile">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1>Inventory Management</h1>
            <div class="action-buttons">
                <button class="btn-primary" onclick="openStockInModal()">
                    <i class="fas fa-arrow-down"></i> Stock In
                </button>
                <button class="btn-secondary" onclick="openStockOutModal()">
                    <i class="fas fa-arrow-up"></i> Stock Out
                </button>
                <button class="btn-warning" onclick="openAdjustModal()">
                    <i class="fas fa-sliders-h"></i> Adjust
                </button>
            </div>
        </div>

        <!-- Alerts -->
        <?php if(isset($success)): ?>
        <div class="alert">
            <i class="fas fa-check-circle"></i>
            <?php echo $success; ?>
        </div>
        <?php endif; ?>

        <?php if(isset($error)): ?>
        <div class="alert error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $error; ?>
        </div>
        <?php endif; ?>

        <!-- Stats Cards - FIXED -->
        <?php
        // Hitung total produk
        $total_produk = mysqli_num_rows($query_produk);
        
        // Hitung total stok dengan aman
        $query_total_stok = mysqli_query($conn, "SELECT COALESCE(SUM(stok), 0) as total FROM produk");
        $total_stok = 0;
        if($query_total_stok && mysqli_num_rows($query_total_stok) > 0) {
            $row = mysqli_fetch_assoc($query_total_stok);
            $total_stok = $row['total'];
        }
        
        // Hitung low stock
        $total_low_stock = mysqli_num_rows($query_low_stock);
        
        // Hitung inventory value dengan aman
        $query_total_value = mysqli_query($conn, "SELECT COALESCE(SUM(stok * harga_beli), 0) as total FROM produk");
        $total_value = 0;
        if($query_total_value && mysqli_num_rows($query_total_value) > 0) {
            $row = mysqli_fetch_assoc($query_total_value);
            $total_value = $row['total'];
        }
        ?>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon info">
                    <i class="fas fa-boxes"></i>
                </div>
                <div class="stat-info">
                    <h4>Total Products</h4>
                    <p><?php echo $total_produk; ?></p>
                    <small>Active items</small>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-cubes"></i>
                </div>
                <div class="stat-info">
                    <h4>Total Stock</h4>
                    <p><?php echo number_format($total_stok); ?></p>
                    <small>Units in stock</small>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-info">
                    <h4>Low Stock</h4>
                    <p><?php echo $total_low_stock; ?></p>
                    <small>Need reorder</small>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon danger">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-info">
                    <h4>Inventory Value</h4>
                    <p>Rp <?php echo number_format($total_value, 0, ',', '.'); ?></p>
                    <small>Total cost</small>
                </div>
            </div>
        </div>

        <!-- Low Stock Section -->
        <?php if(mysqli_num_rows($query_low_stock) > 0): ?>
        <div class="low-stock-section">
            <div class="section-header">
                <h2><i class="fas fa-exclamation-triangle"></i> Low Stock Alert</h2>
                <span class="stock-badge critical"><?php echo $total_low_stock; ?> items</span>
            </div>
            <div class="low-stock-grid">
                <?php while($item = mysqli_fetch_assoc($query_low_stock)): ?>
                <div class="low-stock-item">
                    <div class="item-info">
                        <h4><?php echo htmlspecialchars($item['nama_produk']); ?></h4>
                        <p><?php echo htmlspecialchars($item['nama_kategori']); ?></p>
                    </div>
                    <div class="stock-badge <?php echo $item['stok'] == 0 ? 'critical' : ''; ?>">
                        <?php echo $item['stok']; ?> / <?php echo $item['stok_minimum']; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- History Tables -->
        <div class="tables-grid">
            <!-- Stock In History -->
            <div class="table-card">
                <div class="table-header">
                    <h3><i class="fas fa-arrow-down"></i> Stock In History</h3>
                    <a href="laporan_stok.php?type=in" style="color: var(--accent); font-size: 13px;">View All</a>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Supplier</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($query_stok_masuk) > 0): ?>
                                <?php while($row = mysqli_fetch_assoc($query_stok_masuk)): ?>
                                <tr>
                                    <td><?php echo date('d/m H:i', strtotime($row['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['nama_produk']); ?></td>
                                    <td><span style="color: var(--success); font-weight: 600;">+<?php echo $row['jumlah']; ?></span></td>
                                    <td><span class="supplier-badge"><?php echo htmlspecialchars($row['nama_supplier']); ?></span></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; padding: 30px;">No stock in history</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Stock Out History -->
            <div class="table-card">
                <div class="table-header">
                    <h3><i class="fas fa-arrow-up"></i> Stock Out History</h3>
                    <a href="laporan_stok.php?type=out" style="color: var(--accent); font-size: 13px;">View All</a>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($query_stok_keluar) > 0): ?>
                                <?php while($row = mysqli_fetch_assoc($query_stok_keluar)): ?>
                                <tr>
                                    <td><?php echo date('d/m H:i', strtotime($row['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['nama_produk']); ?></td>
                                    <td><span style="color: var(--danger); font-weight: 600;">-<?php echo $row['jumlah']; ?></span></td>
                                    <td><?php echo htmlspecialchars($row['keterangan'] ?: '-'); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; padding: 30px;">No stock out history</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Action Button -->
    <div class="fab" onclick="openStockInModal()">
        <i class="fas fa-plus"></i>
    </div>

    <!-- Stock In Modal -->
    <div class="modal" id="stockInModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Stock In</h2>
                <div class="close-modal" onclick="closeModal('stockInModal')">&times;</div>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label>Product</label>
                    <select class="form-control" name="produk_id" required>
                        <option value="">Select Product</option>
                        <?php 
                        mysqli_data_seek($query_produk, 0);
                        while($produk = mysqli_fetch_assoc($query_produk)): 
                        ?>
                        <option value="<?php echo $produk['id']; ?>">
                            <?php echo htmlspecialchars($produk['nama_produk']); ?> (Stock: <?php echo $produk['stok']; ?>)
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Quantity</label>
                    <input type="number" class="form-control" name="jumlah" min="1" required>
                </div>

                <div class="form-group">
                    <label>Purchase Price</label>
                    <input type="text" class="form-control" name="harga_beli" onkeyup="formatRupiah(this)" required>
                </div>

                <div class="form-group">
                    <label>Supplier</label>
                    <select class="form-control" name="supplier_id">
                        <option value="">Select Supplier (Optional)</option>
                        <?php 
                        mysqli_data_seek($query_supplier, 0);
                        while($supplier = mysqli_fetch_assoc($query_supplier)): 
                        ?>
                        <option value="<?php echo $supplier['id']; ?>">
                            <?php echo htmlspecialchars($supplier['nama_supplier']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Notes</label>
                    <textarea class="form-control" name="keterangan" placeholder="Optional notes"></textarea>
                </div>

                <div class="modal-footer">
                    <button type="submit" name="stock_in" class="btn-save">Add Stock</button>
                    <button type="button" class="btn-cancel" onclick="closeModal('stockInModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Stock Out Modal -->
    <div class="modal" id="stockOutModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Stock Out</h2>
                <div class="close-modal" onclick="closeModal('stockOutModal')">&times;</div>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label>Product</label>
                    <select class="form-control" name="produk_id" id="stockOutProduct" required>
                        <option value="">Select Product</option>
                        <?php 
                        mysqli_data_seek($query_produk, 0);
                        while($produk = mysqli_fetch_assoc($query_produk)): 
                        ?>
                        <option value="<?php echo $produk['id']; ?>" data-stock="<?php echo $produk['stok']; ?>">
                            <?php echo htmlspecialchars($produk['nama_produk']); ?> (Stock: <?php echo $produk['stok']; ?>)
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Quantity</label>
                    <input type="number" class="form-control" name="jumlah" id="stockOutQty" min="1" required>
                </div>

                <div class="form-group">
                    <label>Reason</label>
                    <textarea class="form-control" name="keterangan" placeholder="e.g., Damaged, Expired, Return to supplier" required></textarea>
                </div>

                <div class="modal-footer">
                    <button type="submit" name="stock_out" class="btn-save">Remove Stock</button>
                    <button type="button" class="btn-cancel" onclick="closeModal('stockOutModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Stock Adjust Modal -->
    <div class="modal" id="adjustModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Adjust Stock</h2>
                <div class="close-modal" onclick="closeModal('adjustModal')">&times;</div>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label>Product</label>
                    <select class="form-control" name="produk_id" id="adjustProduct" required>
                        <option value="">Select Product</option>
                        <?php 
                        mysqli_data_seek($query_produk, 0);
                        while($produk = mysqli_fetch_assoc($query_produk)): 
                        ?>
                        <option value="<?php echo $produk['id']; ?>" data-current="<?php echo $produk['stok']; ?>">
                            <?php echo htmlspecialchars($produk['nama_produk']); ?> (Current: <?php echo $produk['stok']; ?>)
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>New Stock Quantity</label>
                    <input type="number" class="form-control" name="stok_baru" id="newStock" min="0" required>
                    <small style="color: var(--text-secondary); display: block; margin-top: 5px;">Current stock: <span id="currentStock">0</span></small>
                </div>

                <div class="form-group">
                    <label>Reason for Adjustment</label>
                    <textarea class="form-control" name="alasan" placeholder="e.g., Stock opname, Correction" required></textarea>
                </div>

                <div class="modal-footer">
                    <button type="submit" name="adjust" class="btn-save">Adjust Stock</button>
                    <button type="button" class="btn-cancel" onclick="closeModal('adjustModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Menu functions
        function toggleMenu() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('overlay').classList.toggle('active');
        }

        function closeMenu() {
            document.getElementById('sidebar').classList.remove('active');
            document.getElementById('overlay').classList.remove('active');
        }

        // Modal functions
        function openStockInModal() {
            document.getElementById('stockInModal').classList.add('active');
        }

        function openStockOutModal() {
            document.getElementById('stockOutModal').classList.add('active');
        }

        function openAdjustModal() {
            document.getElementById('adjustModal').classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        // Format Rupiah
        function formatRupiah(input) {
            let value = input.value.replace(/[^,\d]/g, '').toString();
            let split = value.split(',');
            let sisa = split[0].length % 3;
            let rupiah = split[0].substr(0, sisa);
            let ribuan = split[0].substr(sisa).match(/\d{3}/gi);

            if (ribuan) {
                let separator = sisa ? '.' : '';
                rupiah += separator + ribuan.join('.');
            }

            rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
            input.value = rupiah;
        }

        // Stock out validation
        document.getElementById('stockOutProduct')?.addEventListener('change', function() {
            const selected = this.options[this.selectedIndex];
            const stock = selected.dataset.stock;
            const qtyInput = document.getElementById('stockOutQty');
            qtyInput.max = stock;
            qtyInput.value = 1;
        });

        // Adjust stock - show current stock
        document.getElementById('adjustProduct')?.addEventListener('change', function() {
            const selected = this.options[this.selectedIndex];
            const currentStock = selected.dataset.current;
            document.getElementById('currentStock').textContent = currentStock;
            document.getElementById('newStock').value = currentStock;
        });

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }

        // Close menu on link click
        document.querySelectorAll('.menu-item, .logout-mobile').forEach(item => {
            item.addEventListener('click', function() {
                if (window.innerWidth < 768) closeMenu();
            });
        });

        // Auto-hide alerts
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>