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

// Only admin and kasir can access
if($role != 'admin' && $role != 'kasir') {
    header("Location: dashboard.php");
    exit();
}

// Initialize cart from session
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Get products from database
$query_produk = mysqli_query($conn, "SELECT p.*, k.nama_kategori 
    FROM produk p 
    LEFT JOIN kategori k ON p.kategori_id = k.id 
    WHERE p.is_active = 1 
    ORDER BY p.nama_produk ASC");

// Get categories
$query_kategori = mysqli_query($conn, "SELECT * FROM kategori ORDER BY nama_kategori ASC");

// Get store settings
$settings_query = mysqli_query($conn, "SELECT * FROM pengaturan WHERE id = 1");
$settings = mysqli_fetch_assoc($settings_query);

// Data bank untuk transfer
$bank_accounts = [
    [
        'nama' => 'BCA',
        'logo' => 'bca.png',
        'no_rekening' => '1234567890',
        'atas_nama' => 'PT Majoo Indonesia',
        'warna' => '#0066AE',
        'icon' => 'fa-university'
    ],
    [
        'nama' => 'Mandiri',
        'logo' => 'mandiri.png',
        'no_rekening' => '123456789012',
        'atas_nama' => 'PT Majoo Indonesia',
        'warna' => '#003E7E',
        'icon' => 'fa-building'
    ],
    [
        'nama' => 'BRI',
        'logo' => 'bri.png',
        'no_rekening' => '123456789012',
        'atas_nama' => 'PT Majoo Indonesia',
        'warna' => '#0053A0',
        'icon' => 'fa-credit-card'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>POS - majoo International</title>
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- QR Code Generator -->
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #0A0A0A;
            --secondary: #1E1E1E;
            --card: #141414;
            --accent: #00FFB2;
            --accent-glow: rgba(0, 255, 178, 0.2);
            --text-primary: #FFFFFF;
            --text-secondary: #A0A0A0;
            --border: #2A2A2A;
            --success: #00FFB2;
            --warning: #FFB800;
            --danger: #FF4D4D;
            --info: #3B82F6;
            --cash: #10b981;
            --qris: #3b82f6;
            --transfer: #f59e0b;
            
            --bca: #0066AE;
            --mandiri: #003E7E;
            --bri: #0053A0;
        }

        body {
            font-family: 'Space Grotesk', sans-serif;
            background: var(--primary);
            color: var(--text-primary);
            min-height: 100vh;
            overflow: hidden;
        }

        /* POS Layout */
        .pos-container {
            display: flex;
            height: 100vh;
            width: 100%;
            overflow: hidden;
        }

        /* Products Section */
        .products-section {
            flex: 1.5;
            display: flex;
            flex-direction: column;
            background: var(--primary);
            border-right: 1px solid var(--border);
        }

        .products-header {
            padding: 20px 25px;
            background: var(--secondary);
            border-bottom: 1px solid var(--border);
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .header-top h1 {
            font-size: 28px;
            font-weight: 800;
            background: linear-gradient(135deg, #fff, var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.5px;
        }

        .back-btn {
            color: var(--text-secondary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 40px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .back-btn:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        .search-box {
            width: 100%;
        }

        .search-box input {
            width: 100%;
            padding: 15px 20px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            color: var(--text-primary);
            font-family: 'Space Grotesk', sans-serif;
            font-size: 16px;
            transition: all 0.2s;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-glow);
        }

        .search-box input::placeholder {
            color: var(--text-secondary);
        }

        .categories-wrapper {
            padding: 20px 25px;
            background: var(--primary);
            border-bottom: 1px solid var(--border);
        }

        .categories {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding-bottom: 5px;
        }

        .categories::-webkit-scrollbar {
            height: 4px;
        }

        .categories::-webkit-scrollbar-track {
            background: var(--border);
            border-radius: 10px;
        }

        .categories::-webkit-scrollbar-thumb {
            background: var(--accent);
            border-radius: 10px;
        }

        .category-btn {
            padding: 10px 20px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 40px;
            color: var(--text-secondary);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.2s;
        }

        .category-btn:hover,
        .category-btn.active {
            background: var(--accent);
            color: var(--primary);
            border-color: var(--accent);
        }

        .products-grid {
            flex: 1;
            padding: 25px;
            overflow-y: auto;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            align-content: start;
        }

        .products-grid::-webkit-scrollbar {
            width: 6px;
        }

        .products-grid::-webkit-scrollbar-track {
            background: var(--border);
            border-radius: 10px;
        }

        .products-grid::-webkit-scrollbar-thumb {
            background: var(--accent);
            border-radius: 10px;
        }

        .product-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .product-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, var(--accent-glow) 0%, transparent 70%);
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
        }

        .product-card:hover::before {
            opacity: 1;
        }

        .product-card:hover {
            border-color: var(--accent);
            transform: translateY(-5px);
        }

        .product-card.out-of-stock {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .product-card.out-of-stock:hover {
            border-color: var(--danger);
            transform: none;
        }

        .product-icon {
            font-size: 48px;
            color: var(--accent);
            margin-bottom: 15px;
            text-align: center;
        }

        .product-card h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 5px;
            text-align: center;
        }

        .product-category {
            font-size: 12px;
            color: var(--text-secondary);
            margin-bottom: 10px;
            text-align: center;
        }

        .product-price {
            font-size: 20px;
            font-weight: 700;
            color: var(--accent);
            margin-bottom: 10px;
            text-align: center;
        }

        .product-stock {
            font-size: 12px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            padding-top: 10px;
            border-top: 1px solid var(--border);
        }

        .stock-low {
            color: var(--warning);
        }

        .stock-out {
            color: var(--danger);
        }

        .stock-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
        }

        .stock-badge.low {
            background: var(--warning);
            color: var(--primary);
        }

        .stock-badge.out {
            background: var(--danger);
            color: white;
        }

        /* Cart Section */
        .cart-section {
            width: 520px;
            background: var(--secondary);
            display: flex;
            flex-direction: column;
            height: 100vh;
            position: relative;
        }

        .cart-header {
            padding: 25px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .cart-header h2 {
            font-size: 22px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .cart-header h2 i {
            color: var(--accent);
            font-size: 24px;
        }

        .cart-stats {
            display: flex;
            gap: 10px;
        }

        .item-count {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 30px;
            padding: 5px 12px;
            font-size: 13px;
            color: var(--text-secondary);
        }

        .clear-cart {
            background: none;
            border: 1px solid var(--border);
            color: var(--text-secondary);
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .clear-cart:hover {
            border-color: var(--danger);
            color: var(--danger);
        }

        .cart-items {
            flex: 1;
            overflow-y: auto;
            padding: 25px;
        }

        .cart-items::-webkit-scrollbar {
            width: 6px;
        }

        .cart-items::-webkit-scrollbar-track {
            background: var(--border);
            border-radius: 10px;
        }

        .cart-items::-webkit-scrollbar-thumb {
            background: var(--accent);
            border-radius: 10px;
        }

        .cart-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid var(--border);
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .cart-item-info {
            flex: 2;
        }

        .cart-item-info h4 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .cart-item-info p {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .cart-item-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .qty-btn {
            width: 32px;
            height: 32px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            color: var(--text-primary);
        }

        .qty-btn:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        .qty-btn:active {
            transform: scale(0.95);
        }

        .item-qty {
            font-weight: 600;
            min-width: 30px;
            text-align: center;
            font-size: 16px;
        }

        .item-price {
            font-weight: 700;
            color: var(--accent);
            min-width: 90px;
            text-align: right;
            font-size: 16px;
        }

        .empty-cart {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }

        .empty-cart i {
            font-size: 80px;
            margin-bottom: 20px;
            color: var(--border);
        }

        .empty-cart p {
            font-size: 16px;
            margin-bottom: 10px;
        }

        .empty-cart small {
            font-size: 13px;
            color: var(--text-secondary);
        }

        /* Cart Footer */
        .cart-footer {
            padding: 25px;
            border-top: 1px solid var(--border);
            background: var(--card);
        }

        .subtotal {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            font-size: 16px;
            color: var(--text-secondary);
        }

        .total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-top: 15px;
            border-top: 2px solid var(--border);
            font-size: 24px;
            font-weight: 700;
        }

        .total span:last-child {
            color: var(--accent);
        }

        /* Payment Methods */
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }

        .payment-method {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 15px 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .payment-method:hover {
            transform: translateY(-3px);
        }

        .payment-method.active {
            border-color: var(--accent);
            background: var(--accent-glow);
            box-shadow: 0 0 20px var(--accent-glow);
        }

        .payment-method i {
            font-size: 28px;
            margin-bottom: 8px;
            display: block;
        }

        .payment-method.cash i { color: var(--cash); }
        .payment-method.qris i { color: var(--qris); }
        .payment-method.transfer i { color: var(--transfer); }

        .payment-method.active i {
            color: var(--accent);
        }

        .payment-method span {
            font-size: 14px;
            font-weight: 600;
        }

        /* Payment Input */
        .payment-input {
            margin-bottom: 15px;
        }

        .payment-input label {
            display: block;
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }

        .payment-input input {
            width: 100%;
            padding: 16px 20px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            color: var(--text-primary);
            font-family: 'Space Grotesk', sans-serif;
            font-size: 20px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .payment-input input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-glow);
        }

        .change-box {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .change-box span:first-child {
            color: var(--text-secondary);
            font-size: 14px;
        }

        .change-box strong {
            font-size: 24px;
            color: var(--accent);
        }

        .checkout-btn {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, var(--accent), #00ccff);
            color: var(--primary);
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
        }

        .checkout-btn:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px var(--accent-glow);
        }

        .checkout-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .checkout-btn i {
            font-size: 20px;
        }

        /* QRIS Modal */
        .qris-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.95);
            backdrop-filter: blur(10px);
            z-index: 3000;
            align-items: center;
            justify-content: center;
        }

        .qris-modal.active {
            display: flex;
        }

        .qris-content {
            background: white;
            border-radius: 40px;
            padding: 40px;
            max-width: 450px;
            width: 90%;
            text-align: center;
            animation: modalPop 0.4s ease;
        }

        .qris-content.dark {
            background: var(--secondary);
            border: 1px solid var(--border);
        }

        .qris-header {
            margin-bottom: 30px;
        }

        .qris-header h2 {
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(135deg, #000, var(--qris));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }

        .qris-header p {
            color: var(--text-secondary);
            font-size: 16px;
        }

        .qris-code {
            background: white;
            padding: 20px;
            border-radius: 30px;
            margin-bottom: 25px;
            display: inline-block;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }

        #qrcode {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        #qrcode img {
            width: 200px;
            height: 200px;
            border-radius: 20px;
        }

        .qris-amount {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 60px;
            padding: 15px 25px;
            margin-bottom: 25px;
            display: inline-block;
        }

        .qris-amount .label {
            font-size: 14px;
            color: var(--text-secondary);
            margin-right: 10px;
        }

        .qris-amount .value {
            font-size: 28px;
            font-weight: 700;
            color: var(--qris);
        }

        .qris-footer {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .qris-btn {
            flex: 1;
            padding: 16px;
            border-radius: 40px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .qris-btn.done {
            background: var(--qris);
            color: white;
        }

        .qris-btn.done:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.4);
        }

        .qris-btn.cancel {
            background: var(--border);
            color: var(--text-primary);
        }

        .qris-btn.cancel:hover {
            background: var(--danger);
            color: white;
        }

        /* Transfer Modal */
        .transfer-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.95);
            backdrop-filter: blur(10px);
            z-index: 3000;
            align-items: center;
            justify-content: center;
        }

        .transfer-modal.active {
            display: flex;
        }

        .transfer-content {
            background: var(--secondary);
            border: 1px solid var(--border);
            border-radius: 40px;
            padding: 40px;
            max-width: 550px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalPop 0.4s ease;
        }

        .transfer-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .transfer-header h2 {
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(135deg, #fff, var(--transfer));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }

        .transfer-header p {
            color: var(--text-secondary);
            font-size: 16px;
        }

        .bank-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 20px;
            max-height: 300px;
            overflow-y: auto;
            padding-right: 5px;
        }

        .bank-list::-webkit-scrollbar {
            width: 4px;
        }

        .bank-list::-webkit-scrollbar-track {
            background: var(--border);
            border-radius: 10px;
        }

        .bank-list::-webkit-scrollbar-thumb {
            background: var(--transfer);
            border-radius: 10px;
        }

        .bank-item {
            background: var(--card);
            border: 2px solid var(--border);
            border-radius: 20px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            cursor: pointer;
            position: relative;
            transition: all 0.3s ease;
        }

        .bank-item:hover {
            border-color: var(--transfer);
            transform: translateX(5px);
            background: rgba(245, 158, 11, 0.05);
        }

        .bank-item.selected {
            border-color: var(--transfer);
            background: rgba(245, 158, 11, 0.1);
            box-shadow: 0 5px 15px rgba(245, 158, 11, 0.2);
        }

        .bank-logo {
            width: 55px;
            height: 55px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
            flex-shrink: 0;
        }

        .bank-logo.bca { background: var(--bca); }
        .bank-logo.mandiri { background: var(--mandiri); }
        .bank-logo.bri { background: var(--bri); }

        .bank-details {
            flex: 1;
        }

        .bank-name {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .bank-account {
            font-size: 16px;
            font-weight: 600;
            color: var(--transfer);
            margin-bottom: 2px;
            letter-spacing: 1px;
        }

        .bank-holder {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .bank-check {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
        }

        .selected-bank-info {
            background: linear-gradient(135deg, var(--card), var(--secondary));
            border: 1px solid var(--transfer);
            border-radius: 20px;
            padding: 16px 20px;
            margin-bottom: 20px;
        }

        .selected-bank-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .selected-label {
            font-size: 13px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .copy-btn {
            background: var(--primary);
            border: 1px solid var(--transfer);
            border-radius: 30px;
            padding: 6px 14px;
            color: var(--transfer);
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .copy-btn:hover {
            background: var(--transfer);
            color: var(--primary);
        }

        .copy-btn i {
            font-size: 12px;
        }

        .selected-bank-detail {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .selected-bank-name {
            font-size: 18px;
            font-weight: 700;
            color: var(--transfer);
        }

        .selected-bank-account {
            font-size: 20px;
            font-weight: 800;
            color: var(--text-primary);
            letter-spacing: 2px;
            font-family: monospace;
        }

        .transfer-amount {
            background: linear-gradient(135deg, var(--primary), var(--card));
            border: 1px solid var(--border);
            border-radius: 60px;
            padding: 16px 25px;
            margin-bottom: 15px;
            text-align: center;
        }

        .transfer-amount .label {
            font-size: 14px;
            color: var(--text-secondary);
            margin-right: 10px;
        }

        .transfer-amount .value {
            font-size: 28px;
            font-weight: 800;
            color: var(--transfer);
        }

        .transfer-instruction {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
            border-radius: 16px;
            padding: 12px 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--transfer);
            font-size: 13px;
        }

        .transfer-instruction i {
            font-size: 18px;
        }

        .transfer-footer {
            display: flex;
            gap: 12px;
            margin-top: 10px;
        }

        .transfer-btn {
            flex: 1;
            padding: 16px;
            border-radius: 40px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .transfer-btn.done {
            background: linear-gradient(135deg, var(--transfer), #fbbf24);
            color: var(--primary);
        }

        .transfer-btn.done:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(245, 158, 11, 0.4);
        }

        .transfer-btn.cancel {
            background: var(--border);
            color: var(--text-primary);
        }

        .transfer-btn.cancel:hover {
            background: var(--danger);
            color: white;
            transform: translateY(-3px);
        }

        /* Success Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            backdrop-filter: blur(10px);
            z-index: 4000;
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
            max-width: 450px;
            width: 90%;
            text-align: center;
            animation: modalPop 0.4s ease;
        }

        @keyframes modalPop {
            from {
                opacity: 0;
                transform: scale(0.8);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .modal-icon {
            width: 100px;
            height: 100px;
            background: var(--accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            font-size: 50px;
            color: var(--primary);
        }

        .modal h3 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #fff, var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .modal p {
            color: var(--text-secondary);
            font-size: 16px;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .modal-actions {
            display: flex;
            gap: 12px;
        }

        .modal-btn {
            flex: 1;
            padding: 16px;
            border-radius: 40px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }

        .modal-btn.primary {
            background: var(--accent);
            color: var(--primary);
        }

        .modal-btn.primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px var(--accent-glow);
        }

        .modal-btn.secondary {
            background: var(--card);
            color: var(--text-primary);
            border: 1px solid var(--border);
        }

        .modal-btn.secondary:hover {
            border-color: var(--accent);
        }

        /* Toast Notification */
        .toast {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: var(--secondary);
            border: 1px solid var(--border);
            border-radius: 50px;
            padding: 15px 30px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            z-index: 5000;
            transition: transform 0.3s ease;
        }

        .toast.show {
            transform: translateX(-50%) translateY(0);
        }

        .toast i {
            font-size: 20px;
        }

        .toast.success i {
            color: var(--accent);
        }

        .toast.error i {
            color: var(--danger);
        }

        .toast.warning i {
            color: var(--warning);
        }

        .toast.warning {
            border-color: var(--warning);
        }

        .toast span {
            font-size: 14px;
            color: var(--text-primary);
        }

        /* Loading Overlay */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(10, 10, 10, 0.9);
            backdrop-filter: blur(5px);
            z-index: 6000;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 20px;
        }

        .loading-overlay.active {
            display: flex;
        }

        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 4px solid var(--border);
            border-top: 4px solid var(--accent);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .loading-overlay p {
            color: var(--text-primary);
            font-size: 18px;
            font-weight: 500;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .cart-section {
                width: 450px;
            }
        }

        @media (max-width: 992px) {
            .pos-container {
                flex-direction: column;
                overflow-y: auto;
            }

            .products-section {
                flex: none;
                height: auto;
            }

            .cart-section {
                width: 100%;
                height: auto;
            }

            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .header-top {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }

            .back-btn {
                width: 100%;
                justify-content: center;
            }

            .payment-methods {
                grid-template-columns: 1fr;
            }

            .modal-actions,
            .qris-footer,
            .transfer-footer {
                flex-direction: column;
            }

            .bank-item {
                flex-direction: column;
                text-align: center;
                padding: 20px;
            }

            .bank-logo {
                margin: 0 auto;
            }

            .bank-check {
                position: static;
                transform: none;
                margin-top: 10px;
            }

            .selected-bank-header {
                flex-direction: column;
                gap: 10px;
            }

            .copy-btn {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .products-grid {
                grid-template-columns: 1fr;
            }

            .cart-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .cart-item-actions {
                width: 100%;
                justify-content: space-between;
            }

            .item-price {
                min-width: auto;
            }

            .transfer-content {
                padding: 25px;
            }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <p>Processing transaction...</p>
    </div>

    <!-- Toast Notification -->
    <div class="toast" id="toast">
        <i class="fas" id="toastIcon"></i>
        <span id="toastMessage"></span>
    </div>

    <!-- Success Modal -->
    <div class="modal" id="successModal">
        <div class="modal-content">
            <div class="modal-icon">
                <i class="fas fa-check"></i>
            </div>
            <h3>Payment Successful!</h3>
            <p id="modalMessage">Transaction completed successfully</p>
            <div class="modal-actions">
                <button class="modal-btn primary" onclick="printReceipt()">
                    <i class="fas fa-print"></i> Print Receipt
                </button>
                <button class="modal-btn secondary" onclick="newTransaction()">
                    <i class="fas fa-plus"></i> New Transaction
                </button>
            </div>
        </div>
    </div>

    <!-- QRIS Modal -->
    <div class="qris-modal" id="qrisModal">
        <div class="qris-content dark">
            <div class="qris-header">
                <h2>QRIS Payment</h2>
                <p>Scan with your e-wallet or mobile banking</p>
            </div>
            
            <div class="qris-code">
                <div id="qrcode"></div>
            </div>

            <div class="qris-amount">
                <span class="label">Total Payment:</span>
                <span class="value" id="qrisAmount">Rp 0</span>
            </div>

            <div class="qris-footer">
                <button class="qris-btn done" onclick="confirmQRIS()">
                    <i class="fas fa-check-circle"></i> Done
                </button>
                <button class="qris-btn cancel" onclick="closeQRISModal()">
                    <i class="fas fa-times-circle"></i> Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- Transfer Modal -->
    <div class="transfer-modal" id="transferModal">
        <div class="transfer-content">
            <div class="transfer-header">
                <h2>Bank Transfer</h2>
                <p>Pilih bank untuk melakukan transfer</p>
            </div>

            <!-- Bank List -->
            <div class="bank-list">
                <?php foreach ($bank_accounts as $index => $bank): ?>
                <div class="bank-item <?php echo $index === 0 ? 'selected' : ''; ?>" 
                     onclick="selectBank(<?php echo $index; ?>)"
                     data-bank-index="<?php echo $index; ?>">
                    <div class="bank-logo <?php echo strtolower($bank['nama']); ?>">
                        <i class="fas <?php echo $bank['icon']; ?>"></i>
                    </div>
                    <div class="bank-details">
                        <div class="bank-name"><?php echo $bank['nama']; ?></div>
                        <div class="bank-account"><?php echo $bank['no_rekening']; ?></div>
                        <div class="bank-holder">a.n. <?php echo $bank['atas_nama']; ?></div>
                    </div>
                    <div class="bank-check">
                        <i class="fas fa-check-circle" style="color: var(--transfer); font-size: 24px; <?php echo $index === 0 ? '' : 'display: none;'; ?>"></i>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Info Rekening Terpilih -->
            <div class="selected-bank-info">
                <div class="selected-bank-header">
                    <span class="selected-label">Rekening Tujuan</span>
                    <button class="copy-btn" onclick="copyRekening()">
                        <i class="fas fa-copy"></i> Salin Nomor
                    </button>
                </div>
                <div class="selected-bank-detail">
                    <span class="selected-bank-name" id="selectedBankName">BCA</span>
                    <span class="selected-bank-account" id="selectedBankAccount">1234567890</span>
                </div>
            </div>

            <!-- Total Transfer -->
            <div class="transfer-amount">
                <span class="label">Total Pembayaran:</span>
                <span class="value" id="transferAmount">Rp 0</span>
            </div>

            <!-- Instruksi -->
            <div class="transfer-instruction">
                <i class="fas fa-info-circle"></i>
                <span>Lakukan transfer ke rekening di atas, lalu klik "Selesai"</span>
            </div>

            <!-- Footer Buttons -->
            <div class="transfer-footer">
                <button class="transfer-btn done" onclick="confirmTransfer()">
                    <i class="fas fa-check-circle"></i> Selesai
                </button>
                <button class="transfer-btn cancel" onclick="closeTransferModal()">
                    <i class="fas fa-times-circle"></i> Batal
                </button>
            </div>
        </div>
    </div>

    <div class="pos-container">
        <!-- Products Section -->
        <div class="products-section">
            <div class="products-header">
                <div class="header-top">
                    <h1>majoo POS International</h1>
                    <a href="dashboard.php" class="back-btn">
                        <i class="fas fa-arrow-left"></i> Dashboard
                    </a>
                </div>
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="🔍 Search products...">
                </div>
            </div>

            <div class="categories-wrapper">
                <div class="categories" id="categories">
                    <button class="category-btn active" data-category="all">All Categories</button>
                    <?php while($kategori = mysqli_fetch_assoc($query_kategori)): ?>
                    <button class="category-btn" data-category="<?php echo $kategori['id']; ?>">
                        <?php echo htmlspecialchars($kategori['nama_kategori']); ?>
                    </button>
                    <?php endwhile; ?>
                </div>
            </div>

            <div class="products-grid" id="productsGrid">
                <?php while($produk = mysqli_fetch_assoc($query_produk)): 
                    $stock_class = '';
                    $stock_text = '';
                    $badge = '';
                    
                    if ($produk['stok'] == 0) {
                        $stock_class = 'stock-out';
                        $stock_text = 'Out of Stock';
                        $badge = '<div class="stock-badge out">Out</div>';
                    } elseif ($produk['stok'] <= $produk['stok_minimum']) {
                        $stock_class = 'stock-low';
                        $stock_text = 'Low Stock';
                        $badge = '<div class="stock-badge low">Low</div>';
                    }
                ?>
                <div class="product-card <?php echo $produk['stok'] == 0 ? 'out-of-stock' : ''; ?>" 
                     data-id="<?php echo $produk['id']; ?>"
                     data-name="<?php echo htmlspecialchars($produk['nama_produk']); ?>"
                     data-price="<?php echo $produk['harga_jual']; ?>"
                     data-category="<?php echo $produk['kategori_id']; ?>"
                     data-stock="<?php echo $produk['stok']; ?>"
                     onclick="addToCart(<?php echo $produk['id']; ?>)">
                    
                    <?php echo $badge; ?>
                    
                    <div class="product-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <h3><?php echo htmlspecialchars($produk['nama_produk']); ?></h3>
                    <div class="product-category"><?php echo htmlspecialchars($produk['nama_kategori']); ?></div>
                    <div class="product-price">Rp <?php echo number_format($produk['harga_jual'], 0, ',', '.'); ?></div>
                    <div class="product-stock <?php echo $stock_class; ?>">
                        <i class="fas fa-cubes"></i> Stock: <?php echo $produk['stok']; ?> <?php echo $produk['satuan']; ?>
                        <?php if ($stock_text): ?>
                        <span> (<?php echo $stock_text; ?>)</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- Cart Section -->
        <div class="cart-section">
            <div class="cart-header">
                <h2>
                    <i class="fas fa-shopping-cart"></i> 
                    Shopping Cart
                </h2>
                <div class="cart-stats">
                    <span class="item-count" id="itemCount">0 items</span>
                    <button class="clear-cart" onclick="clearCart()">
                        <i class="fas fa-trash-alt"></i> Clear
                    </button>
                </div>
            </div>

            <div class="cart-items" id="cartItems">
                <div class="empty-cart">
                    <i class="fas fa-shopping-cart"></i>
                    <p>Your cart is empty</p>
                    <small>Click on products to add them to cart</small>
                </div>
            </div>

            <div class="cart-footer" id="cartFooter" style="display: none;">
                <div class="subtotal">
                    <span>Subtotal</span>
                    <span id="subtotal">Rp 0</span>
                </div>
                <div class="total">
                    <span>Total</span>
                    <span id="total">Rp 0</span>
                </div>

                <!-- Payment Methods -->
                <div class="payment-methods">
                    <div class="payment-method cash active" onclick="selectMethod('cash')">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Cash</span>
                    </div>
                    <div class="payment-method qris" onclick="selectMethod('qris')">
                        <i class="fas fa-qrcode"></i>
                        <span>QRIS</span>
                    </div>
                    <div class="payment-method transfer" onclick="selectMethod('transfer')">
                        <i class="fas fa-university"></i>
                        <span>Transfer</span>
                    </div>
                </div>

                <!-- Amount Paid (untuk Cash) -->
                <div class="payment-input" id="cashInput">
                    <label>Amount Paid</label>
                    <input type="text" id="amountPaid" placeholder="Enter amount" oninput="formatRupiah(this)" onkeyup="calculateChange()">
                </div>

                <!-- Change (untuk Cash) -->
                <div class="change-box" id="changeBox">
                    <span>Change</span>
                    <strong id="changeAmount">Rp 0</strong>
                </div>

                <!-- Checkout Button -->
                <button class="checkout-btn" id="checkoutBtn" onclick="processPayment()" disabled>
                    <i class="fas fa-bolt"></i> Process Payment
                </button>
            </div>
        </div>
    </div>

    <script>
        // ==================== //
        // GLOBAL VARIABLES
        // ==================== //
        
        let cart = <?php echo json_encode($_SESSION['cart']); ?>;
        let selectedMethod = 'cash';
        let lastTransaction = null;
        let currentTotal = 0;
        let selectedBankIndex = 0;
        let lastMethod = 'cash';
        let lastBank = 0;

        // ==================== //
        // CART FUNCTIONS
        // ==================== //
        
        function initCart() {
            updateCartDisplay();
        }

        function updateCartDisplay() {
            const cartItems = document.getElementById('cartItems');
            const cartFooter = document.getElementById('cartFooter');
            const itemCount = document.getElementById('itemCount');
            
            if (cart.length === 0) {
                cartItems.innerHTML = `
                    <div class="empty-cart">
                        <i class="fas fa-shopping-cart"></i>
                        <p>Your cart is empty</p>
                        <small>Click on products to add them to cart</small>
                    </div>
                `;
                cartFooter.style.display = 'none';
                itemCount.innerHTML = '0 items';
                return;
            }

            let subtotal = 0;
            let itemsHtml = '';

            cart.forEach((item, index) => {
                const itemTotal = item.price * item.qty;
                subtotal += itemTotal;

                itemsHtml += `
                    <div class="cart-item" data-index="${index}">
                        <div class="cart-item-info">
                            <h4>${item.name}</h4>
                            <p>Rp ${item.price.toLocaleString('id-ID')} x ${item.qty}</p>
                        </div>
                        <div class="cart-item-actions">
                            <button class="qty-btn" onclick="updateQuantity(${index}, -1)">
                                <i class="fas fa-minus"></i>
                            </button>
                            <span class="item-qty">${item.qty}</span>
                            <button class="qty-btn" onclick="updateQuantity(${index}, 1)">
                                <i class="fas fa-plus"></i>
                            </button>
                            <span class="item-price">Rp ${itemTotal.toLocaleString('id-ID')}</span>
                        </div>
                    </div>
                `;
            });

            cartItems.innerHTML = itemsHtml;
            
            document.getElementById('subtotal').innerHTML = `Rp ${subtotal.toLocaleString('id-ID')}`;
            document.getElementById('total').innerHTML = `Rp ${subtotal.toLocaleString('id-ID')}`;
            currentTotal = subtotal;
            
            const totalItems = cart.reduce((sum, item) => sum + item.qty, 0);
            itemCount.innerHTML = `${totalItems} item${totalItems !== 1 ? 's' : ''}`;
            
            cartFooter.style.display = 'block';
            
            updateCheckoutButton();
        }

        function addToCart(productId) {
            const productCard = document.querySelector(`.product-card[data-id="${productId}"]`);
            
            if (!productCard) return;
            
            if (productCard.classList.contains('out-of-stock')) {
                showToast('Product is out of stock!', 'error');
                return;
            }
            
            const name = productCard.dataset.name;
            const price = parseInt(productCard.dataset.price);
            const stock = parseInt(productCard.dataset.stock);

            const existingItem = cart.find(item => item.id === productId);

            if (existingItem) {
                if (existingItem.qty < stock) {
                    existingItem.qty++;
                    showToast(`Added another ${name} to cart`, 'success');
                } else {
                    showToast(`Maximum stock (${stock}) reached!`, 'error');
                    return;
                }
            } else {
                cart.push({
                    id: productId,
                    name: name,
                    price: price,
                    qty: 1
                });
                showToast(`${name} added to cart`, 'success');
            }

            updateCartDisplay();
        }

        function updateQuantity(index, change) {
            const item = cart[index];
            const productCard = document.querySelector(`.product-card[data-id="${item.id}"]`);
            const stock = parseInt(productCard.dataset.stock);
            
            const newQty = item.qty + change;

            if (newQty < 1) {
                cart.splice(index, 1);
                showToast(`${item.name} removed from cart`, 'success');
            } else if (newQty <= stock) {
                item.qty = newQty;
            } else {
                showToast(`Maximum stock (${stock}) reached!`, 'error');
                return;
            }

            updateCartDisplay();
        }

        function clearCart() {
            if (cart.length === 0) return;
            
            if (confirm('Are you sure you want to clear all items from cart?')) {
                cart = [];
                updateCartDisplay();
                document.getElementById('amountPaid').value = '';
                calculateChange();
                showToast('Cart cleared', 'success');
            }
        }

        // ==================== //
        // PAYMENT FUNCTIONS
        // ==================== //

        function selectMethod(method) {
            selectedMethod = method;
            
            document.querySelectorAll('.payment-method').forEach(el => {
                el.classList.remove('active');
            });
            
            document.querySelector(`.payment-method.${method}`).classList.add('active');
            
            // Tampilkan/sembunyikan input cash sesuai metode
            const cashInput = document.getElementById('cashInput');
            const changeBox = document.getElementById('changeBox');
            
            if (method === 'cash') {
                cashInput.style.display = 'block';
                changeBox.style.display = 'flex';
            } else {
                cashInput.style.display = 'none';
                changeBox.style.display = 'none';
            }
            
            updateCheckoutButton();
        }

        function formatRupiah(input) {
            let value = input.value.replace(/[^,\d]/g, '').toString();
            
            if (value) {
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
            
            calculateChange();
        }

        function calculateChange() {
            const total = currentTotal;
            const paidInput = document.getElementById('amountPaid').value;
            const paid = parseInt(paidInput.replace(/[^,\d]/g, '')) || 0;
            
            const change = paid - total;
            document.getElementById('changeAmount').innerHTML = `Rp ${change.toLocaleString('id-ID')}`;
            
            updateCheckoutButton();
        }

        function updateCheckoutButton() {
            const checkoutBtn = document.getElementById('checkoutBtn');
            
            if (cart.length === 0) {
                checkoutBtn.disabled = true;
                return;
            }
            
            if (selectedMethod === 'cash') {
                const paidInput = document.getElementById('amountPaid').value;
                const paid = parseInt(paidInput.replace(/[^,\d]/g, '')) || 0;
                checkoutBtn.disabled = !(paid >= currentTotal && paid > 0);
            } else {
                // Untuk QRIS dan Transfer, selalu enable (pembayaran dilakukan di luar sistem)
                checkoutBtn.disabled = false;
            }
        }

        function processPayment() {
            if (selectedMethod === 'cash') {
                // Cash langsung proses
                checkout();
            } else if (selectedMethod === 'qris') {
                // Tampilkan QRIS modal
                showQRIS();
            } else if (selectedMethod === 'transfer') {
                // Tampilkan Transfer modal
                showTransfer();
            }
        }

        // ==================== //
        // QRIS FUNCTIONS
        // ==================== //

        function showQRIS() {
            const total = currentTotal;
            document.getElementById('qrisAmount').innerHTML = `Rp ${total.toLocaleString('id-ID')}`;
            
            // Generate QR Code
            document.getElementById('qrcode').innerHTML = '';
            new QRCode(document.getElementById('qrcode'), {
                text: `majoo://payment?amount=${total}&merchant=MAJOO-POS`,
                width: 200,
                height: 200,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });
            
            document.getElementById('qrisModal').classList.add('active');
        }

        function closeQRISModal() {
            document.getElementById('qrisModal').classList.remove('active');
        }

        function confirmQRIS() {
            closeQRISModal();
            lastMethod = 'qris';
            checkout();
        }

        // ==================== //
        // BANK SELECTION
        // ==================== //

        function selectBank(index) {
            selectedBankIndex = index;
            
            // Update class selected
            document.querySelectorAll('.bank-item').forEach((item, i) => {
                if (i === index) {
                    item.classList.add('selected');
                } else {
                    item.classList.remove('selected');
                }
            });
            
            // Update check icon
            document.querySelectorAll('.bank-check i').forEach((check, i) => {
                if (i === index) {
                    check.style.display = 'block';
                } else {
                    check.style.display = 'none';
                }
            });
            
            // Update selected bank info
            const banks = <?php echo json_encode($bank_accounts); ?>;
            document.getElementById('selectedBankName').innerText = banks[index].nama;
            document.getElementById('selectedBankAccount').innerText = banks[index].no_rekening;
        }

        // Copy rekening
        function copyRekening() {
            const banks = <?php echo json_encode($bank_accounts); ?>;
            const rekening = banks[selectedBankIndex].no_rekening;
            
            navigator.clipboard.writeText(rekening).then(() => {
                showToast('Nomor rekening disalin!', 'success');
            }).catch(() => {
                showToast('Gagal menyalin!', 'error');
            });
        }

        function showTransfer() {
            const total = currentTotal;
            document.getElementById('transferAmount').innerHTML = `Rp ${total.toLocaleString('id-ID')}`;
            selectBank(0);
            document.getElementById('transferModal').classList.add('active');
        }

        function closeTransferModal() {
            document.getElementById('transferModal').classList.remove('active');
        }

        function confirmTransfer() {
            closeTransferModal();
            lastMethod = 'transfer';
            lastBank = selectedBankIndex;
            checkout();
        }

        // ==================== //
        // CHECKOUT FUNCTION
        // ==================== //

        function checkout() {
            const total = currentTotal;
            let paid = total; // Default untuk QRIS dan Transfer
            
            if (selectedMethod === 'cash') {
                const paidInput = document.getElementById('amountPaid').value;
                paid = parseInt(paidInput.replace(/[^,\d]/g, '')) || 0;
                
                if (paid < total) {
                    showToast('Insufficient payment amount!', 'error');
                    return;
                }
            }

            document.getElementById('loadingOverlay').classList.add('active');

            fetch('../api/process_transaction.php?action=process', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    cart: cart,
                    total: total,
                    metode: selectedMethod,
                    bayar: paid
                })
            })
            .then(res => res.json())
            .then(data => {
                document.getElementById('loadingOverlay').classList.remove('active');
                
                if (data.success) {
                    lastTransaction = data.data;
                    lastTransaction.method = selectedMethod;
                    
                    cart = [];
                    updateCartDisplay();
                    document.getElementById('amountPaid').value = '';
                    calculateChange();
                    
                    document.getElementById('modalMessage').innerHTML = 
                        `Transaction ${data.data.no_transaksi}<br>Total: Rp ${data.data.total.toLocaleString('id-ID')}`;
                    document.getElementById('successModal').classList.add('active');
                    
                    showToast('Payment successful!', 'success');
                } else {
                    showToast('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                document.getElementById('loadingOverlay').classList.remove('active');
                console.error('Error:', error);
                showToast('Transaction failed!', 'error');
            });
        }

        // ==================== //
        // PRINT RECEIPT - FIXED
        // ==================== //

        function printReceipt() {
            if (lastTransaction) {
                const receiptUrl = lastTransaction.receipt_url;
                
                // Tampilkan URL untuk debugging
                console.log('Receipt URL:', receiptUrl);
                
                if (receiptUrl) {
                    // Hapus '../' dari URL jika ada
                    let cleanUrl = receiptUrl.replace('../', '');
                    
                    // Buat URL absolut
                    let baseUrl = window.location.protocol + '//' + window.location.host + '/aplikasi-kasir/';
                    let fullUrl = baseUrl + cleanUrl;
                    
                    // Tambahkan parameter print
                    if (fullUrl.includes('?')) {
                        fullUrl += '&print=true';
                    } else {
                        fullUrl += '?print=true';
                    }
                    
                    console.log('Full URL:', fullUrl);
                    
                    // Buka di tab baru
                    const printWindow = window.open(fullUrl, '_blank');
                    
                    if (printWindow) {
                        printWindow.focus();
                        showToast('Membuka struk di tab baru...', 'success');
                    } else {
                        // Popup blocker aktif
                        showToast('Klik izinkan popup untuk melihat struk', 'warning');
                        
                        // Alternatif: buka di tab yang sama
                        if (confirm('Popup diblokir. Buka struk di tab ini?')) {
                            window.location.href = fullUrl;
                        }
                    }
                } else {
                    showToast('URL struk tidak ditemukan', 'error');
                }
            }
            
            // Tetap buka modal baru untuk transaksi berikutnya
            setTimeout(() => {
                newTransaction();
            }, 500);
        }

        // New transaction
        function newTransaction() {
            document.getElementById('successModal').classList.remove('active');
            selectMethod('cash');
            document.getElementById('amountPaid').value = '';
            calculateChange();
        }

        // ==================== //
        // UI FUNCTIONS
        // ==================== //

        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            const icon = document.getElementById('toastIcon');
            const msg = document.getElementById('toastMessage');
            
            icon.className = `fas ${type === 'success' ? 'fa-check-circle' : (type === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle')}`;
            toast.className = `toast ${type}`;
            msg.textContent = message;
            
            toast.classList.add('show');
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        // ==================== //
        // SEARCH & FILTER
        // ==================== //

        document.getElementById('searchInput').addEventListener('keyup', function() {
            const search = this.value.toLowerCase();
            const products = document.querySelectorAll('.product-card');

            products.forEach(product => {
                const name = product.dataset.name.toLowerCase();
                product.style.display = name.includes(search) ? 'block' : 'none';
            });
        });

        document.querySelectorAll('.category-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.category-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const category = this.dataset.category;
                const products = document.querySelectorAll('.product-card');

                products.forEach(product => {
                    if (category === 'all' || product.dataset.category === category) {
                        product.style.display = 'block';
                    } else {
                        product.style.display = 'none';
                    }
                });
            });
        });

        // ==================== //
        // KEYBOARD SHORTCUTS
        // ==================== //

        document.addEventListener('keydown', function(e) {
            if (e.key === 'F1') {
                e.preventDefault();
                clearCart();
            }
            if (e.key === 'F2') {
                e.preventDefault();
                const checkoutBtn = document.getElementById('checkoutBtn');
                if (!checkoutBtn.disabled) {
                    processPayment();
                }
            }
            if (e.key === 'F3') {
                e.preventDefault();
                document.getElementById('searchInput').focus();
            }
            if (e.key === '1') {
                selectMethod('cash');
            }
            if (e.key === '2') {
                selectMethod('qris');
            }
            if (e.key === '3') {
                selectMethod('transfer');
            }
        });

        // Close modals when clicking outside
        window.onclick = function(event) {
            const qrisModal = document.getElementById('qrisModal');
            const transferModal = document.getElementById('transferModal');
            const successModal = document.getElementById('successModal');
            
            if (event.target === qrisModal) {
                closeQRISModal();
            }
            if (event.target === transferModal) {
                closeTransferModal();
            }
            if (event.target === successModal) {
                newTransaction();
            }
        }

        // ==================== //
        // INITIALIZE
        // ==================== //

        initCart();
        document.getElementById('searchInput').focus();
        document.getElementById('amountPaid').addEventListener('keyup', calculateChange);
    </script>
</body>
</html>