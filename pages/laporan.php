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

// Only admin and owner can access
if($role != 'admin' && $role != 'owner') {
    header("Location: dashboard.php");
    exit();
}

// Get date range from request
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Summary statistics
$query_summary = mysqli_query($conn, "SELECT 
    COUNT(DISTINCT t.id) as total_transactions,
    COALESCE(SUM(t.grand_total), 0) as total_sales,
    COALESCE(AVG(t.grand_total), 0) as average_sale,
    COUNT(DISTINCT DATE(t.created_at)) as active_days
    FROM transaksi t 
    WHERE DATE(t.created_at) BETWEEN '$start_date' AND '$end_date' 
    AND t.status = 'selesai'");

$summary = mysqli_fetch_assoc($query_summary);

// Daily sales stats
$query_daily_stats = mysqli_query($conn, "SELECT 
    DATE(t.created_at) as tanggal,
    COUNT(*) as jumlah_transaksi,
    COALESCE(SUM(t.grand_total), 0) as total
    FROM transaksi t 
    WHERE DATE(t.created_at) BETWEEN '$start_date' AND '$end_date' 
    AND t.status = 'selesai'
    GROUP BY DATE(t.created_at)
    ORDER BY tanggal DESC
    LIMIT 7");

$daily_stats = [];
while($row = mysqli_fetch_assoc($query_daily_stats)) {
    $daily_stats[] = [
        'tanggal' => date('d M Y', strtotime($row['tanggal'])),
        'transaksi' => $row['jumlah_transaksi'],
        'total' => $row['total']
    ];
}

// ========== PAYMENT QUERY - FIXED UNTUK DATA KOSONG ==========
$query_payment_simple = mysqli_query($conn, "SELECT 
    CASE 
        WHEN metode_pembayaran IS NULL OR metode_pembayaran = '' THEN 'cash'
        WHEN LOWER(metode_pembayaran) = 'cash' THEN 'cash'
        WHEN LOWER(metode_pembayaran) = 'tunai' THEN 'cash'
        WHEN LOWER(metode_pembayaran) = 'qris' THEN 'qris'
        WHEN LOWER(metode_pembayaran) = 'transfer' THEN 'transfer'
        ELSE 'cash'
    END as metode,
    COUNT(*) as jumlah,
    COALESCE(SUM(grand_total), 0) as total
    FROM transaksi 
    WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date' 
    AND status = 'selesai'
    GROUP BY metode");

// Inisialisasi array
$payment_counts = [
    'cash' => 0,
    'qris' => 0,
    'transfer' => 0
];

$payment_totals = [
    'cash' => 0,
    'qris' => 0,
    'transfer' => 0
];

// Ambil data
while($row = mysqli_fetch_assoc($query_payment_simple)) {
    $metode = $row['metode'];
    if(isset($payment_counts[$metode])) {
        $payment_counts[$metode] = $row['jumlah'];
        $payment_totals[$metode] = $row['total'];
    }
}

$total_cash = $payment_totals['cash'];
$count_cash = $payment_counts['cash'];

$total_qris = $payment_totals['qris'];
$count_qris = $payment_counts['qris'];

$total_transfer = $payment_totals['transfer'];
$count_transfer = $payment_counts['transfer'];

// ===== DEBUG: Tampilkan hasil di HTML (bisa dihapus nanti) =====
echo "<!-- DEBUG: cash: $total_cash ($count_cash), qris: $total_qris ($count_qris), transfer: $total_transfer ($count_transfer) -->";
// ===============================================================

// Top products
$query_top_products = mysqli_query($conn, "SELECT 
    p.nama_produk,
    k.nama_kategori,
    SUM(dt.jumlah) as total_terjual,
    SUM(dt.subtotal) as total_pendapatan
    FROM detail_transaksi dt
    JOIN produk p ON dt.produk_id = p.id
    JOIN kategori k ON p.kategori_id = k.id
    JOIN transaksi t ON dt.transaksi_id = t.id
    WHERE DATE(t.created_at) BETWEEN '$start_date' AND '$end_date' 
    AND t.status = 'selesai'
    GROUP BY p.id
    ORDER BY total_terjual DESC
    LIMIT 10");

// Daily sales for chart
$query_daily = mysqli_query($conn, "SELECT 
    DATE(t.created_at) as tanggal,
    COUNT(*) as jumlah_transaksi,
    COALESCE(SUM(t.grand_total), 0) as total
    FROM transaksi t 
    WHERE DATE(t.created_at) BETWEEN '$start_date' AND '$end_date' 
    AND t.status = 'selesai'
    GROUP BY DATE(t.created_at)
    ORDER BY tanggal ASC");

$daily_labels = [];
$daily_data = [];
while($row = mysqli_fetch_assoc($query_daily)) {
    $daily_labels[] = date('d M', strtotime($row['tanggal']));
    $daily_data[] = $row['total'];
}

// Transactions list
$query_transactions = mysqli_query($conn, "SELECT 
    t.*, 
    u.nama_lengkap as kasir
    FROM transaksi t
    JOIN users u ON t.user_id = u.id
    WHERE DATE(t.created_at) BETWEEN '$start_date' AND '$end_date' 
    AND t.status = 'selesai'
    ORDER BY t.created_at DESC
    LIMIT 50");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Reports - Kasir Majoo</title>
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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
            --purple: #A78BFA;
            --orange: #FF8A5C;
            --cash: #00FFB2;
            --qris: #3B82F6;
            --transfer: #FFB800;
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

        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* Date Filter */
        .date-filter {
            background: var(--secondary);
            border: 1px solid var(--border);
            border-radius: 60px;
            padding: 5px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 5px;
        }

        .date-input {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: var(--primary);
            border-radius: 40px;
            border: 1px solid var(--border);
        }

        .date-input i {
            color: var(--accent);
            font-size: 14px;
        }

        .date-input input {
            background: none;
            border: none;
            color: var(--text-primary);
            font-family: 'Space Grotesk', sans-serif;
            font-size: 13px;
            width: 120px;
        }

        .date-input input:focus {
            outline: none;
        }

        .date-input input::-webkit-calendar-picker-indicator {
            filter: invert(1);
            opacity: 0.5;
            cursor: pointer;
        }

        .btn-filter {
            background: var(--accent);
            color: var(--primary);
            border: none;
            padding: 10px 24px;
            border-radius: 40px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px var(--accent-glow);
        }

        .btn-export {
            background: var(--primary);
            color: var(--text-primary);
            border: 1px solid var(--border);
            padding: 10px 20px;
            border-radius: 40px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn-export:hover {
            border-color: var(--accent);
            color: var(--accent);
            transform: translateY(-2px);
        }

        /* Stats Cards */
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
            position: relative;
            overflow: hidden;
            transition: all 0.3s;
        }

        .stat-card:hover {
            border-color: var(--accent);
            transform: translateY(-5px);
        }

        .stat-card::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, var(--accent-glow) 0%, transparent 70%);
            opacity: 0.3;
            pointer-events: none;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin-bottom: 15px;
            border: 1px solid var(--border);
            position: relative;
            z-index: 1;
        }

        .stat-icon.purple { 
            background: rgba(167, 139, 250, 0.1); 
            color: var(--purple);
            border-color: rgba(167, 139, 250, 0.3);
        }
        
        .stat-icon.green { 
            background: rgba(0, 255, 178, 0.1); 
            color: var(--success);
            border-color: rgba(0, 255, 178, 0.3);
        }
        
        .stat-icon.blue { 
            background: rgba(59, 130, 246, 0.1); 
            color: var(--info);
            border-color: rgba(59, 130, 246, 0.3);
        }
        
        .stat-icon.orange { 
            background: rgba(255, 138, 92, 0.1); 
            color: var(--orange);
            border-color: rgba(255, 138, 92, 0.3);
        }

        .stat-info {
            position: relative;
            z-index: 1;
        }

        .stat-info h4 {
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 8px;
            letter-spacing: 0.5px;
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
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .stat-info small i {
            color: var(--accent);
            font-size: 10px;
        }

        /* Daily Stats Cards */
        .daily-stats-section {
            margin-bottom: 30px;
        }

        .section-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-title h2 {
            font-size: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title h2 i {
            color: var(--accent);
        }

        .daily-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        .daily-stat-card {
            background: var(--secondary);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.2s;
        }

        .daily-stat-card:hover {
            border-color: var(--accent);
            transform: translateY(-3px);
        }

        .daily-stat-icon {
            width: 50px;
            height: 50px;
            background: var(--primary);
            border: 1px solid var(--border);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent);
            font-size: 22px;
        }

        .daily-stat-info {
            flex: 1;
        }

        .daily-stat-info .date {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: 5px;
        }

        .daily-stat-info .amount {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 3px;
        }

        .daily-stat-info .transactions {
            font-size: 12px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .daily-stat-info .transactions i {
            color: var(--accent);
            font-size: 10px;
        }

        /* Charts Row */
        .charts-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: var(--secondary);
            border: 1px solid var(--border);
            border-radius: 30px;
            padding: 20px;
            transition: all 0.3s;
        }

        .chart-card:hover {
            border-color: var(--accent);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .chart-header h3 {
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .chart-header h3 i {
            color: var(--accent);
        }

        .date-range-badge {
            background: var(--primary);
            border: 1px solid var(--border);
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 11px;
            color: var(--text-secondary);
        }

        .chart-container {
            height: 250px;
            width: 100%;
        }

        /* Payment Methods */
        .payment-list {
            margin-top: 20px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .payment-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px;
            background: var(--primary);
            border: 1px solid var(--border);
            border-radius: 20px;
            transition: all 0.2s;
        }

        .payment-item:hover {
            border-color: var(--accent);
            transform: translateX(5px);
            background: var(--secondary);
        }

        .payment-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .payment-icon {
            width: 48px;
            height: 48px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .payment-icon.cash {
            background: rgba(0, 255, 178, 0.15);
            color: var(--cash);
            border: 1px solid rgba(0, 255, 178, 0.3);
        }

        .payment-icon.qris {
            background: rgba(59, 130, 246, 0.15);
            color: var(--qris);
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .payment-icon.transfer {
            background: rgba(255, 184, 0, 0.15);
            color: var(--transfer);
            border: 1px solid rgba(255, 184, 0, 0.3);
        }

        .payment-details {
            flex: 1;
        }

        .payment-name {
            font-weight: 700;
            font-size: 16px;
            margin-bottom: 4px;
        }

        .payment-count {
            font-size: 11px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .payment-count i {
            color: var(--accent);
            font-size: 8px;
        }

        .payment-stats {
            text-align: right;
        }

        .payment-total {
            font-weight: 700;
            color: var(--accent);
            font-size: 18px;
            margin-bottom: 4px;
        }

        .payment-percentage {
            font-size: 11px;
            color: var(--text-secondary);
            background: var(--secondary);
            padding: 4px 10px;
            border-radius: 30px;
            display: inline-block;
            border: 1px solid var(--border);
        }

        /* Top Products Table */
        .table-card {
            background: var(--secondary);
            border: 1px solid var(--border);
            border-radius: 30px;
            padding: 20px;
            margin-bottom: 30px;
            transition: all 0.3s;
        }

        .table-card:hover {
            border-color: var(--accent);
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .table-header h3 {
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .table-header h3 i {
            color: var(--accent);
        }

        .table-badge {
            background: var(--primary);
            border: 1px solid var(--border);
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 11px;
            color: var(--text-secondary);
        }

        .table-container {
            overflow-x: auto;
            border-radius: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        th {
            text-align: left;
            padding: 16px;
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 12px;
            border-bottom: 1px solid var(--border);
            background: var(--primary);
        }

        td {
            padding: 16px;
            color: var(--text-primary);
            font-size: 13px;
            border-bottom: 1px solid var(--border);
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background: var(--primary);
        }

        .rank {
            width: 36px;
            height: 36px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 700;
        }

        .rank.gold {
            background: rgba(255, 184, 0, 0.15);
            border: 1px solid rgba(255, 184, 0, 0.3);
            color: var(--warning);
        }

        .rank.silver {
            background: rgba(160, 160, 160, 0.15);
            border: 1px solid rgba(160, 160, 160, 0.3);
            color: var(--text-secondary);
        }

        .rank.bronze {
            background: rgba(205, 127, 50, 0.15);
            border: 1px solid rgba(205, 127, 50, 0.3);
            color: #CD7F32;
        }

        .rank.default {
            background: var(--secondary);
            border: 1px solid var(--border);
            color: var(--text-secondary);
        }

        .product-category {
            font-size: 11px;
            color: var(--text-secondary);
            background: var(--primary);
            padding: 4px 10px;
            border-radius: 30px;
            display: inline-block;
            border: 1px solid var(--border);
        }

        .progress-bar {
            width: 80px;
            height: 6px;
            background: var(--border);
            border-radius: 3px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: var(--accent);
            border-radius: 3px;
        }

        /* Transactions Table */
        .transactions-table {
            margin-top: 30px;
        }

        .badge {
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            text-align: center;
            min-width: 80px;
        }

        .badge.cash {
            background: rgba(0, 255, 178, 0.15);
            color: var(--cash);
            border: 1px solid rgba(0, 255, 178, 0.3);
        }

        .badge.qris {
            background: rgba(59, 130, 246, 0.15);
            color: var(--qris);
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .badge.transfer {
            background: rgba(255, 184, 0, 0.15);
            color: var(--transfer);
            border: 1px solid rgba(255, 184, 0, 0.3);
        }

        .invoice-number {
            font-weight: 600;
            color: var(--accent);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state i {
            font-size: 60px;
            color: var(--border);
            margin-bottom: 20px;
        }

        .empty-state p {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 20px;
        }

        .btn-empty {
            background: var(--accent);
            color: var(--primary);
            border: none;
            padding: 12px 30px;
            border-radius: 40px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn-empty:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px var(--accent-glow);
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
        }

        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .charts-row {
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

            .header-actions {
                width: 100%;
            }

            .date-filter {
                width: 100%;
                border-radius: 20px;
                padding: 10px;
            }

            .date-input {
                width: 100%;
            }

            .date-input input {
                width: 100%;
            }

            .btn-filter, .btn-export {
                width: 100%;
                justify-content: center;
            }

            .daily-stats-grid {
                grid-template-columns: 1fr;
            }

            .payment-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .payment-stats {
                width: 100%;
                text-align: left;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
        }

        @media (max-width: 480px) {
            .stat-card p {
                font-size: 22px;
            }

            .payment-total {
                font-size: 16px;
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
            <a href="laporan.php" class="menu-item active">
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
            <h1>Sales Reports</h1>
            <div class="header-actions">
                <!-- Date Filter -->
                <form method="GET" class="date-filter">
                    <div class="date-input">
                        <i class="fas fa-calendar-alt"></i>
                        <input type="date" name="start_date" value="<?php echo $start_date; ?>">
                    </div>
                    <span style="color: var(--text-secondary);">to</span>
                    <div class="date-input">
                        <i class="fas fa-calendar-alt"></i>
                        <input type="date" name="end_date" value="<?php echo $end_date; ?>">
                    </div>
                    <button type="submit" class="btn-filter">
                        <i class="fas fa-search"></i> Apply
                    </button>
                </form>
                
                <button class="btn-export" onclick="exportReport()">
                    <i class="fas fa-download"></i> Export
                </button>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stat-info">
                    <h4>TOTAL TRANSACTIONS</h4>
                    <p><?php echo number_format($summary['total_transactions'] ?: 0); ?></p>
                    <small><i class="fas fa-circle"></i> <?php echo $summary['active_days'] ?: 0; ?> active days</small>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-info">
                    <h4>TOTAL SALES</h4>
                    <p>Rp <?php echo number_format($summary['total_sales'] ?: 0, 0, ',', '.'); ?></p>
                    <small><i class="fas fa-circle"></i> Gross revenue</small>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-info">
                    <h4>AVERAGE SALE</h4>
                    <p>Rp <?php echo number_format($summary['average_sale'] ?: 0, 0, ',', '.'); ?></p>
                    <small><i class="fas fa-circle"></i> Per transaction</small>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-info">
                    <h4>DAILY AVERAGE</h4>
                    <p>Rp <?php 
                        $daily_avg = $summary['active_days'] > 0 ? $summary['total_sales'] / $summary['active_days'] : 0;
                        echo number_format($daily_avg, 0, ',', '.');
                    ?></p>
                    <small><i class="fas fa-circle"></i> Per active day</small>
                </div>
            </div>
        </div>

        <!-- Daily Stats Section -->
        <div class="daily-stats-section">
            <div class="section-title">
                <h2><i class="fas fa-calendar-day"></i> Daily Sales Overview</h2>
                <span class="date-range-badge">Last 7 Days</span>
            </div>
            
            <div class="daily-stats-grid">
                <?php if(!empty($daily_stats)): ?>
                    <?php foreach($daily_stats as $day): ?>
                    <div class="daily-stat-card">
                        <div class="daily-stat-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="daily-stat-info">
                            <div class="date"><?php echo $day['tanggal']; ?></div>
                            <div class="amount">Rp <?php echo number_format($day['total'], 0, ',', '.'); ?></div>
                            <div class="transactions">
                                <i class="fas fa-circle"></i> <?php echo $day['transaksi']; ?> transactions
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: 40px; background: var(--secondary); border-radius: 20px;">
                        <i class="fas fa-calendar-day" style="font-size: 48px; color: var(--border); margin-bottom: 15px;"></i>
                        <p style="color: var(--text-secondary);">No daily data available for this period</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-row">
            <!-- Sales Chart -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3><i class="fas fa-chart-line"></i> Sales Trend</h3>
                    <span class="date-range-badge">
                        <i class="fas fa-calendar"></i> 
                        <?php echo date('d M Y', strtotime($start_date)); ?> - <?php echo date('d M Y', strtotime($end_date)); ?>
                    </span>
                </div>
                <div class="chart-container">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            <!-- Payment Methods -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3><i class="fas fa-credit-card"></i> Payment Methods</h3>
                    <span class="date-range-badge">
                        <i class="fas fa-percent"></i> Distribution
                    </span>
                </div>
                <div class="chart-container">
                    <canvas id="paymentChart"></canvas>
                </div>
                
                <!-- Payment List -->
                <div class="payment-list">
                    <?php 
                    $total_all = $total_cash + $total_qris + $total_transfer;
                    
                    // Tampilkan Cash
                    if($count_cash > 0 || $total_cash > 0):
                        $percentage = $total_all > 0 ? round(($total_cash / $total_all) * 100, 1) : 0;
                    ?>
                    <div class="payment-item">
                        <div class="payment-info">
                            <div class="payment-icon cash">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <div class="payment-details">
                                <div class="payment-name">Cash</div>
                                <div class="payment-count">
                                    <i class="fas fa-circle"></i> <?php echo $count_cash; ?> transactions
                                </div>
                            </div>
                        </div>
                        <div class="payment-stats">
                            <div class="payment-total">Rp <?php echo number_format($total_cash, 0, ',', '.'); ?></div>
                            <div class="payment-percentage"><?php echo $percentage; ?>%</div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if($count_qris > 0 || $total_qris > 0):
                        $percentage = $total_all > 0 ? round(($total_qris / $total_all) * 100, 1) : 0;
                    ?>
                    <div class="payment-item">
                        <div class="payment-info">
                            <div class="payment-icon qris">
                                <i class="fas fa-qrcode"></i>
                            </div>
                            <div class="payment-details">
                                <div class="payment-name">QRIS</div>
                                <div class="payment-count">
                                    <i class="fas fa-circle"></i> <?php echo $count_qris; ?> transactions
                                </div>
                            </div>
                        </div>
                        <div class="payment-stats">
                            <div class="payment-total">Rp <?php echo number_format($total_qris, 0, ',', '.'); ?></div>
                            <div class="payment-percentage"><?php echo $percentage; ?>%</div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if($count_transfer > 0 || $total_transfer > 0):
                        $percentage = $total_all > 0 ? round(($total_transfer / $total_all) * 100, 1) : 0;
                    ?>
                    <div class="payment-item">
                        <div class="payment-info">
                            <div class="payment-icon transfer">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            <div class="payment-details">
                                <div class="payment-name">Transfer</div>
                                <div class="payment-count">
                                    <i class="fas fa-circle"></i> <?php echo $count_transfer; ?> transactions
                                </div>
                            </div>
                        </div>
                        <div class="payment-stats">
                            <div class="payment-total">Rp <?php echo number_format($total_transfer, 0, ',', '.'); ?></div>
                            <div class="payment-percentage"><?php echo $percentage; ?>%</div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if($count_cash == 0 && $count_qris == 0 && $count_transfer == 0): ?>
                    <div class="empty-state" style="padding: 30px;">
                        <i class="fas fa-credit-card"></i>
                        <p>No payment data available</p>
                        <p style="font-size: 12px; margin-top: 5px;">Try making some transactions first</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Top Products -->
        <div class="table-card">
            <div class="table-header">
                <h3><i class="fas fa-crown"></i> Top Selling Products</h3>
                <span class="table-badge">
                    <i class="fas fa-chart-simple"></i> By quantity sold
                </span>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Quantity Sold</th>
                            <th>Revenue</th>
                            <th>% of Sales</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_revenue = $summary['total_sales'];
                        $rank = 1;
                        if(mysqli_num_rows($query_top_products) > 0):
                            while($product = mysqli_fetch_assoc($query_top_products)):
                                $percentage = $total_revenue > 0 ? ($product['total_pendapatan'] / $total_revenue) * 100 : 0;
                                $rank_class = 'default';
                                if($rank == 1) $rank_class = 'gold';
                                else if($rank == 2) $rank_class = 'silver';
                                else if($rank == 3) $rank_class = 'bronze';
                        ?>
                        <tr>
                            <td>
                                <div class="rank <?php echo $rank_class; ?>"><?php echo $rank++; ?></div>
                            </td>
                            <td>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($product['nama_produk']); ?></div>
                            </td>
                            <td>
                                <span class="product-category">
                                    <i class="fas fa-tag"></i> <?php echo htmlspecialchars($product['nama_kategori']); ?>
                                </span>
                            </td>
                            <td><?php echo number_format($product['total_terjual']); ?> pcs</td>
                            <td style="color: var(--accent); font-weight: 600;">Rp <?php echo number_format($product['total_pendapatan'], 0, ',', '.'); ?></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span style="min-width: 45px;"><?php echo number_format($percentage, 1); ?>%</span>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $percentage; ?>%;"></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="6" class="empty-state">
                                <i class="fas fa-chart-line"></i>
                                <p>No sales data available for this period</p>
                                <a href="kasir.php" class="btn-empty">
                                    <i class="fas fa-plus"></i> New Transaction
                                </a>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="table-card transactions-table">
            <div class="table-header">
                <h3><i class="fas fa-history"></i> Recent Transactions</h3>
                <span class="table-badge">
                    <i class="fas fa-clock"></i> Last 50 transactions
                </span>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>Date & Time</th>
                            <th>Cashier</th>
                            <th>Payment Method</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($query_transactions) > 0): ?>
                            <?php while($trans = mysqli_fetch_assoc($query_transactions)): ?>
                            <tr>
                                <td>
                                    <span class="invoice-number">
                                        <i class="fas fa-receipt"></i> <?php echo $trans['no_transaksi']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div><?php echo date('d M Y', strtotime($trans['created_at'])); ?></div>
                                    <small style="color: var(--text-secondary);"><?php echo date('H:i', strtotime($trans['created_at'])); ?></small>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 5px;">
                                        <i class="fas fa-user-circle" style="color: var(--accent);"></i>
                                        <?php echo htmlspecialchars($trans['kasir']); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    $method = strtolower($trans['metode_pembayaran']);
                                    $badge_class = 'cash';
                                    $badge_text = 'Cash';
                                    $icon = 'fa-money-bill-wave';
                                    
                                    if($method == 'qris') {
                                        $badge_class = 'qris';
                                        $badge_text = 'QRIS';
                                        $icon = 'fa-qrcode';
                                    } elseif($method == 'transfer') {
                                        $badge_class = 'transfer';
                                        $badge_text = 'Transfer';
                                        $icon = 'fa-credit-card';
                                    } elseif($method == 'tunai' || $method == 'cash' || $method == '') {
                                        $badge_class = 'cash';
                                        $badge_text = 'Cash';
                                        $icon = 'fa-money-bill-wave';
                                    }
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <i class="fas <?php echo $icon; ?>"></i>
                                        <?php echo $badge_text; ?>
                                    </span>
                                </td>
                                <td style="color: var(--accent); font-weight: 600;">Rp <?php echo number_format($trans['grand_total'], 0, ',', '.'); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="empty-state">
                                    <i class="fas fa-receipt"></i>
                                    <p>No transactions found for this period</p>
                                    <a href="kasir.php" class="btn-empty">
                                        <i class="fas fa-plus"></i> New Transaction
                                    </a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Menu functions
        function toggleMenu() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('overlay').classList.toggle('active');
            if(document.getElementById('sidebar').classList.contains('active')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        }

        function closeMenu() {
            document.getElementById('sidebar').classList.remove('active');
            document.getElementById('overlay').classList.remove('active');
            document.body.style.overflow = '';
        }

        // Sales Chart
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($daily_labels); ?>,
                datasets: [{
                    label: 'Sales',
                    data: <?php echo json_encode($daily_data); ?>,
                    borderColor: '#00FFB2',
                    backgroundColor: 'rgba(0, 255, 178, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4,
                    pointBackgroundColor: '#00FFB2',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'var(--secondary)',
                        titleColor: 'var(--text-primary)',
                        bodyColor: 'var(--text-secondary)',
                        borderColor: 'var(--border)',
                        borderWidth: 1,
                        callbacks: {
                            label: function(context) {
                                return 'Rp ' + context.raw.toLocaleString('id-ID');
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { 
                            color: 'rgba(255,255,255,0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            color: '#A0A0A0',
                            callback: function(value) {
                                if(value >= 1000000) return 'Rp' + (value/1000000).toFixed(1) + 'M';
                                if(value >= 1000) return 'Rp' + (value/1000).toFixed(0) + 'K';
                                return 'Rp' + value;
                            }
                        }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { 
                            color: '#A0A0A0',
                            maxRotation: 45,
                            minRotation: 45
                        }
                    }
                }
            }
        });

        // Payment Chart
        const paymentCtx = document.getElementById('paymentChart').getContext('2d');
        
        const cashValue = <?php echo $total_cash ?: 0; ?>;
        const qrisValue = <?php echo $total_qris ?: 0; ?>;
        const transferValue = <?php echo $total_transfer ?: 0; ?>;

        console.log('🔍 Payment Data:', {
            cash: cashValue,
            qris: qrisValue,
            transfer: transferValue,
            count_cash: <?php echo $count_cash; ?>,
            count_qris: <?php echo $count_qris; ?>,
            count_transfer: <?php echo $count_transfer; ?>
        });

        let chartData = [];
        let chartLabels = [];
        let chartColors = [];

        if (cashValue > 0 || <?php echo $count_cash; ?> > 0) {
            chartData.push(cashValue);
            chartLabels.push('Cash');
            chartColors.push('#00FFB2');
        }

        if (qrisValue > 0 || <?php echo $count_qris; ?> > 0) {
            chartData.push(qrisValue);
            chartLabels.push('QRIS');
            chartColors.push('#3B82F6');
        }

        if (transferValue > 0 || <?php echo $count_transfer; ?> > 0) {
            chartData.push(transferValue);
            chartLabels.push('Transfer');
            chartColors.push('#FFB800');
        }

        if (chartData.length === 0) {
            chartData = [1];
            chartLabels = ['No Data'];
            chartColors.push('#2A2A2A');
        }

        new Chart(paymentCtx, {
            type: 'doughnut',
            data: {
                labels: chartLabels,
                datasets: [{
                    data: chartData,
                    backgroundColor: chartColors,
                    borderWidth: 0,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: { 
                        display: chartLabels[0] !== 'No Data',
                        position: 'bottom',
                        labels: {
                            color: '#A0A0A0',
                            font: { size: 11 },
                            padding: 15,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        backgroundColor: 'var(--secondary)',
                        titleColor: 'var(--text-primary)',
                        bodyColor: 'var(--text-secondary)',
                        borderColor: 'var(--border)',
                        borderWidth: 1,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                let value = context.raw || 0;
                                let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                let percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                
                                let count = 0;
                                if (label === 'Cash') count = <?php echo $count_cash; ?>;
                                else if (label === 'QRIS') count = <?php echo $count_qris; ?>;
                                else if (label === 'Transfer') count = <?php echo $count_transfer; ?>;
                                
                                return [
                                    `${label}: Rp ${value.toLocaleString('id-ID')}`,
                                    `${count} transactions (${percentage}%)`
                                ];
                            }
                        }
                    }
                }
            }
        });

        // Export function
        function exportReport() {
            const startDate = document.querySelector('input[name="start_date"]').value;
            const endDate = document.querySelector('input[name="end_date"]').value;
            
            const btn = event.currentTarget;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting...';
            btn.disabled = true;
            
            setTimeout(() => {
                window.location.href = 'export_laporan.php?start_date=' + startDate + '&end_date=' + endDate;
                btn.innerHTML = originalText;
                btn.disabled = false;
            }, 1000);
        }

        // Close menu on link click
        document.querySelectorAll('.menu-item, .logout-mobile').forEach(item => {
            item.addEventListener('click', function() {
                if (window.innerWidth < 768) closeMenu();
            });
        });

        // Handle window resize
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                if (window.innerWidth >= 768) {
                    closeMenu();
                }
            }, 250);
        });

        // Touch effects for mobile
        if('ontouchstart' in window) {
            document.querySelectorAll('.btn-filter, .btn-export, .btn-empty').forEach(element => {
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