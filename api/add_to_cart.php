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

$data = getPostData();
$action = $_GET['action'] ?? $data['action'] ?? '';

switch ($action) {
    case 'add':
        addToCart($conn, $data);
        break;
    case 'update':
        updateCart($conn, $data);
        break;
    case 'remove':
        removeFromCart($conn, $data);
        break;
    case 'clear':
        clearCart();
        break;
    case 'get':
        getCart();
        break;
    default:
        sendError('Invalid action');
}

function addToCart($conn, $data) {
    $produk_id = $data['produk_id'] ?? 0;
    $qty = $data['qty'] ?? 1;
    
    if (!$produk_id) {
        sendError('Product ID required');
    }
    
    // Cek produk
    $query = mysqli_query($conn, "SELECT * FROM produk WHERE id = '$produk_id' AND is_active = 1");
    if (mysqli_num_rows($query) == 0) {
        sendError('Product not found');
    }
    
    $produk = mysqli_fetch_assoc($query);
    
    // Cek stok
    if ($produk['stok'] < $qty) {
        sendError('Insufficient stock. Available: ' . $produk['stok']);
    }
    
    // Initialize cart if not exists
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    // Add to cart
    $found = false;
    foreach ($_SESSION['cart'] as &$item) {
        if ($item['id'] == $produk_id) {
            $item['qty'] += $qty;
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        $_SESSION['cart'][] = [
            'id' => $produk['id'],
            'kode' => $produk['kode_produk'],
            'nama' => $produk['nama_produk'],
            'harga' => $produk['harga_jual'],
            'qty' => $qty,
            'stok' => $produk['stok']
        ];
    }
    
    sendResponse(true, 'Product added to cart', [
        'cart' => $_SESSION['cart'],
        'total_items' => count($_SESSION['cart']),
        'total_qty' => array_sum(array_column($_SESSION['cart'], 'qty')),
        'total_price' => array_sum(array_map(function($item) {
            return $item['harga'] * $item['qty'];
        }, $_SESSION['cart']))
    ]);
}

function updateCart($conn, $data) {
    $produk_id = $data['produk_id'] ?? 0;
    $qty = $data['qty'] ?? 1;
    
    if (!$produk_id) {
        sendError('Product ID required');
    }
    
    if (!isset($_SESSION['cart'])) {
        sendError('Cart is empty');
    }
    
    // Cek stok
    $query = mysqli_query($conn, "SELECT stok FROM produk WHERE id = '$produk_id'");
    $produk = mysqli_fetch_assoc($query);
    
    if ($produk['stok'] < $qty) {
        sendError('Insufficient stock. Available: ' . $produk['stok']);
    }
    
    foreach ($_SESSION['cart'] as &$item) {
        if ($item['id'] == $produk_id) {
            if ($qty <= 0) {
                // Remove if qty is 0
                $_SESSION['cart'] = array_filter($_SESSION['cart'], function($i) use ($produk_id) {
                    return $i['id'] != $produk_id;
                });
                $_SESSION['cart'] = array_values($_SESSION['cart']);
            } else {
                $item['qty'] = $qty;
            }
            break;
        }
    }
    
    sendResponse(true, 'Cart updated', [
        'cart' => $_SESSION['cart'],
        'total_items' => count($_SESSION['cart']),
        'total_qty' => array_sum(array_column($_SESSION['cart'], 'qty')),
        'total_price' => array_sum(array_map(function($item) {
            return $item['harga'] * $item['qty'];
        }, $_SESSION['cart']))
    ]);
}

function removeFromCart($conn, $data) {
    $produk_id = $data['produk_id'] ?? 0;
    
    if (!$produk_id) {
        sendError('Product ID required');
    }
    
    if (isset($_SESSION['cart'])) {
        $_SESSION['cart'] = array_filter($_SESSION['cart'], function($item) use ($produk_id) {
            return $item['id'] != $produk_id;
        });
        $_SESSION['cart'] = array_values($_SESSION['cart']);
    }
    
    sendResponse(true, 'Product removed from cart', [
        'cart' => $_SESSION['cart'] ?? [],
        'total_items' => count($_SESSION['cart'] ?? []),
        'total_qty' => array_sum(array_column($_SESSION['cart'] ?? [], 'qty')),
        'total_price' => array_sum(array_map(function($item) {
            return $item['harga'] * $item['qty'];
        }, $_SESSION['cart'] ?? []))
    ]);
}

function clearCart() {
    unset($_SESSION['cart']);
    sendResponse(true, 'Cart cleared', [
        'cart' => [],
        'total_items' => 0,
        'total_qty' => 0,
        'total_price' => 0
    ]);
}

function getCart() {
    $cart = $_SESSION['cart'] ?? [];
    sendResponse(true, 'Cart retrieved', [
        'cart' => $cart,
        'total_items' => count($cart),
        'total_qty' => array_sum(array_column($cart, 'qty')),
        'total_price' => array_sum(array_map(function($item) {
            return $item['harga'] * $item['qty'];
        }, $cart))
    ]);
}
?>