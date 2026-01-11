<?php
// Student/Staff Dashboard - Main entry point for students and staff
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Start secure session
start_secure_session();

// Check if user is logged in and has student or staff role
if (!is_logged_in() || (!has_role('student') && !has_role('staff'))) {
    header('Location: ../index.php');
    exit();
}

include '../includes/header.php';
?>

<div class="container">
    <h1>Library Portal</h1>
    <p>Welcome to the Ethiopian Police University Library</p>
    
    <!-- Student/Staff-specific content will be added in later tasks -->
    <div class="quick-actions">
        <a href="search.php" class="btn btn-primary">Search Books</a>
        <a href="my-books.php" class="btn btn-primary">My Borrowed Books</a>
        <a href="history.php" class="btn btn-primary">Borrowing History</a>
        <a href="fines.php" class="btn btn-primary">My Fines</a>
    </div>
</div>

<?php include '../includes/footer.php'; ?>