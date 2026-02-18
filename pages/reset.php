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

// ONLY ADMIN can access
if($role != 'admin') {
    header("Location: dashboard.php");
    exit();
}

// Kalau user minta reset via POST
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Konfirmasi password admin untuk keamanan ekstra
    $password = md5($_POST['password']);
    
    $check = mysqli_query($conn, "SELECT id FROM users WHERE id='$user_id' AND password='$password'");
    
    if(mysqli_num_rows($check) == 0) {
        $error = "Password admin salah!";
    } else {
        // Mulai reset data
        mysqli_begin_transaction($conn);
        
        try {
            // 1. Hapus semua transaksi
            mysqli_query($conn, "DELETE FROM detail_transaksi");
            mysqli_query($conn, "DELETE FROM transaksi");
            
            // 2. Hapus semua stok masuk/keluar
            mysqli_query($conn, "DELETE FROM stok_masuk");
            mysqli_query($conn, "DELETE FROM stok_keluar");
            
            // 3. Reset stok produk ke 0
            mysqli_query($conn, "UPDATE produk SET stok = 0");
            
            // 4. Hapus log aktivitas
            mysqli_query($conn, "DELETE FROM log_aktivitas");
            
            // 5. TAPI jangan hapus users, produk, kategori, supplier
            // Biar data master tetap ada
            
            // 6. Reset pengaturan ke default
            mysqli_query($conn, "UPDATE pengaturan SET 
                nama_toko = 'Majoo POS',
                alamat = '',
                telepon = '',
                email = '',
                pajak = 0,
                diskon_default = 0,
                receipt_header = 'Terima Kasih!',
                receipt_footer = 'Silahkan datang kembali'
                WHERE id = 1");
            
            // 7. Log aktivitas reset
            $log = "INSERT INTO log_aktivitas (user_id, aktivitas, detail) 
                    VALUES ('$user_id', 'System Reset', 'All transaction data has been reset')";
            mysqli_query($conn, $log);
            
            mysqli_commit($conn);
            
            $success = "✅ Semua data transaksi berhasil direset!";
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Ambil statistik sebelum reset (buat info)
$total_transaksi = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM transaksi"))['total'];
$total_produk = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM produk"))['total'];
$total_kategori = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM kategori"))['total'];
$total_user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM users"))['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset System - majoo POS</title>
    
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
            --text-primary: #FFFFFF;
            --text-secondary: #A0A0A0;
            --border: #2A2A2A;
            --danger: #FF4D4D;
            --danger-glow: rgba(255, 77, 77, 0.3);
            --warning: #FFB800;
        }

        body {
            font-family: 'Space Grotesk', sans-serif;
            background: var(--primary);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .reset-container {
            max-width: 600px;
            width: 100%;
        }

        .reset-card {
            background: var(--secondary);
            border: 1px solid var(--border);
            border-radius: 40px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }

        .danger-icon {
            width: 100px;
            height: 100px;
            background: rgba(255, 77, 77, 0.1);
            border: 2px solid var(--danger);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            font-size: 48px;
            color: var(--danger);
        }

        h1 {
            font-size: 32px;
            font-weight: 700;
            text-align: center;
            margin-bottom: 10px;
            color: var(--danger);
        }

        .warning-text {
            text-align: center;
            color: var(--text-secondary);
            margin-bottom: 30px;
            font-size: 16px;
        }

        .info-box {
            background: var(--primary);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .info-box h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--warning);
        }

        .info-box h3 i {
            color: var(--warning);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-item {
            background: var(--secondary);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 15px;
            text-align: center;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--accent);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .danger-list {
            list-style: none;
            margin-top: 15px;
        }

        .danger-list li {
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-secondary);
        }

        .danger-list li i {
            color: var(--danger);
            font-size: 14px;
        }

        .danger-list li:last-child {
            border-bottom: none;
        }

        .safe-list {
            list-style: none;
            margin-top: 15px;
        }

        .safe-list li {
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-secondary);
        }

        .safe-list li i {
            color: var(--accent);
            font-size: 14px;
        }

        .safe-list li:last-child {
            border-bottom: none;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }

        .form-control {
            width: 100%;
            padding: 16px 20px;
            background: var(--primary);
            border: 1px solid var(--border);
            border-radius: 16px;
            color: var(--text-primary);
            font-family: 'Space Grotesk', sans-serif;
            font-size: 16px;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--danger);
        }

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

        .btn-reset {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, var(--danger), #ff0000);
            color: white;
            border: none;
            border-radius: 40px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .btn-reset:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px var(--danger-glow);
        }

        .btn-cancel {
            width: 100%;
            padding: 16px;
            background: var(--primary);
            color: var(--text-primary);
            border: 1px solid var(--border);
            border-radius: 40px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-cancel:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        .footer-note {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
            color: var(--text-secondary);
        }

        .footer-note i {
            color: var(--accent);
        }

        @media (max-width: 480px) {
            .reset-card {
                padding: 25px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            h1 {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-card">
            <div class="danger-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>

            <h1>Reset System</h1>
            <p class="warning-text">This action cannot be undone. Proceed with caution!</p>

            <?php if(isset($success)): ?>
            <div class="alert">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
            <?php endif; ?>

            <?php if(isset($error)): ?>
            <div class="alert error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
            <?php endif; ?>

            <!-- Info Box -->
            <div class="info-box">
                <h3><i class="fas fa-chart-bar"></i> Current Statistics</h3>
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo number_format($total_transaksi); ?></div>
                        <div class="stat-label">Transactions</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo number_format($total_produk); ?></div>
                        <div class="stat-label">Products</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo number_format($total_kategori); ?></div>
                        <div class="stat-label">Categories</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo number_format($total_user); ?></div>
                        <div class="stat-label">Users</div>
                    </div>
                </div>
            </div>

            <!-- What will be DELETED -->
            <div class="info-box" style="border-color: var(--danger);">
                <h3 style="color: var(--danger);"><i class="fas fa-trash-alt"></i> Will be DELETED:</h3>
                <ul class="danger-list">
                    <li><i class="fas fa-times-circle"></i> All transactions history</li>
                    <li><i class="fas fa-times-circle"></i> All transaction details</li>
                    <li><i class="fas fa-times-circle"></i> Stock in/out history</li>
                    <li><i class="fas fa-times-circle"></i> Activity logs</li>
                    <li><i class="fas fa-times-circle"></i> Product stock quantities (reset to 0)</li>
                </ul>
            </div>

            <!-- What will be KEPT -->
            <div class="info-box">
                <h3 style="color: var(--accent);"><i class="fas fa-check-circle"></i> Will be KEPT:</h3>
                <ul class="safe-list">
                    <li><i class="fas fa-check-circle"></i> All user accounts</li>
                    <li><i class="fas fa-check-circle"></i> Product master data</li>
                    <li><i class="fas fa-check-circle"></i> Categories</li>
                    <li><i class="fas fa-check-circle"></i> Suppliers</li>
                    <li><i class="fas fa-check-circle"></i> Store settings</li>
                </ul>
            </div>

            <!-- Reset Form -->
            <form method="POST" onsubmit="return confirmReset()">
                <div class="form-group">
                    <label>Enter your password to confirm:</label>
                    <input type="password" name="password" class="form-control" placeholder="Your password" required>
                </div>

                <button type="submit" class="btn-reset">
                    <i class="fas fa-exclamation-triangle"></i> RESET ALL DATA
                </button>

                <a href="pengaturan.php" class="btn-cancel">
                    <i class="fas fa-arrow-left"></i> Cancel & Go Back
                </a>
            </form>

            <div class="footer-note">
                <i class="fas fa-shield-alt"></i> This action is logged for security purposes
            </div>
        </div>
    </div>

    <script>
        function confirmReset() {
            if(confirm('⚠️ WARNING: This will DELETE all transactions and reset stock to 0!\n\nAre you ABSOLUTELY sure?')) {
                if(confirm('Type "RESET" to confirm this destructive action')) {
                    return true;
                }
            }
            return false;
        }
    </script>
</body>
</html>