<?php
require_once 'config.php';

// Cek login
if (!isLoggedIn()) {
    sendError('Unauthorized', 401);
}

$action = $_GET['action'] ?? 'list';
$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = (int)($_GET['limit'] ?? 20);
$offset = ($page - 1) * $limit;

switch ($action) {
    case 'list':
        getProducts($conn, $category, $search, $limit, $offset);
        break;
    case 'detail':
        getProductDetail($conn, $_GET['id'] ?? 0);
        break;
    case 'categories':
        getCategories($conn);
        break;
    case 'lowstock':
        getLowStock($conn);
        break;
    default:
        sendError('Invalid action');
}

function getProducts($conn, $category, $search, $limit, $offset) {
    $where = "WHERE p.is_active = 1";
    
    if ($category && $category != 'all') {
        $where .= " AND p.kategori_id = '$category'";
    }
    
    if ($search) {
        $search = mysqli_real_escape_string($conn, $search);
        $where .= " AND (p.nama_produk LIKE '%$search%' OR p.kode_produk LIKE '%$search%')";
    }
    
    // Get total count
    $count_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM produk p $where");
    $total = mysqli_fetch_assoc($count_query)['total'];
    
    // Get products
    $query = "SELECT p.*, k.nama_kategori 
              FROM produk p 
              LEFT JOIN kategori k ON p.kategori_id = k.id 
              $where 
              ORDER BY p.nama_produk ASC 
              LIMIT $limit OFFSET $offset";
    
    $result = mysqli_query($conn, $query);
    $products = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $products[] = [
            'id' => $row['id'],
            'kode' => $row['kode_produk'],
            'nama' => $row['nama_produk'],
            'kategori' => $row['nama_kategori'],
            'harga' => (int)$row['harga_jual'],
            'stok' => (int)$row['stok'],
            'stok_minimum' => (int)$row['stok_minimum'],
            'satuan' => $row['satuan'],
            'status' => $row['stok'] <= $row['stok_minimum'] ? 'low' : 'normal'
        ];
    }
    
    sendResponse(true, 'Products retrieved', [
        'products' => $products,
        'total' => $total,
        'page' => $page,
        'total_pages' => ceil($total / $limit)
    ]);
}

function getProductDetail($conn, $id) {
    if (!$id) {
        sendError('Product ID required');
    }
    
    $query = mysqli_query($conn, "SELECT p.*, k.nama_kategori 
                                  FROM produk p 
                                  LEFT JOIN kategori k ON p.kategori_id = k.id 
                                  WHERE p.id = '$id'");
    
    if (mysqli_num_rows($query) == 0) {
        sendError('Product not found');
    }
    
    $row = mysqli_fetch_assoc($query);
    
    $product = [
        'id' => $row['id'],
        'kode' => $row['kode_produk'],
        'nama' => $row['nama_produk'],
        'kategori' => $row['nama_kategori'],
        'kategori_id' => $row['kategori_id'],
        'harga_beli' => (int)$row['harga_beli'],
        'harga_jual' => (int)$row['harga_jual'],
        'stok' => (int)$row['stok'],
        'stok_minimum' => (int)$row['stok_minimum'],
        'satuan' => $row['satuan'],
        'deskripsi' => $row['deskripsi'],
        'is_active' => (bool)$row['is_active']
    ];
    
    sendResponse(true, 'Product detail retrieved', $product);
}

function getCategories($conn) {
    $result = mysqli_query($conn, "SELECT * FROM kategori ORDER BY nama_kategori ASC");
    $categories = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[] = [
            'id' => $row['id'],
            'nama' => $row['nama_kategori'],
            'slug' => $row['slug'],
            'icon' => $row['icon']
        ];
    }
    
    sendResponse(true, 'Categories retrieved', $categories);
}

function getLowStock($conn) {
    $result = mysqli_query($conn, "SELECT p.*, k.nama_kategori 
                                   FROM produk p 
                                   LEFT JOIN kategori k ON p.kategori_id = k.id 
                                   WHERE p.stok <= p.stok_minimum 
                                   ORDER BY p.stok ASC 
                                   LIMIT 10");
    
    $products = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $products[] = [
            'id' => $row['id'],
            'nama' => $row['nama_produk'],
            'kategori' => $row['nama_kategori'],
            'stok' => (int)$row['stok'],
            'stok_minimum' => (int)$row['stok_minimum'],
            'status' => $row['stok'] == 0 ? 'out' : 'low'
        ];
    }
    
    sendResponse(true, 'Low stock products retrieved', $products);
}
?>