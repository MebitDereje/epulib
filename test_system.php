<?php
/**
 * System Test Script - Ethiopian Police University Library Management System
 * This script tests basic functionality to ensure the system is working correctly
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';

echo "<h1>Ethiopian Police University Library Management System - Test Results</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .test-result { padding: 10px; margin: 10px 0; border-radius: 5px; }
    .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
</style>";

// Test 1: Database Connection
echo "<h2>Test 1: Database Connection</h2>";
try {
    $pdo = get_db_connection();
    echo "<div class='test-result success'>✓ Database connection successful</div>";
} catch (Exception $e) {
    echo "<div class='test-result error'>✗ Database connection failed: " . $e->getMessage() . "</div>";
    exit();
}

// Test 2: Check if tables exist
echo "<h2>Test 2: Database Tables</h2>";
$required_tables = ['admins', 'users', 'books', 'categories', 'borrow_records', 'fines', 'security_logs', 'system_settings'];
foreach ($required_tables as $table) {
    try {
        $sql = "SELECT COUNT(*) FROM $table";
        $result = execute_query($sql);
        $count = $result->fetchColumn();
        echo "<div class='test-result success'>✓ Table '$table' exists with $count records</div>";
    } catch (Exception $e) {
        echo "<div class='test-result error'>✗ Table '$table' missing or error: " . $e->getMessage() . "</div>";
    }
}

// Test 3: Check admin accounts
echo "<h2>Test 3: Admin Accounts</h2>";
try {
    $sql = "SELECT username, full_name, role FROM admins WHERE status = 'active'";
    $result = execute_query($sql);
    $admins = $result->fetchAll();
    
    if (!empty($admins)) {
        echo "<div class='test-result success'>✓ Found " . count($admins) . " active admin accounts:</div>";
        foreach ($admins as $admin) {
            echo "<div class='test-result info'>- {$admin['username']} ({$admin['full_name']}) - Role: {$admin['role']}</div>";
        }
    } else {
        echo "<div class='test-result error'>✗ No active admin accounts found</div>";
    }
} catch (Exception $e) {
    echo "<div class='test-result error'>✗ Error checking admin accounts: " . $e->getMessage() . "</div>";
}

// Test 4: Check sample data
echo "<h2>Test 4: Sample Data</h2>";
try {
    // Check categories
    $sql = "SELECT COUNT(*) FROM categories";
    $result = execute_query($sql);
    $category_count = $result->fetchColumn();
    echo "<div class='test-result success'>✓ Categories: $category_count</div>";
    
    // Check books
    $sql = "SELECT COUNT(*) FROM books";
    $result = execute_query($sql);
    $book_count = $result->fetchColumn();
    echo "<div class='test-result success'>✓ Books: $book_count</div>";
    
    // Check users
    $sql = "SELECT COUNT(*) FROM users";
    $result = execute_query($sql);
    $user_count = $result->fetchColumn();
    echo "<div class='test-result success'>✓ Users: $user_count</div>";
    
    // Check borrow records
    $sql = "SELECT COUNT(*) FROM borrow_records";
    $result = execute_query($sql);
    $borrow_count = $result->fetchColumn();
    echo "<div class='test-result success'>✓ Borrow Records: $borrow_count</div>";
    
} catch (Exception $e) {
    echo "<div class='test-result error'>✗ Error checking sample data: " . $e->getMessage() . "</div>";
}

// Test 5: Check views
echo "<h2>Test 5: Database Views</h2>";
$views = ['active_borrowings', 'overdue_books', 'library_statistics'];
foreach ($views as $view) {
    try {
        $sql = "SELECT COUNT(*) FROM $view";
        $result = execute_query($sql);
        $count = $result->fetchColumn();
        echo "<div class='test-result success'>✓ View '$view' working with $count records</div>";
    } catch (Exception $e) {
        echo "<div class='test-result error'>✗ View '$view' error: " . $e->getMessage() . "</div>";
    }
}

// Test 6: Authentication Functions
echo "<h2>Test 6: Authentication Functions</h2>";
try {
    // Test password verification for admin account
    $test_result = authenticate_user('admin', 'admin123');
    if ($test_result) {
        echo "<div class='test-result success'>✓ Admin authentication working</div>";
    } else {
        echo "<div class='test-result error'>✗ Admin authentication failed</div>";
    }
    
    // Test student authentication
    $test_result = authenticate_user('STU001', 'STU001');
    if ($test_result) {
        echo "<div class='test-result success'>✓ Student authentication working</div>";
    } else {
        echo "<div class='test-result error'>✗ Student authentication failed</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='test-result error'>✗ Authentication test error: " . $e->getMessage() . "</div>";
}

// Test 7: System Settings
echo "<h2>Test 7: System Settings</h2>";
try {
    $sql = "SELECT setting_key, setting_value FROM system_settings";
    $result = execute_query($sql);
    $settings = $result->fetchAll();
    
    echo "<div class='test-result success'>✓ System settings loaded:</div>";
    foreach ($settings as $setting) {
        echo "<div class='test-result info'>- {$setting['setting_key']}: {$setting['setting_value']}</div>";
    }
} catch (Exception $e) {
    echo "<div class='test-result error'>✗ Error loading system settings: " . $e->getMessage() . "</div>";
}

echo "<h2>Test Summary</h2>";
echo "<div class='test-result info'>
<strong>Login Credentials for Testing:</strong><br>
• Admin: username = 'admin', password = 'admin123'<br>
• Librarian: username = 'librarian', password = 'librarian123'<br>
• Student/Staff: username = ID number (e.g., 'STU001'), password = same as username<br><br>
<strong>Next Steps:</strong><br>
1. Access the system at index.php<br>
2. Login with admin credentials<br>
3. Test book management at admin/books.php<br>
4. Test user management at admin/users.php<br>
5. View dashboard statistics at admin/index.php
</div>";
?>