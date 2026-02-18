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

// Handle Add Employee
if(isset($_POST['add'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = md5($_POST['password']);
    $nama = mysqli_real_escape_string($conn, $_POST['nama_lengkap']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $no_telepon = mysqli_real_escape_string($conn, $_POST['no_telepon']);
    $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);
    $role_karyawan = $_POST['role'];
    
    // Check if username exists
    $check = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username'");
    if(mysqli_num_rows($check) > 0) {
        $error = "Username already exists!";
    } else {
        $query = "INSERT INTO users (username, password, nama_lengkap, email, no_telepon, alamat, role) 
                  VALUES ('$username', '$password', '$nama', '$email', '$no_telepon', '$alamat', '$role_karyawan')";
        
        if(mysqli_query($conn, $query)) {
            // Log activity
            mysqli_query($conn, "INSERT INTO log_aktivitas (user_id, aktivitas, detail) 
                VALUES ('$user_id', 'Add Employee', 'Added new employee: $nama')");
            $success = "Employee added successfully!";
        } else {
            $error = "Error: " . mysqli_error($conn);
        }
    }
}

// Handle Edit Employee
if(isset($_POST['edit'])) {
    $id = $_POST['id'];
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $nama = mysqli_real_escape_string($conn, $_POST['nama_lengkap']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $no_telepon = mysqli_real_escape_string($conn, $_POST['no_telepon']);
    $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);
    $role_karyawan = $_POST['role'];
    
    // Check if username exists for other users
    $check = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username' AND id != '$id'");
    if(mysqli_num_rows($check) > 0) {
        $error = "Username already exists!";
    } else {
        $query = "UPDATE users SET 
                  username='$username', 
                  nama_lengkap='$nama', 
                  email='$email', 
                  no_telepon='$no_telepon', 
                  alamat='$alamat', 
                  role='$role_karyawan' 
                  WHERE id='$id'";
        
        // If password is provided, update it
        if(!empty($_POST['password'])) {
            $password = md5($_POST['password']);
            $query = "UPDATE users SET 
                      username='$username', 
                      password='$password', 
                      nama_lengkap='$nama', 
                      email='$email', 
                      no_telepon='$no_telepon', 
                      alamat='$alamat', 
                      role='$role_karyawan' 
                      WHERE id='$id'";
        }
        
        if(mysqli_query($conn, $query)) {
            // Log activity
            mysqli_query($conn, "INSERT INTO log_aktivitas (user_id, aktivitas, detail) 
                VALUES ('$user_id', 'Edit Employee', 'Edited employee: $nama')");
            $success = "Employee updated successfully!";
        } else {
            $error = "Error: " . mysqli_error($conn);
        }
    }
}

// Handle Delete Employee
if(isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Don't allow deleting yourself
    if($id == $user_id) {
        $error = "You cannot delete your own account!";
    } else {
        // Get employee name for log
        $emp = mysqli_query($conn, "SELECT nama_lengkap FROM users WHERE id = '$id'");
        $emp_data = mysqli_fetch_assoc($emp);
        $emp_name = $emp_data['nama_lengkap'];
        
        $query = "DELETE FROM users WHERE id='$id'";
        if(mysqli_query($conn, $query)) {
            // Log activity
            mysqli_query($conn, "INSERT INTO log_aktivitas (user_id, aktivitas, detail) 
                VALUES ('$user_id', 'Delete Employee', 'Deleted employee: $emp_name')");
            $success = "Employee deleted successfully!";
        } else {
            $error = "Error: " . mysqli_error($conn);
        }
    }
}

// Handle Toggle Status
if(isset($_GET['toggle'])) {
    $id = $_GET['toggle'];
    
    // Don't toggle yourself
    if($id == $user_id) {
        $error = "You cannot change your own status!";
    } else {
        $query = "UPDATE users SET is_active = NOT is_active WHERE id='$id'";
        if(mysqli_query($conn, $query)) {
            // Get employee name for log
            $emp = mysqli_query($conn, "SELECT nama_lengkap FROM users WHERE id = '$id'");
            $emp_data = mysqli_fetch_assoc($emp);
            $emp_name = $emp_data['nama_lengkap'];
            
            // Log activity
            mysqli_query($conn, "INSERT INTO log_aktivitas (user_id, aktivitas, detail) 
                VALUES ('$user_id', 'Toggle Status', 'Changed status for: $emp_name')");
            $success = "Employee status updated!";
        } else {
            $error = "Error: " . mysqli_error($conn);
        }
    }
}

// Get all employees
$query_karyawan = mysqli_query($conn, "SELECT * FROM users ORDER BY 
    CASE 
        WHEN role = 'admin' THEN 1
        WHEN role = 'kasir' THEN 2
        WHEN role = 'owner' THEN 3
        ELSE 4
    END, nama_lengkap ASC");

// Get statistics
$total_karyawan = mysqli_num_rows($query_karyawan);
$total_admin = mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE role = 'admin'")->fetch_assoc()['total'];
$total_kasir = mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE role = 'kasir'")->fetch_assoc()['total'];
$total_owner = mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE role = 'owner'")->fetch_assoc()['total'];
$active_users = mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE is_active = 1")->fetch_assoc()['total'];

// Get recent activity
$query_aktivitas = mysqli_query($conn, "SELECT la.*, u.nama_lengkap 
    FROM log_aktivitas la 
    JOIN users u ON la.user_id = u.id 
    ORDER BY la.created_at DESC 
    LIMIT 10");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Employees - Kasir Majoo</title>
    
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
            --purple: #A78BFA;
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

        .stat-icon.blue { 
            background: rgba(59, 130, 246, 0.1); 
            color: var(--info);
            border-color: rgba(59, 130, 246, 0.3);
        }
        
        .stat-icon.green { 
            background: rgba(0, 255, 178, 0.1); 
            color: var(--success);
            border-color: rgba(0, 255, 178, 0.3);
        }
        
        .stat-icon.yellow { 
            background: rgba(255, 184, 0, 0.1); 
            color: var(--warning);
            border-color: rgba(255, 184, 0, 0.3);
        }
        
        .stat-icon.purple { 
            background: rgba(167, 139, 250, 0.1); 
            color: var(--purple);
            border-color: rgba(167, 139, 250, 0.3);
        }

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

        /* Employees Table */
        .table-card {
            background: var(--secondary);
            border: 1px solid var(--border);
            border-radius: 30px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .table-header h2 {
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .table-header h2 i {
            color: var(--accent);
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
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

        .employee-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .employee-avatar {
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, var(--accent), #00ccff);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: var(--primary);
            font-weight: 600;
        }

        .employee-name {
            font-weight: 600;
            font-size: 14px;
        }

        .employee-email {
            font-size: 11px;
            color: var(--text-secondary);
        }

        .role-badge {
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            text-align: center;
            min-width: 80px;
        }

        .role-badge.admin {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .role-badge.kasir {
            background: rgba(59, 130, 246, 0.15);
            color: var(--info);
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .role-badge.owner {
            background: rgba(245, 158, 11, 0.15);
            color: var(--warning);
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .status-badge {
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 500;
            display: inline-block;
        }

        .status-badge.active {
            background: rgba(0, 255, 178, 0.1);
            color: var(--success);
            border: 1px solid rgba(0, 255, 178, 0.3);
        }

        .status-badge.inactive {
            background: rgba(255, 77, 77, 0.1);
            color: var(--danger);
            border: 1px solid rgba(255, 77, 77, 0.3);
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

        /* Activity Log */
        .activity-card {
            background: var(--secondary);
            border: 1px solid var(--border);
            border-radius: 30px;
            padding: 20px;
        }

        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .activity-header h3 {
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .activity-header h3 i {
            color: var(--accent);
        }

        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px;
            background: var(--primary);
            border: 1px solid var(--border);
            border-radius: 16px;
            transition: all 0.2s;
        }

        .activity-item:hover {
            border-color: var(--accent);
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            background: rgba(0, 255, 178, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent);
            font-size: 18px;
            border: 1px solid rgba(0, 255, 178, 0.3);
        }

        .activity-details {
            flex: 1;
        }

        .activity-text {
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 4px;
        }

        .activity-time {
            font-size: 11px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .activity-time i {
            font-size: 8px;
            color: var(--accent);
        }

        .activity-user {
            font-size: 12px;
            color: var(--accent);
            font-weight: 600;
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
            min-height: 80px;
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
            box-shadow: 0 5px 15px var(--accent-glow);
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

        .btn-cancel:hover {
            background: var(--text-secondary);
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
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .btn-primary {
                width: 100%;
                justify-content: center;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .activity-item {
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
            <a href="karyawan.php" class="menu-item active">
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
            <h1>Employee Management</h1>
            <button class="btn-primary" onclick="openAddModal()">
                <i class="fas fa-user-plus"></i> Add Employee
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

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h4>TOTAL EMPLOYEES</h4>
                    <p><?php echo $total_karyawan; ?></p>
                    <small><i class="fas fa-circle"></i> <?php echo $active_users; ?> active</small>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="stat-info">
                    <h4>ADMIN</h4>
                    <p><?php echo $total_admin; ?></p>
                    <small><i class="fas fa-circle"></i> Full access</small>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon yellow">
                    <i class="fas fa-user-clock"></i>
                </div>
                <div class="stat-info">
                    <h4>KASIR</h4>
                    <p><?php echo $total_kasir; ?></p>
                    <small><i class="fas fa-circle"></i> POS access</small>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fas fa-user-cog"></i>
                </div>
                <div class="stat-info">
                    <h4>OWNER</h4>
                    <p><?php echo $total_owner; ?></p>
                    <small><i class="fas fa-circle"></i> Reports only</small>
                </div>
            </div>
        </div>

        <!-- Employees Table -->
        <div class="table-card">
            <div class="table-header">
                <h2><i class="fas fa-users"></i> Employees List</h2>
                <span style="color: var(--text-secondary); font-size: 12px;"><?php echo $total_karyawan; ?> total employees</span>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Username</th>
                            <th>Contact</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($query_karyawan) > 0): ?>
                            <?php while($emp = mysqli_fetch_assoc($query_karyawan)): ?>
                            <tr>
                                <td>
                                    <div class="employee-info">
                                        <div class="employee-avatar">
                                            <?php echo strtoupper(substr($emp['nama_lengkap'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="employee-name"><?php echo htmlspecialchars($emp['nama_lengkap']); ?></div>
                                            <div class="employee-email"><?php echo htmlspecialchars($emp['email'] ?: '-'); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($emp['username']); ?></td>
                                <td>
                                    <div><?php echo htmlspecialchars($emp['no_telepon'] ?: '-'); ?></div>
                                    <small style="color: var(--text-secondary);"><?php echo htmlspecialchars($emp['alamat'] ?: '-'); ?></small>
                                </td>
                                <td>
                                    <span class="role-badge <?php echo $emp['role']; ?>">
                                        <i class="fas <?php 
                                            echo $emp['role'] == 'admin' ? 'fa-shield-alt' : 
                                                ($emp['role'] == 'kasir' ? 'fa-cash-register' : 'fa-chart-line'); 
                                        ?>"></i>
                                        <?php echo ucfirst($emp['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $emp['is_active'] ? 'active' : 'inactive'; ?>">
                                        <i class="fas fa-circle"></i>
                                        <?php echo $emp['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if($emp['last_login']): ?>
                                        <div><?php echo date('d M Y', strtotime($emp['last_login'])); ?></div>
                                        <small style="color: var(--text-secondary);"><?php echo date('H:i', strtotime($emp['last_login'])); ?></small>
                                    <?php else: ?>
                                        <span style="color: var(--text-secondary);">Never</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="actions">
                                        <div class="action-btn" onclick="editEmployee(<?php echo $emp['id']; ?>)" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </div>
                                        <?php if($emp['id'] != $user_id): ?>
                                        <div class="action-btn" onclick="toggleStatus(<?php echo $emp['id']; ?>)" title="Toggle Status">
                                            <i class="fas fa-power-off"></i>
                                        </div>
                                        <div class="action-btn delete" onclick="deleteEmployee(<?php echo $emp['id']; ?>)" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 50px;">
                                    <i class="fas fa-users" style="font-size: 48px; color: var(--border); margin-bottom: 15px;"></i>
                                    <p>No employees found</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Activity Log -->
        <div class="activity-card">
            <div class="activity-header">
                <h3><i class="fas fa-history"></i> Recent Activity</h3>
                <span style="color: var(--text-secondary); font-size: 12px;">Last 10 actions</span>
            </div>
            <div class="activity-list">
                <?php if(mysqli_num_rows($query_aktivitas) > 0): ?>
                    <?php while($log = mysqli_fetch_assoc($query_aktivitas)): ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <i class="fas <?php 
                                echo strpos($log['aktivitas'], 'Add') !== false ? 'fa-user-plus' : 
                                    (strpos($log['aktivitas'], 'Edit') !== false ? 'fa-user-edit' : 
                                    (strpos($log['aktivitas'], 'Delete') !== false ? 'fa-user-minus' : 'fa-circle')); 
                            ?>"></i>
                        </div>
                        <div class="activity-details">
                            <div class="activity-text">
                                <span class="activity-user"><?php echo htmlspecialchars($log['nama_lengkap']); ?></span>
                                <?php echo htmlspecialchars($log['aktivitas']); ?>
                            </div>
                            <div class="activity-time">
                                <i class="fas fa-circle"></i>
                                <?php echo date('d M Y H:i', strtotime($log['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
                        <i class="fas fa-history" style="font-size: 40px; margin-bottom: 10px;"></i>
                        <p>No activity recorded</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Floating Action Button -->
    <div class="fab" onclick="openAddModal()">
        <i class="fas fa-user-plus"></i>
    </div>

    <!-- Add/Edit Modal -->
    <div class="modal" id="employeeModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add New Employee</h2>
                <div class="close-modal" onclick="closeModal()">&times;</div>
            </div>
            
            <form method="POST" id="employeeForm">
                <input type="hidden" name="id" id="employeeId">
                
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" class="form-control" name="nama_lengkap" id="nama_lengkap" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" class="form-control" name="username" id="username" required>
                    </div>
                    <div class="form-group">
                        <label>Role</label>
                        <select class="form-control" name="role" id="role" required>
                            <option value="">Select Role</option>
                            <option value="admin">Admin</option>
                            <option value="kasir">Kasir</option>
                            <option value="owner">Owner</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input type="email" class="form-control" name="email" id="email">
                </div>

                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" class="form-control" name="no_telepon" id="no_telepon">
                </div>

                <div class="form-group">
                    <label>Address</label>
                    <textarea class="form-control" name="alamat" id="alamat"></textarea>
                </div>

                <div class="form-group" id="passwordGroup">
                    <label>Password</label>
                    <input type="password" class="form-control" name="password" id="password">
                    <small style="color: var(--text-secondary);">Leave empty to keep current password</small>
                </div>

                <div class="modal-footer">
                    <button type="submit" name="add" id="btnSubmit" class="btn-save">Save Employee</button>
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
            document.body.style.overflow = document.getElementById('sidebar').classList.contains('active') ? 'hidden' : '';
        }

        function closeMenu() {
            document.getElementById('sidebar').classList.remove('active');
            document.getElementById('overlay').classList.remove('active');
            document.body.style.overflow = '';
        }

        // Modal functions
        function openAddModal() {
            document.getElementById('modalTitle').innerText = 'Add New Employee';
            document.getElementById('employeeForm').reset();
            document.getElementById('employeeId').value = '';
            document.getElementById('btnSubmit').name = 'add';
            document.getElementById('passwordGroup').style.display = 'block';
            document.getElementById('password').required = true;
            document.getElementById('employeeModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('employeeModal').classList.remove('active');
        }

        // Edit employee
        function editEmployee(id) {
            // For demo purposes, redirect to edit page
            // In production, you'd fetch data via AJAX
            window.location.href = 'karyawan.php?edit=' + id;
        }

        // Toggle status
        function toggleStatus(id) {
            if(confirm('Are you sure you want to toggle this employee\'s status?')) {
                window.location.href = 'karyawan.php?toggle=' + id;
            }
        }

        // Delete employee
        function deleteEmployee(id) {
            if(confirm('Are you sure you want to delete this employee? This action cannot be undone.')) {
                window.location.href = 'karyawan.php?delete=' + id;
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('employeeModal');
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
            document.querySelectorAll('.btn-primary, .action-btn, .btn-save, .btn-cancel').forEach(element => {
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