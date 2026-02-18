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

// Handle Profile Update
if(isset($_POST['update_profile'])) {
    $nama = mysqli_real_escape_string($conn, $_POST['nama_lengkap']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $no_telepon = mysqli_real_escape_string($conn, $_POST['no_telepon']);
    
    $query = "UPDATE users SET 
              nama_lengkap='$nama', 
              email='$email', 
              no_telepon='$no_telepon' 
              WHERE id='$user_id'";
    
    if(mysqli_query($conn, $query)) {
        $_SESSION['nama_lengkap'] = $nama;
        $success_profile = "Profile updated successfully!";
    } else {
        $error_profile = "Error: " . mysqli_error($conn);
    }
}

// Handle Password Change
if(isset($_POST['change_password'])) {
    $current = md5($_POST['current_password']);
    $new = md5($_POST['new_password']);
    $confirm = md5($_POST['confirm_password']);
    
    // Check current password
    $check = mysqli_query($conn, "SELECT id FROM users WHERE id='$user_id' AND password='$current'");
    if(mysqli_num_rows($check) == 0) {
        $error_password = "Current password is incorrect!";
    } elseif($_POST['new_password'] != $_POST['confirm_password']) {
        $error_password = "New passwords do not match!";
    } else {
        $query = "UPDATE users SET password='$new' WHERE id='$user_id'";
        if(mysqli_query($conn, $query)) {
            $success_password = "Password changed successfully!";
        } else {
            $error_password = "Error: " . mysqli_error($conn);
        }
    }
}

// Handle Store Settings
if(isset($_POST['update_store'])) {
    $nama_toko = mysqli_real_escape_string($conn, $_POST['nama_toko']);
    $alamat = mysqli_real_escape_string($conn, $_POST['alamat_toko']);
    $telepon = mysqli_real_escape_string($conn, $_POST['telepon_toko']);
    $email = mysqli_real_escape_string($conn, $_POST['email_toko']);
    $pajak = $_POST['pajak'];
    $diskon = $_POST['diskon_default'];
    
    // Check if settings exist
    $check = mysqli_query($conn, "SELECT id FROM pengaturan WHERE id=1");
    if(mysqli_num_rows($check) > 0) {
        $query = "UPDATE pengaturan SET 
                  nama_toko='$nama_toko', 
                  alamat='$alamat', 
                  telepon='$telepon', 
                  email='$email', 
                  pajak='$pajak', 
                  diskon_default='$diskon' 
                  WHERE id=1";
    } else {
        $query = "INSERT INTO pengaturan (id, nama_toko, alamat, telepon, email, pajak, diskon_default) 
                  VALUES (1, '$nama_toko', '$alamat', '$telepon', '$email', '$pajak', '$diskon')";
    }
    
    if(mysqli_query($conn, $query)) {
        $success_store = "Store settings updated!";
    } else {
        $error_store = "Error: " . mysqli_error($conn);
    }
}

// Handle Receipt Settings
if(isset($_POST['update_receipt'])) {
    $header = mysqli_real_escape_string($conn, $_POST['receipt_header']);
    $footer = mysqli_real_escape_string($conn, $_POST['receipt_footer']);
    $show_pajak = isset($_POST['show_pajak']) ? 1 : 0;
    $show_diskon = isset($_POST['show_diskon']) ? 1 : 0;
    $auto_print = isset($_POST['auto_print']) ? 1 : 0;
    
    // Check if settings exist
    $check = mysqli_query($conn, "SELECT id FROM pengaturan WHERE id=1");
    if(mysqli_num_rows($check) > 0) {
        $query = "UPDATE pengaturan SET 
                  receipt_header='$header', 
                  receipt_footer='$footer', 
                  show_pajak='$show_pajak', 
                  show_diskon='$show_diskon', 
                  auto_print='$auto_print' 
                  WHERE id=1";
    } else {
        $query = "INSERT INTO pengaturan (id, receipt_header, receipt_footer, show_pajak, show_diskon, auto_print) 
                  VALUES (1, '$header', '$footer', '$show_pajak', '$show_diskon', '$auto_print')";
    }
    
    if(mysqli_query($conn, $query)) {
        $success_receipt = "Receipt settings updated!";
    } else {
        $error_receipt = "Error: " . mysqli_error($conn);
    }
}

// Handle Backup
if(isset($_POST['backup'])) {
    // Get all tables
    $tables = array('users', 'kategori', 'produk', 'supplier', 'transaksi', 'detail_transaksi', 'stok_masuk', 'stok_keluar', 'log_aktivitas', 'pengaturan');
    
    $backup = "-- majoo POS Database Backup\n";
    $backup .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $backup .= "-- -----------------------------------\n\n";
    
    foreach($tables as $table) {
        $result = mysqli_query($conn, "SELECT * FROM $table");
        $num_fields = mysqli_num_fields($result);
        
        $backup .= "DROP TABLE IF EXISTS `$table`;\n";
        $row = mysqli_fetch_row(mysqli_query($conn, "SHOW CREATE TABLE $table"));
        $backup .= $row[1] . ";\n\n";
        
        if(mysqli_num_rows($result) > 0) {
            while($row = mysqli_fetch_row($result)) {
                $backup .= "INSERT INTO `$table` VALUES(";
                for($j=0; $j<$num_fields; $j++) {
                    $row[$j] = addslashes($row[$j]);
                    $row[$j] = str_replace("\n", "\\n", $row[$j]);
                    if(isset($row[$j])) {
                        $backup .= '"' . $row[$j] . '"';
                    } else {
                        $backup .= '""';
                    }
                    if($j < ($num_fields-1)) {
                        $backup .= ',';
                    }
                }
                $backup .= ");\n";
            }
        }
        $backup .= "\n\n";
    }
    
    $filename = 'backup_majoo_' . date('Y-m-d_H-i-s') . '.sql';
    header('Content-Type: application/octet-stream');
    header("Content-Transfer-Encoding: Binary");
    header("Content-disposition: attachment; filename=\"$filename\"");
    echo $backup;
    exit();
}

// Get current settings with error handling
$store = array(
    'nama_toko' => 'Majoo POS',
    'alamat' => '',
    'telepon' => '',
    'email' => '',
    'pajak' => 0,
    'diskon_default' => 0,
    'receipt_header' => 'Terima Kasih!',
    'receipt_footer' => 'Silahkan datang kembali',
    'show_pajak' => 1,
    'show_diskon' => 1,
    'auto_print' => 0
);

$settings = mysqli_query($conn, "SELECT * FROM pengaturan WHERE id=1");
if($settings && mysqli_num_rows($settings) > 0) {
    $store = array_merge($store, mysqli_fetch_assoc($settings));
}

// Get current user data
$user_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id='$user_id'"));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Settings - Kasir Majoo</title>
    
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
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(135deg, #fff, var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 5px;
        }

        .page-header p {
            color: var(--text-secondary);
            font-size: 14px;
        }

        /* Settings Grid */
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
        }

        /* Settings Card */
        .settings-card {
            background: var(--secondary);
            border: 1px solid var(--border);
            border-radius: 30px;
            padding: 25px;
            transition: all 0.3s;
        }

        .settings-card:hover {
            border-color: var(--accent);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border);
        }

        .card-icon {
            width: 50px;
            height: 50px;
            background: rgba(0, 255, 178, 0.1);
            border: 1px solid rgba(0, 255, 178, 0.3);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent);
            font-size: 22px;
        }

        .card-header h2 {
            font-size: 18px;
            font-weight: 600;
        }

        .card-header p {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 2px;
        }

        /* Form */
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
            border-radius: 16px;
            padding: 14px 18px;
            color: var(--text-primary);
            font-family: 'Space Grotesk', sans-serif;
            font-size: 14px;
            transition: all 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent);
        }

        textarea.form-control {
            min-height: 80px;
            resize: vertical;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--accent);
            cursor: pointer;
        }

        .checkbox-group label {
            margin-bottom: 0;
            cursor: pointer;
        }

        .btn-save {
            width: 100%;
            background: var(--accent);
            color: var(--primary);
            border: none;
            border-radius: 40px;
            padding: 14px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
            margin-top: 10px;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px var(--accent-glow);
        }

        .btn-danger {
            width: 100%;
            background: rgba(255, 77, 77, 0.1);
            color: var(--danger);
            border: 1px solid rgba(255, 77, 77, 0.3);
            border-radius: 40px;
            padding: 14px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn-danger:hover {
            background: var(--danger);
            color: white;
            transform: translateY(-2px);
        }

        /* Alert */
        .alert {
            background: rgba(0, 255, 178, 0.1);
            border: 1px solid var(--accent);
            color: var(--accent);
            padding: 15px 20px;
            border-radius: 16px;
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

        /* Info Box */
        .info-box {
            background: var(--primary);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 15px;
            margin-top: 20px;
        }

        .info-box h4 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-box h4 i {
            color: var(--accent);
        }

        .info-box p {
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 5px;
        }

        /* Divider */
        .divider {
            height: 1px;
            background: var(--border);
            margin: 25px 0;
        }

        /* Responsive */
        @media (min-width: 768px) {
            .mobile-top-nav {
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

            .settings-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .card-header {
                flex-direction: column;
                text-align: center;
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
            <a href="pengaturan.php" class="menu-item active">
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
            <h1>Settings</h1>
            <p>Configure your POS system</p>
        </div>

        <!-- Settings Grid -->
        <div class="settings-grid">
            <!-- Profile Settings -->
            <div class="settings-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div>
                        <h2>Profile Settings</h2>
                        <p>Update your personal information</p>
                    </div>
                </div>

                <?php if(isset($success_profile)): ?>
                <div class="alert">
                    <i class="fas fa-check-circle"></i> <?php echo $success_profile; ?>
                </div>
                <?php endif; ?>

                <?php if(isset($error_profile)): ?>
                <div class="alert error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_profile; ?>
                </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" class="form-control" name="nama_lengkap" value="<?php echo htmlspecialchars($user_data['nama_lengkap']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user_data['email']); ?>">
                    </div>

                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" class="form-control" name="no_telepon" value="<?php echo htmlspecialchars($user_data['no_telepon']); ?>">
                    </div>

                    <button type="submit" name="update_profile" class="btn-save">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                </form>
            </div>

            <!-- Password Settings -->
            <div class="settings-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <div>
                        <h2>Change Password</h2>
                        <p>Update your login password</p>
                    </div>
                </div>

                <?php if(isset($success_password)): ?>
                <div class="alert">
                    <i class="fas fa-check-circle"></i> <?php echo $success_password; ?>
                </div>
                <?php endif; ?>

                <?php if(isset($error_password)): ?>
                <div class="alert error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_password; ?>
                </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" class="form-control" name="current_password" required>
                    </div>

                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" class="form-control" name="new_password" required>
                    </div>

                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" class="form-control" name="confirm_password" required>
                    </div>

                    <button type="submit" name="change_password" class="btn-save">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </form>
            </div>

            <!-- Store Settings -->
            <div class="settings-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-store"></i>
                    </div>
                    <div>
                        <h2>Store Information</h2>
                        <p>Configure your store details</p>
                    </div>
                </div>

                <?php if(isset($success_store)): ?>
                <div class="alert">
                    <i class="fas fa-check-circle"></i> <?php echo $success_store; ?>
                </div>
                <?php endif; ?>

                <?php if(isset($error_store)): ?>
                <div class="alert error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_store; ?>
                </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label>Store Name</label>
                        <input type="text" class="form-control" name="nama_toko" value="<?php echo htmlspecialchars($store['nama_toko']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Address</label>
                        <textarea class="form-control" name="alamat_toko"><?php echo htmlspecialchars($store['alamat']); ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="text" class="form-control" name="telepon_toko" value="<?php echo htmlspecialchars($store['telepon']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" class="form-control" name="email_toko" value="<?php echo htmlspecialchars($store['email']); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Tax (%)</label>
                            <input type="number" class="form-control" name="pajak" value="<?php echo $store['pajak']; ?>" step="0.1" min="0">
                        </div>
                        <div class="form-group">
                            <label>Default Discount (%)</label>
                            <input type="number" class="form-control" name="diskon_default" value="<?php echo $store['diskon_default']; ?>" step="0.1" min="0">
                        </div>
                    </div>

                    <button type="submit" name="update_store" class="btn-save">
                        <i class="fas fa-save"></i> Update Store
                    </button>
                </form>
            </div>

            <!-- Receipt Settings -->
            <div class="settings-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <div>
                        <h2>Receipt Settings</h2>
                        <p>Customize your receipts</p>
                    </div>
                </div>

                <?php if(isset($success_receipt)): ?>
                <div class="alert">
                    <i class="fas fa-check-circle"></i> <?php echo $success_receipt; ?>
                </div>
                <?php endif; ?>

                <?php if(isset($error_receipt)): ?>
                <div class="alert error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_receipt; ?>
                </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label>Receipt Header</label>
                        <input type="text" class="form-control" name="receipt_header" value="<?php echo htmlspecialchars($store['receipt_header']); ?>">
                    </div>

                    <div class="form-group">
                        <label>Receipt Footer</label>
                        <input type="text" class="form-control" name="receipt_footer" value="<?php echo htmlspecialchars($store['receipt_footer']); ?>">
                    </div>

                    <div class="checkbox-group">
                        <input type="checkbox" name="show_pajak" id="show_pajak" <?php echo ($store['show_pajak']) ? 'checked' : ''; ?>>
                        <label for="show_pajak">Show tax on receipt</label>
                    </div>

                    <div class="checkbox-group">
                        <input type="checkbox" name="show_diskon" id="show_diskon" <?php echo ($store['show_diskon']) ? 'checked' : ''; ?>>
                        <label for="show_diskon">Show discount on receipt</label>
                    </div>

                    <div class="checkbox-group">
                        <input type="checkbox" name="auto_print" id="auto_print" <?php echo ($store['auto_print']) ? 'checked' : ''; ?>>
                        <label for="auto_print">Auto-print receipt after transaction</label>
                    </div>

                    <button type="submit" name="update_receipt" class="btn-save">
                        <i class="fas fa-save"></i> Update Receipt
                    </button>
                </form>
            </div>

            <!-- Backup & Restore -->
            <div class="settings-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-database"></i>
                    </div>
                    <div>
                        <h2>Backup & Restore</h2>
                        <p>Secure your data</p>
                    </div>
                </div>

                <div class="info-box">
                    <h4><i class="fas fa-info-circle"></i> Database Backup</h4>
                    <p>Create a backup of your entire database including:</p>
                    <p>• Products & Categories</p>
                    <p>• Transactions & History</p>
                    <p>• Employees & Settings</p>
                </div>

                <form method="POST">
                    <button type="submit" name="backup" class="btn-save" style="margin-bottom: 10px;">
                        <i class="fas fa-download"></i> Download Backup
                    </button>
                </form>

                <div class="divider"></div>

                <div class="info-box" style="border-color: rgba(255, 77, 77, 0.3);">
                    <h4 style="color: var(--danger);"><i class="fas fa-exclamation-triangle"></i> Danger Zone</h4>
                    <p>Reset all data to factory settings. This action cannot be undone.</p>
                </div>

                <button class="btn-danger" onclick="confirmReset()">
                    <i class="fas fa-trash-alt"></i> Reset All Data
                </button>
            </div>

            <!-- System Info -->
            <div class="settings-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-server"></i>
                    </div>
                    <div>
                        <h2>System Information</h2>
                        <p>About your POS system</p>
                    </div>
                </div>

                <div class="info-box">
                    <h4><i class="fas fa-tag"></i> Version</h4>
                    <p>majoo POS Enterprise v3.0.0</p>
                </div>

                <div class="info-box">
                    <h4><i class="fas fa-calendar"></i> Last Update</h4>
                    <p><?php echo date('d F Y'); ?></p>
                </div>

                <div class="info-box">
                    <h4><i class="fas fa-database"></i> Database</h4>
                    <p>MySQL <?php echo mysqli_get_server_info($conn); ?></p>
                </div>

                <div class="info-box">
                    <h4><i class="fas fa-clock"></i> Server Time</h4>
                    <p><?php echo date('H:i:s'); ?></p>
                </div>

                <div class="info-box">
                    <h4><i class="fas fa-globe"></i> PHP Version</h4>
                    <p><?php echo phpversion(); ?></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Menu functions
        function toggleMenu() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('overlay').classList.toggle('active');
            document.body.style.overflow = document.getElementById('sidebar').classList.contains('active') ? 'hidden' : '';
        }

        function closeMenu() {
            document.getElementById('sidebar').classList.remove('active');
            document.getElementById('overlay').classList.remove('active');
            document.body.style.overflow = '';
        }

        // Confirm reset
        function confirmReset() {
            if(confirm('⚠️ WARNING: This will delete ALL data! Are you absolutely sure?')) {
                if(confirm('Type "RESET" to confirm this destructive action')) {
                    window.location.href = 'reset.php';
                }
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

        // Touch effects for mobile
        if('ontouchstart' in window) {
            document.querySelectorAll('.btn-save, .btn-danger').forEach(element => {
                element.addEventListener('touchstart', function() {
                    this.style.opacity = '0.7';
                });
                element.addEventListener('touchend', function() {
                    this.style.opacity = '1';
                });
            });
        }
    </script>
</body>
</html>