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

// Handle Add Product
if(isset($_POST['add'])) {
    $kode_produk = mysqli_real_escape_string($conn, $_POST['kode_produk']);
    $nama_produk = mysqli_real_escape_string($conn, $_POST['nama_produk']);
    $kategori_id = $_POST['kategori_id'];
    $harga_beli = str_replace('.', '', $_POST['harga_beli']);
    $harga_jual = str_replace('.', '', $_POST['harga_jual']);
    $stok = $_POST['stok'];
    $stok_minimum = $_POST['stok_minimum'];
    $satuan = $_POST['satuan'];
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    
    // Validasi harga beli tidak boleh 0
    if($harga_beli <= 0) {
        $error = "Harga beli harus lebih dari 0!";
    } else {
        $query = "INSERT INTO produk (kode_produk, nama_produk, kategori_id, harga_beli, harga_jual, stok, stok_minimum, satuan, deskripsi) 
                  VALUES ('$kode_produk', '$nama_produk', '$kategori_id', '$harga_beli', '$harga_jual', '$stok', '$stok_minimum', '$satuan', '$deskripsi')";
        
        if(mysqli_query($conn, $query)) {
            $success = "Product added successfully!";
            header("Location: produk.php");
            exit();
        } else {
            $error = "Error: " . mysqli_error($conn);
        }
    }
}

// Handle Edit Product
if(isset($_POST['edit'])) {
    $id = $_POST['id'];
    $kode_produk = mysqli_real_escape_string($conn, $_POST['kode_produk']);
    $nama_produk = mysqli_real_escape_string($conn, $_POST['nama_produk']);
    $kategori_id = $_POST['kategori_id'];
    $harga_beli = str_replace('.', '', $_POST['harga_beli']);
    $harga_jual = str_replace('.', '', $_POST['harga_jual']);
    $stok = $_POST['stok'];
    $stok_minimum = $_POST['stok_minimum'];
    $satuan = $_POST['satuan'];
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    
    // Validasi harga beli tidak boleh 0
    if($harga_beli <= 0) {
        $error = "Harga beli harus lebih dari 0!";
    } else {
        $query = "UPDATE produk SET 
                  kode_produk='$kode_produk', 
                  nama_produk='$nama_produk', 
                  kategori_id='$kategori_id', 
                  harga_beli='$harga_beli', 
                  harga_jual='$harga_jual', 
                  stok='$stok', 
                  stok_minimum='$stok_minimum', 
                  satuan='$satuan', 
                  deskripsi='$deskripsi' 
                  WHERE id='$id'";
        
        if(mysqli_query($conn, $query)) {
            $success = "Product updated successfully!";
            header("Location: produk.php");
            exit();
        } else {
            $error = "Error: " . mysqli_error($conn);
        }
    }
}

// Handle Delete Product
if(isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $query = "DELETE FROM produk WHERE id='$id'";
    
    if(mysqli_query($conn, $query)) {
        $success = "Product deleted successfully!";
        header("Location: produk.php");
        exit();
    } else {
        $error = "Error: " . mysqli_error($conn);
    }
}

// Handle Toggle Status
if(isset($_GET['toggle'])) {
    $id = $_GET['toggle'];
    $query = "UPDATE produk SET is_active = NOT is_active WHERE id='$id'";
    
    if(mysqli_query($conn, $query)) {
        $success = "Product status updated!";
        header("Location: produk.php");
        exit();
    } else {
        $error = "Error: " . mysqli_error($conn);
    }
}

