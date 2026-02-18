<?php
session_start();
require_once '../config/database.php';

// Cek login
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$no_transaksi = $_GET['no'] ?? '';

if (!$no_transaksi) {
    header("Location: dashboard.php");
    exit();
}

// Get transaction data
$query = mysqli_query($conn, "SELECT t.*, u.nama_lengkap as kasir 
                              FROM transaksi t 
                              JOIN users u ON t.user_id = u.id 
                              WHERE t.no_transaksi = '$no_transaksi'");

if (mysqli_num_rows($query) == 0) {
    header("Location: dashboard.php");
    exit();
}

$transaksi = mysqli_fetch_assoc($query);

// Get transaction details
$detail_query = mysqli_query($conn, "SELECT dt.*, p.nama_produk 
                                     FROM detail_transaksi dt 
                                     JOIN produk p ON dt.produk_id = p.id 
                                     WHERE dt.transaksi_id = '" . $transaksi['id'] . "'");

// Get store settings
$settings_query = mysqli_query($conn, "SELECT * FROM pengaturan WHERE id = 1");
$settings = mysqli_fetch_assoc($settings_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk - <?php echo $no_transaksi; ?></title>
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        /* RESET TOTAL */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #FFFFFF;
            font-family: 'Courier New', Courier, monospace;
            width: 100%;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px 0;
        }

        /* LEBAR FULL - 80mm DENGAN FONT BESAR */
        .receipt {
            width: 80mm; /* Full width 80mm */
            max-width: 80mm;
            margin: 0 auto;
            background: #FFFFFF;
            font-size: 14px; /* Font lebih besar */
            line-height: 1.4;
            padding: 5px 0;
        }

        /* SEMUA ELEMEN FULL WIDTH */
        .receipt > div {
            width: 100%;
        }

        /* HEADER - BESAR */
        .store-header {
            text-align: center;
            width: 100%;
            padding: 5px 0;
            border-bottom: 2px solid #000000;
            margin-bottom: 3px;
        }

        .store-name {
            font-size: 24px; /* BESAR BANGET */
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
            line-height: 1.2;
        }

        .store-address {
            font-size: 14px; /* BESAR */
            text-transform: uppercase;
            line-height: 1.3;
        }

        .store-contact {
            font-size: 14px; /* BESAR */
            text-transform: uppercase;
            line-height: 1.3;
        }

        /* JUDUL */
        .receipt-title {
            text-align: center;
            font-weight: 800;
            font-size: 16px; /* BESAR */
            text-transform: uppercase;
            letter-spacing: 2px;
            padding: 5px 0;
            border-bottom: 2px solid #000000;
            margin-bottom: 3px;
        }

        /* INFO SECTION - FONT BESAR */
        .info-section {
            width: 100%;
            padding: 3px 0;
            border-bottom: 2px solid #000000;
            margin-bottom: 3px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            font-size: 15px; /* BESAR */
            text-transform: uppercase;
            line-height: 1.5;
            padding: 2px 0;
        }

        /* ITEMS SECTION */
        .items-section {
            width: 100%;
            border-bottom: 2px solid #000000;
            margin-bottom: 3px;
        }

        .items-header {
            display: grid;
            grid-template-columns: 3fr 1fr 1.5fr;
            font-weight: 800;
            font-size: 15px; /* BESAR */
            text-transform: uppercase;
            border-bottom: 2px solid #000000;
            padding: 3px 0;
        }

        .items-header span:last-child {
            text-align: right;
        }

        .item-row {
            display: grid;
            grid-template-columns: 3fr 1fr 1.5fr;
            font-size: 15px; /* BESAR */
            line-height: 1.5;
            padding: 3px 0;
            border-bottom: 1px dashed #666666;
        }

        .item-row:last-child {
            border-bottom: none;
        }

        .item-name {
            text-transform: uppercase;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding-right: 5px;
        }

        .item-qty {
            text-align: center;
        }

        .item-price {
            text-align: right;
        }

        /* TOTAL SECTION - BESAR */
        .total-section {
            width: 100%;
            padding: 3px 0;
            border-bottom: 2px solid #000000;
            margin-bottom: 3px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            font-size: 16px; /* BESAR */
            text-transform: uppercase;
            line-height: 1.5;
            padding: 2px 0;
        }

        .grand-total {
            display: flex;
            justify-content: space-between;
            font-weight: 800;
            font-size: 20px; /* SANGAT BESAR */
            text-transform: uppercase;
            border-top: 2px solid #000000;
            border-bottom: 2px solid #000000;
            padding: 5px 0;
            margin: 3px 0;
        }

        /* PAYMENT SECTION - BESAR */
        .payment-section {
            width: 100%;
            padding: 3px 0;
            border-bottom: 2px solid #000000;
            margin-bottom: 3px;
        }

        .payment-row {
            display: flex;
            justify-content: space-between;
            font-size: 16px; /* BESAR */
            text-transform: uppercase;
            line-height: 1.5;
            padding: 2px 0;
        }

        .payment-row:last-child {
            font-weight: 800;
        }

        /* BARCODE */
        .barcode {
            text-align: center;
            font-size: 24px; /* BESAR BANGET */
            letter-spacing: 5px;
            border-top: 2px solid #000000;
            border-bottom: 2px solid #000000;
            padding: 5px 0;
            margin-bottom: 3px;
        }

        .barcode i {
            font-size: 28px;
        }

        /* FOOTER - BESAR */
        .footer-section {
            text-align: center;
            width: 100%;
            padding: 5px 0;
        }

        .thank-you {
            font-weight: 800;
            font-size: 22px; /* BESAR BANGET */
            text-transform: uppercase;
            letter-spacing: 2px;
            line-height: 1.3;
        }

        .footer-message {
            font-size: 16px; /* BESAR */
            text-transform: uppercase;
            line-height: 1.4;
            padding: 3px 0;
        }

        .datetime {
            font-size: 14px; /* BESAR */
            text-transform: uppercase;
            line-height: 1.4;
        }

        /* TOMBOL AKSES - TETAP DI BAWAH */
        .actions {
            width: 80mm;
            margin: 20px auto 0;
            display: flex;
            gap: 10px;
        }

        .btn {
            flex: 1;
            background: #FFFFFF;
            border: 2px solid #000000;
            padding: 15px 0;
            font-family: 'Courier New', monospace;
            font-size: 16px;
            font-weight: 800;
            text-transform: uppercase;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            color: #000000;
        }

        .btn:hover {
            background: #000000;
            color: #FFFFFF;
        }

        .btn i {
            margin-right: 5px;
        }

        /* PRINT OPTIMIZATION */
        @media print {
            body {
                padding: 0;
                margin: 0;
                background: #FFFFFF;
            }

            .receipt {
                width: 80mm;
                margin: 0;
                padding: 2px 0;
            }

            .actions {
                display: none;
            }

            /* SEMUA BORDER TEBAL UNTUK PRINT */
            .store-header {
                border-bottom: 2px solid #000000;
            }
            .receipt-title {
                border-bottom: 2px solid #000000;
            }
            .info-section {
                border-bottom: 2px solid #000000;
            }
            .items-section {
                border-bottom: 2px solid #000000;
            }
            .items-header {
                border-bottom: 2px solid #000000;
            }
            .item-row {
                border-bottom: 1px solid #666666;
            }
            .total-section {
                border-bottom: 2px solid #000000;
            }
            .grand-total {
                border-top: 2px solid #000000;
                border-bottom: 2px solid #000000;
            }
            .payment-section {
                border-bottom: 2px solid #000000;
            }
            .barcode {
                border-top: 2px solid #000000;
                border-bottom: 2px solid #000000;
            }
        }

        /* PERBAIKAN TYPO */
        .info-row span:first-child {
            min-width: 80px;
        }
    </style>
</head>
<body>
    <div class="receipt">
        <!-- HEADER -->
        <div class="store-header">
            <div class="store-name"><?php echo htmlspecialchars($settings['nama_toko'] ?? 'MAJOO POS'); ?></div>
            <div class="store-address"><?php echo htmlspecialchars($settings['alamat'] ?? 'JL. SUDIRMAN NO. 123'); ?></div>
            <div class="store-contact">TELP: <?php echo htmlspecialchars($settings['telepon'] ?? '021-12345678'); ?></div>
        </div>

        <!-- JUDUL -->
        <div class="receipt-title">STRUK PEMBELIAN</div>

        <!-- INFO TRANSAKSI -->
        <div class="info-section">
            <div class="info-row">
                <span>NO. TRANSAKSI</span>
                <span><?php echo htmlspecialchars($transaksi['no_transaksi']); ?></span>
            </div>
            <div class="info-row">
                <span>TANGGAL</span>
                <span><?php echo date('d/m/Y', strtotime($transaksi['created_at'])); ?></span>
            </div>
            <div class="info-row">
                <span>JAM</span>
                <span><?php echo date('H:i:s', strtotime($transaksi['created_at'])); ?></span>
            </div>
            <div class="info-row">
                <span>KASIR</span>
                <span><?php echo htmlspecialchars($transaksi['kasir']); ?></span>
            </div>
        </div>

        <!-- ITEMS -->
        <div class="items-section">
            <div class="items-header">
                <span>ITEM</span>
                <span>QTY</span>
                <span>HARGA</span>
            </div>

            <?php 
            $subtotal = 0;
            while($item = mysqli_fetch_assoc($detail_query)): 
                $subtotal += $item['subtotal'];
            ?>
            <div class="item-row">
                <span class="item-name"><?php echo htmlspecialchars($item['nama_produk']); ?></span>
                <span class="item-qty"><?php echo $item['jumlah']; ?></span>
                <span class="item-price"><?php echo number_format($item['subtotal'], 0, ',', '.'); ?></span>
            </div>
            <?php endwhile; ?>
        </div>

        <!-- TOTAL -->
        <div class="total-section">
            <div class="total-row">
                <span>SUBTOTAL</span>
                <span><?php echo number_format($subtotal, 0, ',', '.'); ?></span>
            </div>

            <?php if ($settings['pajak'] > 0): ?>
            <div class="total-row">
                <span>PAJAK <?php echo $settings['pajak']; ?>%</span>
                <span><?php 
                    $tax = $subtotal * ($settings['pajak'] / 100);
                    echo number_format($tax, 0, ',', '.');
                ?></span>
            </div>
            <?php endif; ?>

            <?php if ($transaksi['diskon'] > 0): ?>
            <div class="total-row">
                <span>DISKON</span>
                <span>-<?php echo number_format($transaksi['diskon'], 0, ',', '.'); ?></span>
            </div>
            <?php endif; ?>

            <div class="grand-total">
                <span>TOTAL</span>
                <span>RP <?php echo number_format($transaksi['grand_total'], 0, ',', '.'); ?></span>
            </div>
        </div>

        <!-- PEMBAYARAN -->
        <div class="payment-section">
            <div class="payment-row">
                <span>TUNAI</span>
                <span><?php echo number_format($transaksi['jumlah_bayar'], 0, ',', '.'); ?></span>
            </div>
            <div class="payment-row">
                <span>KEMBALI</span>
                <span><?php echo number_format($transaksi['kembalian'], 0, ',', '.'); ?></span>
            </div>
            <div class="payment-row">
                <span>METODE</span>
                <span>
                    <?php 
                    if ($transaksi['metode_pembayaran'] == 'cash' || $transaksi['metode_pembayaran'] == 'tunai') {
                        echo 'TUNAI';
                    } elseif ($transaksi['metode_pembayaran'] == 'qris') {
                        echo 'QRIS';
                    } elseif ($transaksi['metode_pembayaran'] == 'transfer') {
                        echo 'TRANSFER';
                    } else {
                        echo strtoupper($transaksi['metode_pembayaran']);
                    }
                    ?>
                </span>
            </div>
        </div>

        <!-- BARCODE -->
        <div class="barcode">
            <i class="fas fa-barcode"></i> <?php echo substr($no_transaksi, -8); ?>
        </div>

        <!-- FOOTER -->
        <div class="footer-section">
            <div class="thank-you">TERIMA KASIH</div>
            <div class="footer-message"><?php echo htmlspecialchars($settings['receipt_footer'] ?? 'SELAMAT DATANG KEMBALI'); ?></div>
            <div class="datetime"><?php echo date('d/m/Y H:i:s'); ?></div>
        </div>
    </div>

    <!-- TOMBOL -->
    <div class="actions">
        <button class="btn" onclick="window.print()">
            <i class="fas fa-print"></i> CETAK
        </button>
        <a href="kasir.php" class="btn">
            <i class="fas fa-plus"></i> BARU
        </a>
    </div>

    <script>
        // Auto print
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('print') === 'true') {
            setTimeout(() => {
                window.print();
            }, 500);
        }
    </script>
</body>
</html>