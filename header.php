<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Ethiopian Police University Library</title>
    <link rel="stylesheet" href="<?php echo isset($css_path) ? $css_path : '../assets/css/'; ?>style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <header class="main-header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                                        <img src="https://www.epu.edu.et/wp-content/uploads/2025/05/EPU-LOGO-1-996x1024.webp" alt="Description of the image" width="50" height="50">

                    <div class="logo-text">
                        <h1>Ethiopian Police University</h1>
                        <p>Library Management System</p>
                    </div>
                </div>
                
                <?php if (is_logged_in()): ?>
                <nav class="main-nav">
                    <ul>
                        <?php
                        $current_user = get_logged_in_user();
                        $role = $current_user['role'];
                        
                        // Navigation based on user role
                        if ($role === 'admin'): ?>
                            <li><a href="../admin/index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                            <li><a href="../admin/books.php"><i class="fas fa-book"></i> Books</a></li>
                            <li><a href="../admin/users.php"><i class="fas fa-users"></i> Users</a></li>
                            <li><a href="../admin/reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                            <li><a href="../admin/settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                        <?php elseif ($role === 'librarian'): ?>
                            <li><a href="../librarian/index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                            <li><a href="../librarian/books.php"><i class="fas fa-book"></i> Books</a></li>
                            <li><a href="../librarian/borrowing.php"><i class="fas fa-hand-holding"></i> Borrowing</a></li>
                            <li><a href="../librarian/returns.php"><i class="fas fa-undo"></i> Returns</a></li>
                            <li><a href="../librarian/reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                            <li><a href="../librarian/about.php"><i class="fas fa-info-circle"></i> About</a></li>
                        <?php else: // student or staff ?>
                            <li><a href="../student/index.php"><i class="fas fa-home"></i> Home</a></li>
                            <li><a href="../student/search.php"><i class="fas fa-search"></i> Search Books</a></li>
                            <li><a href="../student/my-books.php"><i class="fas fa-book-reader"></i> My Books</a></li>
                            <li><a href="../student/history.php"><i class="fas fa-history"></i> History</a></li>
                            <li><a href="../student/fines.php"><i class="fas fa-money-bill"></i> Fines</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
                
                <div class="user-menu">
                    <div class="user-info">
                        <span class="user-name"><?php echo htmlspecialchars($current_user['full_name']); ?></span>
                        <span class="user-role"><?php echo ucfirst($current_user['role']); ?></span>
                    </div>
                    <div class="user-actions">
                        <a href="../profile.php" class="btn btn-sm"><i class="fas fa-user"></i> Profile</a>
                        <a href="../logout.php" class="btn btn-sm btn-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </header>
    
    <main class="main-content">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>