// Get all products with category
$query_produk = mysqli_query($conn, "SELECT p.*, k.nama_kategori 
    FROM produk p 
    LEFT JOIN kategori k ON p.kategori_id = k.id 
    ORDER BY p.created_at DESC");

// Get categories for dropdown
$query_kategori = mysqli_query($conn, "SELECT * FROM kategori ORDER BY nama_kategori ASC");

// Ambil data produk untuk diedit
$edit_product = null;
if(isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $edit_query = mysqli_query($conn, "SELECT * FROM produk WHERE id = '$edit_id'");
    if(mysqli_num_rows($edit_query) > 0) {
        $edit_product = mysqli_fetch_assoc($edit_query);
    }
}

// Hitung total inventory value untuk ditampilkan di footer (opsional)
$query_total_value = mysqli_query($conn, "SELECT SUM(stok * harga_beli) as total FROM produk");
$total_inventory = 0;
if($query_total_value && mysqli_num_rows($query_total_value) > 0) {
    $row = mysqli_fetch_assoc($query_total_value);
    $total_inventory = $row['total'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Products - Kasir Majoo</title>
    
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

        /* Filters */
        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .search-box {
            flex: 1;
            background: var(--secondary);
            border: 1px solid var(--border);
            border-radius: 40px;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .search-box i {
            color: var(--text-secondary);
        }

        .search-box input {
            flex: 1;
            background: none;
            border: none;
            color: var(--text-primary);
            font-family: 'Space Grotesk', sans-serif;
            font-size: 14px;
        }

        .search-box input:focus {
            outline: none;
        }

        .filter-select {
            background: var(--secondary);
            border: 1px solid var(--border);
            border-radius: 40px;
            padding: 14px 20px;
            color: var(--text-primary);
            font-family: 'Space Grotesk', sans-serif;
            font-size: 14px;
            min-width: 150px;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--accent);
        }

        /* Products Table */
        .table-container {
            background: var(--secondary);
            border: 1px solid var(--border);
            border-radius: 30px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        th {
            text-align: left;
            padding: 20px;
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border);
        }

        td {
            padding: 16px 20px;
            color: var(--text-primary);
            font-size: 14px;
            border-bottom: 1px solid var(--border);
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background: var(--primary);
        }

        .product-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .product-image {
            width: 40px;
            height: 40px;
            background: var(--primary);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent);
            font-size: 18px;
            border: 1px solid var(--border);
        }

        .product-name {
            font-weight: 600;
        }

        .product-sku {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .badge {
            padding: 6px 12px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .badge.active {
            background: rgba(0, 255, 178, 0.1);
            color: var(--success);
            border: 1px solid rgba(0, 255, 178, 0.2);
        }

        .badge.inactive {
            background: rgba(255, 77, 77, 0.1);
            color: var(--danger);
            border: 1px solid rgba(255, 77, 77, 0.2);
        }

        .badge.low-stock {
            background: rgba(255, 184, 0, 0.1);
            color: var(--warning);
            border: 1px solid rgba(255, 184, 0, 0.2);
        }

        .price {
            font-weight: 600;
            color: var(--accent);
        }

        .actions {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            width: 36px;
            height: 36px;
            background: var(--primary);
            border: 1px solid var(--border);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s;
        }

        .action-btn:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        .action-btn.delete:hover {
            border-color: var(--danger);
            color: var(--danger);
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
            font-size: 30px;
            color: var(--text-secondary);
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .close-modal:hover {
            color: var(--danger);
            background: rgba(255, 77, 77, 0.1);
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

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
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
            transition: all 0.2s;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px var(--accent-glow);
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
            transition: all 0.2s;
        }

        .btn-cancel:hover {
            background: var(--danger);
            color: white;
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

        @media (max-width: 768px) {
            .filters {
                flex-direction: column;
            }

            .filter-select {
                width: 100%;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
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
            <a href="produk.php" class="menu-item active">
                <i class="fas fa-cube"></i> Products
            </a>
            <a href="stok.php" class="menu-item">
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
            <h1>Products Management</h1>
            <button class="btn-primary" onclick="openAddModal()">
                <i class="fas fa-plus"></i> Add New Product
            </button>
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

        <!-- Filters -->
        <div class="filters">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search products...">
            </div>
            <select class="filter-select" id="categoryFilter">
                <option value="all">All Categories</option>
                <?php 
                mysqli_data_seek($query_kategori, 0);
                while($kategori = mysqli_fetch_assoc($query_kategori)): 
                ?>
                <option value="<?php echo $kategori['id']; ?>">
                    <?php echo htmlspecialchars($kategori['nama_kategori']); ?>
                </option>
                <?php endwhile; ?>
            </select>
            <select class="filter-select" id="stockFilter">
                <option value="all">All Stock</option>
                <option value="low">Low Stock</option>
                <option value="out">Out of Stock</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>

        <!-- Products Table -->
        <div class="table-container">
            <table id="productsTable">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Category</th>
                        <th>Purchase Price</th>
                        <th>Selling Price</th>
                        <th>Stock</th>
                        <th>Min Stock</th>
                        <th>Unit</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($query_produk) > 0): ?>
                        <?php while($produk = mysqli_fetch_assoc($query_produk)): ?>
                        <tr data-category="<?php echo $produk['kategori_id']; ?>" 
                            data-stock="<?php echo $produk['stok']; ?>"
                            data-status="<?php echo $produk['is_active'] ? 'active' : 'inactive'; ?>">
                            <td>
                                <div class="product-info">
                                    <div class="product-image">
                                        <i class="fas fa-cube"></i>
                                    </div>
                                    <div>
                                        <div class="product-name"><?php echo htmlspecialchars($produk['nama_produk']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="product-sku"><?php echo htmlspecialchars($produk['kode_produk']); ?></td>
                            <td><?php echo htmlspecialchars($produk['nama_kategori']); ?></td>
                            <td class="price">Rp <?php echo number_format($produk['harga_beli'], 0, ',', '.'); ?></td>
                            <td class="price">Rp <?php echo number_format($produk['harga_jual'], 0, ',', '.'); ?></td>
                            <td>
                                <?php if($produk['stok'] <= $produk['stok_minimum']): ?>
                                <span class="badge low-stock"><?php echo $produk['stok']; ?></span>
                                <?php else: ?>
                                <?php echo $produk['stok']; ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $produk['stok_minimum']; ?></td>
                            <td><?php echo $produk['satuan']; ?></td>
                            <td>
                                <span class="badge <?php echo $produk['is_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $produk['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="actions">
                                    <div class="action-btn" onclick="editProduct(<?php echo $produk['id']; ?>)" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </div>
                                    <div class="action-btn" onclick="toggleStatus(<?php echo $produk['id']; ?>)" title="Toggle Status">
                                        <i class="fas fa-power-off"></i>
                                    </div>
                                    <div class="action-btn delete" onclick="deleteProduct(<?php echo $produk['id']; ?>)" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 50px;">
                                <i class="fas fa-box" style="font-size: 48px; color: var(--border); margin-bottom: 15px;"></i>
                                <p>No products found</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Total Inventory Value Info -->
        <div style="margin-top: 20px; padding: 15px; background: var(--secondary); border-radius: 10px; text-align: right;">
            <span style="color: var(--text-secondary);">Total Inventory Value: </span>
            <span style="color: var(--accent); font-weight: 700; font-size: 18px;">Rp <?php echo number_format($total_inventory, 0, ',', '.'); ?></span>
        </div>
    </div>

    <!-- Floating Action Button -->
    <div class="fab" onclick="openAddModal()">
        <i class="fas fa-plus"></i>
    </div>

    <!-- Add/Edit Modal -->
    <div class="modal <?php echo $edit_product ? 'active' : ''; ?>" id="productModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle"><?php echo $edit_product ? 'Edit Product' : 'Add New Product'; ?></h2>
                <span class="close-modal" onclick="closeModal()">&times;</span>
            </div>
            
            <form method="POST" id="productForm">
                <input type="hidden" name="id" id="productId" value="<?php echo $edit_product['id'] ?? ''; ?>">
                
                <div class="form-group">
                    <label>Product Code (SKU)</label>
                    <input type="text" class="form-control" name="kode_produk" id="kode_produk" 
                           value="<?php echo $edit_product['kode_produk'] ?? ''; ?>" required>
                </div>

                <div class="form-group">
                    <label>Product Name</label>
                    <input type="text" class="form-control" name="nama_produk" id="nama_produk" 
                           value="<?php echo $edit_product['nama_produk'] ?? ''; ?>" required>
                </div>

                <div class="form-group">
                    <label>Category</label>
                    <select class="form-control" name="kategori_id" id="kategori_id" required>
                        <option value="">Select Category</option>
                        <?php 
                        mysqli_data_seek($query_kategori, 0);
                        while($kategori = mysqli_fetch_assoc($query_kategori)): 
                            $selected = ($edit_product && $edit_product['kategori_id'] == $kategori['id']) ? 'selected' : '';
                        ?>
                        <option value="<?php echo $kategori['id']; ?>" <?php echo $selected; ?>>
                            <?php echo htmlspecialchars($kategori['nama_kategori']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Purchase Price</label>
                        <input type="text" class="form-control" name="harga_beli" id="harga_beli" 
                               value="<?php echo $edit_product ? number_format($edit_product['harga_beli'],0,',','.') : ''; ?>" 
                               onkeyup="formatRupiah(this)" required>
                    </div>
                    <div class="form-group">
                        <label>Selling Price</label>
                        <input type="text" class="form-control" name="harga_jual" id="harga_jual" 
                               value="<?php echo $edit_product ? number_format($edit_product['harga_jual'],0,',','.') : ''; ?>" 
                               onkeyup="formatRupiah(this)" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Stock</label>
                        <input type="number" class="form-control" name="stok" id="stok" 
                               value="<?php echo $edit_product['stok'] ?? ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Minimum Stock</label>
                        <input type="number" class="form-control" name="stok_minimum" id="stok_minimum" 
                               value="<?php echo $edit_product['stok_minimum'] ?? ''; ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Unit (pcs, kg, etc)</label>
                    <input type="text" class="form-control" name="satuan" id="satuan" 
                           value="<?php echo $edit_product['satuan'] ?? 'pcs'; ?>" required>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea class="form-control" name="deskripsi" id="deskripsi"><?php echo $edit_product['deskripsi'] ?? ''; ?></textarea>
                </div>

                <div class="modal-footer">
                    <button type="submit" name="<?php echo $edit_product ? 'edit' : 'add'; ?>" id="btnSubmit" class="btn-save">
                        <?php echo $edit_product ? 'Update Product' : 'Save Product'; ?>
                    </button>
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
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
        function openAddModal() {
            document.getElementById('modalTitle').innerText = 'Add New Product';
            document.getElementById('productForm').reset();
            document.getElementById('productId').value = '';
            document.getElementById('btnSubmit').name = 'add';
            document.getElementById('btnSubmit').innerText = 'Save Product';
            document.getElementById('productModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('productModal').classList.remove('active');
            window.location.href = 'produk.php';
        }

        // Edit product
        function editProduct(id) {
            window.location.href = 'produk.php?edit=' + id;
        }

        // Toggle status
        function toggleStatus(id) {
            if(confirm('Toggle product status?')) {
                window.location.href = 'produk.php?toggle=' + id;
            }
        }

        // Delete product
        function deleteProduct(id) {
            if(confirm('Are you sure you want to delete this product?')) {
                window.location.href = 'produk.php?delete=' + id;
            }
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

        // Search and filter
        document.getElementById('searchInput').addEventListener('keyup', filterTable);
        document.getElementById('categoryFilter').addEventListener('change', filterTable);
        document.getElementById('stockFilter').addEventListener('change', filterTable);

        function filterTable() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const category = document.getElementById('categoryFilter').value;
            const stock = document.getElementById('stockFilter').value;
            const rows = document.querySelectorAll('#productsTable tbody tr');

            rows.forEach(row => {
                const productName = row.querySelector('.product-name')?.textContent.toLowerCase() || '';
                const productSku = row.querySelector('.product-sku')?.textContent.toLowerCase() || '';
                const rowCategory = row.dataset.category;
                const rowStock = parseInt(row.dataset.stock);
                const rowStatus = row.dataset.status;

                let matchSearch = productName.includes(search) || productSku.includes(search);
                let matchCategory = category === 'all' || rowCategory === category;
                let matchStock = true;

                if (stock === 'low') {
                    matchStock = rowStock > 0 && rowStock <= 5;
                } else if (stock === 'out') {
                    matchStock = rowStock === 0;
                } else if (stock === 'active') {
                    matchStock = rowStatus === 'active';
                } else if (stock === 'inactive') {
                    matchStock = rowStatus === 'inactive';
                }

                if (matchSearch && matchCategory && matchStock) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('productModal');
            if (event.target === modal) {
                closeModal();
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
            document.querySelectorAl('.alert').forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>