<?php
require_once 'config.php';

// Cek login
if (!isLoggedIn()) {
    sendError('Unauthorized', 401);
}

// Cek role (admin & owner only)
if (!hasRole(['admin', 'owner'])) {
    sendError('Forbidden', 403);
}

$action = $_GET['action'] ?? 'summary';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

switch ($action) {
    case 'summary':
        getSummary($conn, $start_date, $end_date);
        break;
    case 'daily':
        getDailySales($conn, $start_date, $end_date);
        break;
    case 'products':
        getTopProducts($conn, $start_date, $end_date);
        break;
    case 'payments':
        getPaymentMethods($conn, $start_date, $end_date);
        break;
    case 'export':
        exportReport($conn, $start_date, $end_date);
        break;
    default:
        sendError('Invalid action');
}

function getSummary($conn, $start_date, $end_date) {
    $query = mysqli_query($conn, "SELECT 
        COUNT(DISTINCT id) as total_transactions,
        COALESCE(SUM(grand_total), 0) as total_sales,
        COALESCE(AVG(grand_total), 0) as average_sale,
        COUNT(DISTINCT DATE(created_at)) as active_days
        FROM transaksi 
        WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date' 
        AND status = 'selesai'");
    
    $summary = mysqli_fetch_assoc($query);
    
    // Get previous period for comparison
    $diff = (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24);
    $prev_start = date('Y-m-d', strtotime($start_date . " - $diff days - 1 day"));
    $prev_end = date('Y-m-d', strtotime($start_date . ' - 1 day'));
    
    $prev_query = mysqli_query($conn, "SELECT COALESCE(SUM(grand_total), 0) as total 
        FROM transaksi 
        WHERE DATE(created_at) BETWEEN '$prev_start' AND '$prev_end' 
        AND status = 'selesai'");
    
    $prev = mysqli_fetch_assoc($prev_query);
    
    $growth = $prev['total'] > 0 ? 
        (($summary['total_sales'] - $prev['total']) / $prev['total'] * 100) : 100;
    
    sendResponse(true, 'Summary retrieved', [
        'period' => [
            'start' => $start_date,
            'end' => $end_date
        ],
        'total_transactions' => (int)$summary['total_transactions'],
        'total_sales' => (int)$summary['total_sales'],
        'average_sale' => (int)$summary['average_sale'],
        'active_days' => (int)$summary['active_days'],
        'growth' => round($growth, 1),
        'daily_average' => $summary['active_days'] > 0 ? 
            (int)($summary['total_sales'] / $summary['active_days']) : 0
    ]);
}

function getDailySales($conn, $start_date, $end_date) {
    $query = mysqli_query($conn, "SELECT 
        DATE(created_at) as date,
        COUNT(*) as transactions,
        COALESCE(SUM(grand_total), 0) as total
        FROM transaksi 
        WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date' 
        AND status = 'selesai'
        GROUP BY DATE(created_at)
        ORDER BY date ASC");
    
    $daily = [];
    $labels = [];
    $data = [];
    
    while ($row = mysqli_fetch_assoc($query)) {
        $labels[] = date('d M', strtotime($row['date']));
        $data[] = (int)$row['total'];
        $daily[] = [
            'date' => $row['date'],
            'transactions' => (int)$row['transactions'],
            'total' => (int)$row['total']
        ];
    }
    
    sendResponse(true, 'Daily sales retrieved', [
        'daily' => $daily,
        'chart' => [
            'labels' => $labels,
            'data' => $data
        ]
    ]);
}

function getTopProducts($conn, $start_date, $end_date) {
    $query = mysqli_query($conn, "SELECT 
        p.id,
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
    
    $products = [];
    
    while ($row = mysqli_fetch_assoc($query)) {
        $products[] = [
            'id' => $row['id'],
            'nama' => $row['nama_produk'],
            'kategori' => $row['nama_kategori'],
            'terjual' => (int)$row['total_terjual'],
            'pendapatan' => (int)$row['total_pendapatan']
        ];
    }
    
    sendResponse(true, 'Top products retrieved', $products);
}

// ========== FIXED: Get Payment Methods dengan penanganan data kosong ==========
function getPaymentMethods($conn, $start_date, $end_date) {
    // Query dengan CASE untuk menangani data kosong
    $query = mysqli_query($conn, "SELECT 
        CASE 
            WHEN metode_pembayaran IS NULL OR metode_pembayaran = '' THEN 'cash'
            ELSE LOWER(metode_pembayaran)
        END as payment_method,
        COUNT(*) as jumlah,
        COALESCE(SUM(grand_total), 0) as total
        FROM transaksi 
        WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date' 
        AND status = 'selesai'
        GROUP BY 
            CASE 
                WHEN metode_pembayaran IS NULL OR metode_pembayaran = '' THEN 'cash'
                ELSE LOWER(metode_pembayaran)
            END");
    
    $methods = [];
    $total_all = 0;
    $payment_map = [
        'cash' => ['Cash', 0, 0],
        'tunai' => ['Cash', 0, 0],
        'qris' => ['QRIS', 0, 0],
        'transfer' => ['Transfer', 0, 0]
    ];
    
    // Kumpulkan data dari query
    while ($row = mysqli_fetch_assoc($query)) {
        $method = $row['payment_method'];
        $jumlah = (int)$row['jumlah'];
        $total = (int)$row['total'];
        
        if (isset($payment_map[$method])) {
            $payment_map[$method][1] += $jumlah;
            $payment_map[$method][2] += $total;
        }
        $total_all += $total;
    }
    
    // Gabungkan cash dan tunai
    $cash_total = $payment_map['cash'][2] + $payment_map['tunai'][2];
    $cash_count = $payment_map['cash'][1] + $payment_map['tunai'][1];
    
    // Format response
    $methods[] = [
        'metode' => 'Cash',
        'jumlah' => $cash_count,
        'total' => $cash_total,
        'percentage' => $total_all > 0 ? round(($cash_total / $total_all) * 100, 1) : 0
    ];
    
    $methods[] = [
        'metode' => 'QRIS',
        'jumlah' => $payment_map['qris'][1],
        'total' => $payment_map['qris'][2],
        'percentage' => $total_all > 0 ? round(($payment_map['qris'][2] / $total_all) * 100, 1) : 0
    ];
    
    $methods[] = [
        'metode' => 'Transfer',
        'jumlah' => $payment_map['transfer'][1],
        'total' => $payment_map['transfer'][2],
        'percentage' => $total_all > 0 ? round(($payment_map['transfer'][2] / $total_all) * 100, 1) : 0
    ];
    
    // Filter hanya yang memiliki transaksi
    $methods = array_filter($methods, function($item) {
        return $item['jumlah'] > 0;
    });
    
    sendResponse(true, 'Payment methods retrieved', array_values($methods));
}
// =============================================================================

function exportReport($conn, $start_date, $end_date) {
    // Get all transactions
    $query = mysqli_query($conn, "SELECT 
        t.no_transaksi,
        t.created_at,
        u.nama_lengkap as kasir,
        CASE 
            WHEN t.metode_pembayaran IS NULL OR t.metode_pembayaran = '' THEN 'cash'
            ELSE t.metode_pembayaran
        END as metode_pembayaran,
        t.grand_total as total
        FROM transaksi t
        JOIN users u ON t.user_id = u.id
        WHERE DATE(t.created_at) BETWEEN '$start_date' AND '$end_date' 
        AND t.status = 'selesai'
        ORDER BY t.created_at DESC");
    
    $transactions = [];
    while ($row = mysqli_fetch_assoc($query)) {
        // Format metode pembayaran untuk display
        $metode = $row['metode_pembayaran'];
        if ($metode == 'cash' || $metode == 'tunai') {
            $metode = 'Cash';
        } elseif ($metode == 'qris') {
            $metode = 'QRIS';
        } elseif ($metode == 'transfer') {
            $metode = 'Transfer';
        }
        
        $transactions[] = [
            'no_transaksi' => $row['no_transaksi'],
            'created_at' => $row['created_at'],
            'kasir' => $row['kasir'],
            'metode_pembayaran' => $metode,
            'total' => $row['total']
        ];
    }
    
    // Generate CSV
    $filename = 'report_' . $start_date . '_to_' . $end_date . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Header CSV
    fputcsv($output, ['No. Transaksi', 'Tanggal', 'Kasir', 'Metode', 'Total']);
    
    // Data
    foreach ($transactions as $row) {
        fputcsv($output, [
            $row['no_transaksi'],
            $row['created_at'],
            $row['kasir'],
            $row['metode_pembayaran'],
            $row['total']
        ]);
    }
    
    fclose($output);
    exit();
}
?>