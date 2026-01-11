<?php
/**
 * Librarian Returns Management - Ethiopian Police University Library Management System
 * Handles book return operations and fine management for librarians
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';

// Start secure session
start_secure_session();

// Check if user is logged in and has librarian role
if (!is_logged_in() || !has_role('librarian')) {
    header('Location: ../index.php');
    exit();
}

$page_title = 'Returns Management';
$success_message = '';
$error_message = '';

// Get system settings
$settings_sql = "SELECT setting_key, setting_value FROM system_settings WHERE setting_key = 'fine_per_day'";
$settings_result = execute_query($settings_sql);
$fine_per_day = 2.00; // Default
if ($row = $settings_result->fetch()) {
    $fine_per_day = (float)$row['setting_value'];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // Verify CSRF token
    if (!verify_csrf_token($csrf_token)) {
        $error_message = 'Invalid request. Please try again.';
    } else {
        switch ($action) {
            case 'process_return':
                $borrow_id = (int)($_POST['borrow_id'] ?? 0);
                $return_condition = sanitize_input($_POST['return_condition'] ?? 'good');
                $notes = sanitize_input($_POST['notes'] ?? '');
                
                if ($borrow_id <= 0) {
                    $error_message = 'Invalid borrowing record.';
                } else {
                    try {
                        // Get borrow record details
                        $borrow_sql = "SELECT br.*, u.full_name, u.id_number, b.title, b.author 
                                      FROM borrow_records br 
                                      JOIN users u ON br.user_id = u.user_id 
                                      JOIN books b ON br.book_id = b.book_id 
                                      WHERE br.borrow_id = ? AND br.return_date IS NULL";
                        $borrow_result = execute_query($borrow_sql, [$borrow_id]);
                        $borrow_record = $borrow_result->fetch();
                        
                        if (!$borrow_record) {
                            $error_message = 'Borrowing record not found or already returned.';
                            break;
                        }
                        
                        // Calculate return date and potential fine
                        $return_date = date('Y-m-d');
                        $due_date = $borrow_record['due_date'];
                        $days_overdue = 0;
                        $fine_amount = 0;
                        
                        if ($return_date > $due_date) {
                            $days_overdue = (strtotime($return_date) - strtotime($due_date)) / (60 * 60 * 24);
                            $fine_amount = $days_overdue * $fine_per_day;
                        }
                        
                        // Update return record with condition and notes
                        $return_notes = $notes;
                        if ($return_condition !== 'good') {
                            $return_notes = "Condition: " . ucfirst($return_condition) . ". " . $notes;
                        }
                        
                        $update_sql = "UPDATE borrow_records 
                                      SET return_date = ?, notes = CONCAT(COALESCE(notes, ''), ?) 
                                      WHERE borrow_id = ?";
                        $notes_addition = $return_notes ? "\nReturn: " . $return_notes : "";
                        execute_query($update_sql, [$return_date, $notes_addition, $borrow_id]);
                        
                        $success_message = "Book '{$borrow_record['title']}' successfully returned by {$borrow_record['full_name']}.";
                        
                        if ($fine_amount > 0) {
                            $success_message .= " Fine of " . number_format($fine_amount, 2) . " ETB has been automatically calculated for {$days_overdue} day(s) overdue.";
                        }
                        
                        log_security_event("Book returned: {$borrow_record['title']} by {$borrow_record['full_name']}", $_SESSION['user_id']);
                        
                    } catch (Exception $e) {
                        $error_message = 'Error processing return. Please try again.';
                        error_log("Return processing error: " . $e->getMessage());
                    }
                }
                break;
                
            case 'waive_fine':
                $fine_id = (int)($_POST['fine_id'] ?? 0);
                $waive_reason = sanitize_input($_POST['waive_reason'] ?? '');
                
                if ($fine_id <= 0 || empty($waive_reason)) {
                    $error_message = 'Please provide a valid fine ID and reason for waiving.';
                } else {
                    try {
                        // Get fine details
                        $fine_sql = "SELECT f.*, u.full_name, u.id_number, b.title 
                                    FROM fines f 
                                    JOIN borrow_records br ON f.borrow_id = br.borrow_id 
                                    JOIN users u ON f.user_id = u.user_id 
                                    JOIN books b ON br.book_id = b.book_id 
                                    WHERE f.fine_id = ? AND f.payment_status = 'unpaid'";
                        $fine_result = execute_query($fine_sql, [$fine_id]);
                        $fine_record = $fine_result->fetch();
                        
                        if (!$fine_record) {
                            $error_message = 'Fine not found or already processed.';
                            break;
                        }
                        
                        // Waive the fine
                        $waive_sql = "UPDATE fines 
                                     SET payment_status = 'waived', 
                                         payment_date = CURDATE(), 
                                         notes = CONCAT(COALESCE(notes, ''), ?) 
                                     WHERE fine_id = ?";
                        $waive_note = "\nWaived by librarian: " . $waive_reason;
                        execute_query($waive_sql, [$waive_note, $fine_id]);
                        
                        $success_message = "Fine of " . number_format($fine_record['fine_amount'], 2) . " ETB waived for {$fine_record['full_name']}.";
                        log_security_event("Fine waived: {$fine_record['fine_amount']} ETB for {$fine_record['full_name']}", $_SESSION['user_id']);
                        
                    } catch (Exception $e) {
                        $error_message = 'Error waiving fine. Please try again.';
                        error_log("Fine waiving error: " . $e->getMessage());
                    }
                }
                break;
                
            case 'mark_fine_paid':
                $fine_id = (int)($_POST['fine_id'] ?? 0);
                $payment_method = sanitize_input($_POST['payment_method'] ?? '');
                $payment_notes = sanitize_input($_POST['payment_notes'] ?? '');
                
                if ($fine_id <= 0 || empty($payment_method)) {
                    $error_message = 'Please provide valid fine ID and payment method.';
                } else {
                    try {
                        // Get fine details
                        $fine_sql = "SELECT f.*, u.full_name, u.id_number 
                                    FROM fines f 
                                    JOIN users u ON f.user_id = u.user_id 
                                    WHERE f.fine_id = ? AND f.payment_status = 'unpaid'";
                        $fine_result = execute_query($fine_sql, [$fine_id]);
                        $fine_record = $fine_result->fetch();
                        
                        if (!$fine_record) {
                            $error_message = 'Fine not found or already processed.';
                            break;
                        }
                        
                        // Mark fine as paid
                        $payment_sql = "UPDATE fines 
                                       SET payment_status = 'paid', 
                                           payment_date = CURDATE(), 
                                           payment_method = ?,
                                           notes = CONCAT(COALESCE(notes, ''), ?) 
                                       WHERE fine_id = ?";
                        $payment_note = $payment_notes ? "\nPayment: " . $payment_notes : "";
                        execute_query($payment_sql, [$payment_method, $payment_note, $fine_id]);
                        
                        $success_message = "Fine of " . number_format($fine_record['fine_amount'], 2) . " ETB marked as paid for {$fine_record['full_name']}.";
                        log_security_event("Fine payment recorded: {$fine_record['fine_amount']} ETB for {$fine_record['full_name']}", $_SESSION['user_id']);
                        
                    } catch (Exception $e) {
                        $error_message = 'Error recording payment. Please try again.';
                        error_log("Fine payment error: " . $e->getMessage());
                    }
                }
                break;
        }
    }
}

// Get filter parameters
$search = sanitize_input($_GET['search'] ?? '');
$status_filter = sanitize_input($_GET['status'] ?? 'pending_return');
$department_filter = sanitize_input($_GET['department'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Build queries based on status filter
if ($status_filter === 'pending_return') {
    // Show books that need to be returned
    $where_conditions = ['br.return_date IS NULL'];
    $params = [];
    
    if (!empty($search)) {
        $where_conditions[] = "(u.full_name LIKE ? OR u.id_number LIKE ? OR b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
    }
    
    if (!empty($department_filter)) {
        $where_conditions[] = "u.department = ?";
        $params[] = $department_filter;
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    // Get total count for pagination
    $count_sql = "SELECT COUNT(*) as total 
                  FROM borrow_records br 
                  JOIN users u ON br.user_id = u.user_id 
                  JOIN books b ON br.book_id = b.book_id 
                  $where_clause";
    $count_result = execute_query($count_sql, $params);
    $total_records = $count_result->fetch()['total'];
    
    // Get pending returns
    $records_sql = "SELECT br.*, u.full_name, u.id_number, u.department, u.phone, u.email,
                           b.title, b.author, b.isbn, c.category_name,
                           DATEDIFF(CURDATE(), br.due_date) as days_overdue,
                           CASE 
                               WHEN br.due_date < CURDATE() THEN 'overdue'
                               WHEN DATEDIFF(br.due_date, CURDATE()) <= 3 THEN 'due_soon'
                               ELSE 'normal'
                           END as urgency_status,
                           (CASE WHEN br.due_date < CURDATE() 
                            THEN DATEDIFF(CURDATE(), br.due_date) * ? 
                            ELSE 0 END) as potential_fine
                    FROM borrow_records br 
                    JOIN users u ON br.user_id = u.user_id 
                    JOIN books b ON br.book_id = b.book_id 
                    JOIN categories c ON b.category_id = c.category_id 
                    $where_clause
                    ORDER BY 
                        CASE 
                            WHEN br.due_date < CURDATE() THEN 1
                            WHEN DATEDIFF(br.due_date, CURDATE()) <= 3 THEN 2
                            ELSE 3
                        END,
                        br.due_date ASC
                    LIMIT $per_page OFFSET $offset";
    
    $params_with_fine = array_merge([$fine_per_day], $params);
    $records_result = execute_query($records_sql, $params_with_fine);
    $records = $records_result->fetchAll();
    
} else {
    // Show unpaid fines
    $where_conditions = ["f.payment_status = 'unpaid'"];
    $params = [];
    
    if (!empty($search)) {
        $where_conditions[] = "(u.full_name LIKE ? OR u.id_number LIKE ? OR b.title LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param]);
    }
    
    if (!empty($department_filter)) {
        $where_conditions[] = "u.department = ?";
        $params[] = $department_filter;
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    // Get total count for pagination
    $count_sql = "SELECT COUNT(*) as total 
                  FROM fines f 
                  JOIN borrow_records br ON f.borrow_id = br.borrow_id 
                  JOIN users u ON f.user_id = u.user_id 
                  JOIN books b ON br.book_id = b.book_id 
                  $where_clause";
    $count_result = execute_query($count_sql, $params);
    $total_records = $count_result->fetch()['total'];
    
    // Get unpaid fines
    $records_sql = "SELECT f.*, br.borrow_date, br.due_date, br.return_date,
                           u.full_name, u.id_number, u.department, u.phone, u.email,
                           b.title, b.author, b.isbn, c.category_name,
                           DATEDIFF(br.return_date, br.due_date) as days_overdue
                    FROM fines f 
                    JOIN borrow_records br ON f.borrow_id = br.borrow_id 
                    JOIN users u ON f.user_id = u.user_id 
                    JOIN books b ON br.book_id = b.book_id 
                    JOIN categories c ON b.category_id = c.category_id 
                    $where_clause
                    ORDER BY f.created_at DESC
                    LIMIT $per_page OFFSET $offset";
    
    $records_result = execute_query($records_sql, $params);
    $records = $records_result->fetchAll();
}

$total_pages = ceil($total_records / $per_page);

// Get departments for filter
$departments_sql = "SELECT DISTINCT department FROM users WHERE status = 'active' ORDER BY department";
$departments_result = execute_query($departments_sql);
$departments = $departments_result->fetchAll();

// Get statistics
$stats_sql = "SELECT 
                (SELECT COUNT(*) FROM borrow_records WHERE return_date IS NULL) as pending_returns,
                (SELECT COUNT(*) FROM borrow_records WHERE return_date IS NULL AND due_date < CURDATE()) as overdue_returns,
                (SELECT COUNT(*) FROM fines WHERE payment_status = 'unpaid') as unpaid_fines,
                (SELECT COALESCE(SUM(fine_amount), 0) FROM fines WHERE payment_status = 'unpaid') as total_unpaid_amount";
$stats_result = execute_query($stats_sql);
$stats = $stats_result->fetch();

include '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-undo"></i> Returns Management</h1>
        <div class="header-actions">
            <span class="role-badge">Librarian View</span>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $stats['pending_returns']; ?></h3>
                <p>Pending Returns</p>
            </div>
        </div>
        <div class="stat-card overdue">
            <div class="stat-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $stats['overdue_returns']; ?></h3>
                <p>Overdue Returns</p>
            </div>
        </div>
        <div class="stat-card warning">
            <div class="stat-icon">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $stats['unpaid_fines']; ?></h3>
                <p>Unpaid Fines</p>
            </div>
        </div>
        <div class="stat-card info">
            <div class="stat-icon">
                <i class="fas fa-coins"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo number_format($stats['total_unpaid_amount'], 2); ?> ETB</h3>
                <p>Total Unpaid Amount</p>
            </div>
        </div>
    </div>

    <!-- View Toggle -->
    <div class="view-toggle">
        <a href="?status=pending_return&search=<?php echo urlencode($search); ?>&department=<?php echo urlencode($department_filter); ?>" 
           class="btn <?php echo $status_filter === 'pending_return' ? 'btn-primary' : 'btn-secondary'; ?>">
            <i class="fas fa-clock"></i> Pending Returns
        </a>
        <a href="?status=unpaid_fines&search=<?php echo urlencode($search); ?>&department=<?php echo urlencode($department_filter); ?>" 
           class="btn <?php echo $status_filter === 'unpaid_fines' ? 'btn-primary' : 'btn-secondary'; ?>">
            <i class="fas fa-money-bill-wave"></i> Unpaid Fines
        </a>
    </div>

    <!-- Search and Filter Section -->
    <div class="search-section">
        <form method="GET" class="search-form">
            <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
            <div class="search-row">
                <div class="search-input-group">
                    <input type="text" name="search" 
                           placeholder="<?php echo $status_filter === 'pending_return' ? 'Search by user name, ID, book title, author, or ISBN...' : 'Search by user name, ID, or book title...'; ?>" 
                           value="<?php echo htmlspecialchars($search); ?>" class="form-control">
                </div>
                <div class="filter-group">
                    <select name="department" class="form-control">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept['department']); ?>" 
                                    <?php echo $department_filter === $dept['department'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['department']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="search-buttons">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <a href="returns.php?status=<?php echo urlencode($status_filter); ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Records Table -->
    <div class="table-container">
        <?php if ($status_filter === 'pending_return'): ?>
            <!-- Pending Returns Table -->
            <table class="table">
                <thead>
                    <tr>
                        <th>Borrower</th>
                        <th>Book Details</th>
                        <th>Borrow Date</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Potential Fine</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($records)): ?>
                        <tr>
                            <td colspan="7" class="text-center">
                                <i class="fas fa-check-circle"></i>
                                No pending returns found. <?php echo !empty($search) || !empty($department_filter) ? 'Try adjusting your search criteria.' : 'All books have been returned.'; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($records as $record): ?>
                            <tr class="<?php echo $record['urgency_status']; ?>-row">
                                <td>
                                    <div class="borrower-info">
                                        <strong><?php echo htmlspecialchars($record['full_name']); ?></strong>
                                        <br><small><?php echo htmlspecialchars($record['id_number']); ?></small>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($record['department']); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div class="book-info">
                                        <strong><?php echo htmlspecialchars($record['title']); ?></strong>
                                        <br><small>by <?php echo htmlspecialchars($record['author']); ?></small>
                                        <br><small class="text-muted">ISBN: <?php echo htmlspecialchars($record['isbn']); ?></small>
                                        <br><span class="category-badge"><?php echo htmlspecialchars($record['category_name']); ?></span>
                                    </div>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($record['borrow_date'])); ?></td>
                                <td>
                                    <?php echo date('M j, Y', strtotime($record['due_date'])); ?>
                                    <?php if ($record['urgency_status'] === 'overdue'): ?>
                                        <br><small class="text-danger">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            <?php echo $record['days_overdue']; ?> days overdue
                                        </small>
                                    <?php elseif ($record['urgency_status'] === 'due_soon'): ?>
                                        <br><small class="text-warning">
                                            <i class="fas fa-clock"></i>
                                            Due in <?php echo -$record['days_overdue']; ?> days
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($record['urgency_status'] === 'overdue'): ?>
                                        <span class="status-badge status-overdue">Overdue</span>
                                    <?php elseif ($record['urgency_status'] === 'due_soon'): ?>
                                        <span class="status-badge status-due-soon">Due Soon</span>
                                    <?php else: ?>
                                        <span class="status-badge status-borrowed">On Time</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($record['potential_fine'] > 0): ?>
                                        <span class="fine-amount text-danger">
                                            <?php echo number_format($record['potential_fine'], 2); ?> ETB
                                        </span>
                                    <?php else: ?>
                                        <span class="text-success">No Fine</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-success" 
                                            onclick="showReturnModal(<?php echo htmlspecialchars(json_encode($record)); ?>)">
                                        <i class="fas fa-undo"></i> Process Return
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php else: ?>
            <!-- Unpaid Fines Table -->
            <table class="table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Book Details</th>
                        <th>Return Date</th>
                        <th>Days Overdue</th>
                        <th>Fine Amount</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($records)): ?>
                        <tr>
                            <td colspan="7" class="text-center">
                                <i class="fas fa-check-circle"></i>
                                No unpaid fines found. <?php echo !empty($search) || !empty($department_filter) ? 'Try adjusting your search criteria.' : 'All fines have been paid or waived.'; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($records as $record): ?>
                            <tr>
                                <td>
                                    <div class="borrower-info">
                                        <strong><?php echo htmlspecialchars($record['full_name']); ?></strong>
                                        <br><small><?php echo htmlspecialchars($record['id_number']); ?></small>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($record['department']); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div class="book-info">
                                        <strong><?php echo htmlspecialchars($record['title']); ?></strong>
                                        <br><small>by <?php echo htmlspecialchars($record['author']); ?></small>
                                        <br><small class="text-muted">ISBN: <?php echo htmlspecialchars($record['isbn']); ?></small>
                                        <br><span class="category-badge"><?php echo htmlspecialchars($record['category_name']); ?></span>
                                    </div>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($record['return_date'])); ?></td>
                                <td>
                                    <span class="text-danger">
                                        <strong><?php echo $record['days_overdue']; ?> days</strong>
                                    </span>
                                </td>
                                <td>
                                    <span class="fine-amount text-danger">
                                        <strong><?php echo number_format($record['fine_amount'], 2); ?> ETB</strong>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($record['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button type="button" class="btn btn-sm btn-success" 
                                                onclick="showPaymentModal(<?php echo $record['fine_id']; ?>, '<?php echo htmlspecialchars($record['full_name']); ?>', <?php echo $record['fine_amount']; ?>)">
                                            <i class="fas fa-money-bill"></i> Mark Paid
                                        </button>
                                        <button type="button" class="btn btn-sm btn-warning" 
                                                onclick="showWaiveModal(<?php echo $record['fine_id']; ?>, '<?php echo htmlspecialchars($record['full_name']); ?>', <?php echo $record['fine_amount']; ?>)">
                                            <i class="fas fa-times-circle"></i> Waive
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination-container">
            <div class="pagination-info">
                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_records); ?> 
                of <?php echo $total_records; ?> <?php echo $status_filter === 'pending_return' ? 'pending returns' : 'unpaid fines'; ?>
            </div>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>&department=<?php echo urlencode($department_filter); ?>" 
                       class="btn btn-sm btn-secondary">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>&department=<?php echo urlencode($department_filter); ?>" 
                       class="btn btn-sm <?php echo $i == $page ? 'btn-primary' : 'btn-secondary'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>&department=<?php echo urlencode($department_filter); ?>" 
                       class="btn btn-sm btn-secondary">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Process Return Modal -->
<div id="returnModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-undo"></i> Process Book Return</h2>
            <button type="button" class="close-btn" onclick="hideReturnModal()">&times;</button>
        </div>
        <form method="POST" id="returnForm">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="process_return">
            <input type="hidden" id="return_borrow_id" name="borrow_id">
            
            <div class="modal-body">
                <div class="return-info">
                    <div class="info-row">
                        <strong>Book:</strong> <span id="return_book_title"></span>
                    </div>
                    <div class="info-row">
                        <strong>Borrower:</strong> <span id="return_borrower_name"></span>
                    </div>
                    <div class="info-row">
                        <strong>Due Date:</strong> <span id="return_due_date"></span>
                    </div>
                    <div class="info-row">
                        <strong>Days Overdue:</strong> <span id="return_days_overdue"></span>
                    </div>
                    <div class="info-row">
                        <strong>Fine Amount:</strong> <span id="return_fine_amount"></span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="return_condition">Book Condition *</label>
                    <select id="return_condition" name="return_condition" class="form-control" required>
                        <option value="good">Good Condition</option>
                        <option value="fair">Fair Condition</option>
                        <option value="poor">Poor Condition</option>
                        <option value="damaged">Damaged</option>
                        <option value="lost">Lost</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="return_notes">Return Notes</label>
                    <textarea id="return_notes" name="notes" class="form-control" rows="3" 
                              placeholder="Any notes about the book condition or return process..."></textarea>
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>Note:</strong> If the book is returned late, a fine will be automatically calculated and recorded. 
                    The fine rate is <?php echo number_format($fine_per_day, 2); ?> ETB per day.
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideReturnModal()">Cancel</button>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-undo"></i> Process Return
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Mark Fine Paid Modal -->
<div id="paymentModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-money-bill"></i> Mark Fine as Paid</h2>
            <button type="button" class="close-btn" onclick="hidePaymentModal()">&times;</button>
        </div>
        <form method="POST" id="paymentForm">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="mark_fine_paid">
            <input type="hidden" id="payment_fine_id" name="fine_id">
            
            <div class="modal-body">
                <p>Mark fine as paid for:</p>
                <div class="payment-info">
                    <strong>User:</strong> <span id="payment_user_name"></span><br>
                    <strong>Fine Amount:</strong> <span id="payment_fine_amount"></span> ETB
                </div>
                
                <div class="form-group">
                    <label for="payment_method">Payment Method *</label>
                    <select id="payment_method" name="payment_method" class="form-control" required>
                        <option value="">Select payment method...</option>
                        <option value="cash">Cash</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="mobile_money">Mobile Money</option>
                        <option value="check">Check</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="payment_notes">Payment Notes</label>
                    <textarea id="payment_notes" name="payment_notes" class="form-control" rows="3" 
                              placeholder="Any additional notes about the payment..."></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hidePaymentModal()">Cancel</button>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-money-bill"></i> Mark as Paid
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Waive Fine Modal -->
<div id="waiveModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-times-circle"></i> Waive Fine</h2>
            <button type="button" class="close-btn" onclick="hideWaiveModal()">&times;</button>
        </div>
        <form method="POST" id="waiveForm">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="waive_fine">
            <input type="hidden" id="waive_fine_id" name="fine_id">
            
            <div class="modal-body">
                <p>Waive fine for:</p>
                <div class="waive-info">
                    <strong>User:</strong> <span id="waive_user_name"></span><br>
                    <strong>Fine Amount:</strong> <span id="waive_fine_amount"></span> ETB
                </div>
                
                <div class="form-group">
                    <label for="waive_reason">Reason for Waiving *</label>
                    <textarea id="waive_reason" name="waive_reason" class="form-control" rows="3" 
                              placeholder="Please provide a reason for waiving this fine..." required></textarea>
                </div>
                
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Warning:</strong> This action cannot be undone. The fine will be permanently waived.
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideWaiveModal()">Cancel</button>
                <button type="submit" class="btn btn-warning">
                    <i class="fas fa-times-circle"></i> Waive Fine
                </button>
            </div>
        </form>
    </div>
</div>

<style>
/* Returns Management Styles */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #e9ecef;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.role-badge {
    background: #17a2b8;
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 1rem;
    border-left: 4px solid #007bff;
}

.stat-card.overdue {
    border-left-color: #dc3545;
}

.stat-card.warning {
    border-left-color: #ffc107;
}

.stat-card.info {
    border-left-color: #17a2b8;
}

.stat-icon {
    font-size: 2rem;
    color: #6c757d;
}

.stat-card.overdue .stat-icon {
    color: #dc3545;
}

.stat-card.warning .stat-icon {
    color: #ffc107;
}

.stat-card.info .stat-icon {
    color: #17a2b8;
}

.stat-content h3 {
    margin: 0;
    font-size: 2rem;
    font-weight: bold;
    color: #212529;
}

.stat-content p {
    margin: 0;
    color: #6c757d;
    font-size: 0.9rem;
}

.view-toggle {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
    justify-content: center;
}

.search-section {
    background: white;
    padding: 1.5rem;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.search-form .search-row {
    display: grid;
    grid-template-columns: 1fr auto auto;
    gap: 1rem;
    align-items: end;
}

.borrower-info, .book-info {
    line-height: 1.4;
}

.category-badge {
    background: #e9ecef;
    color: #495057;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
}

.status-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 600;
}

.status-borrowed {
    background: #d4edda;
    color: #155724;
}

.status-overdue {
    background: #f8d7da;
    color: #721c24;
}

.status-due-soon {
    background: #fff3cd;
    color: #856404;
}

.overdue-row {
    background-color: #fff5f5;
}

.due_soon-row {
    background-color: #fffbf0;
}

.fine-amount {
    font-weight: bold;
    font-size: 1.1em;
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    border-radius: 10px;
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid #e9ecef;
}

.modal-header h2 {
    margin: 0;
    color: #1e3c72;
}

.close-btn {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #6c757d;
}

.close-btn:hover {
    color: #dc3545;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    padding: 1.5rem;
    border-top: 1px solid #e9ecef;
}

.return-info, .payment-info, .waive-info {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 5px;
    margin-bottom: 1rem;
}

.info-row {
    margin-bottom: 0.5rem;
    display: flex;
    justify-content: space-between;
}

.pagination-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 2rem;
    padding: 1rem 0;
}

.pagination {
    display: flex;
    gap: 0.5rem;
}

.pagination-info {
    color: #6c757d;
    font-size: 0.9rem;
}

@media (max-width: 768px) {
    .search-form .search-row {
        grid-template-columns: 1fr;
    }
    
    .page-header {
        flex-direction: column;
        gap: 1rem;
        align-items: stretch;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .view-toggle {
        flex-direction: column;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .pagination-container {
        flex-direction: column;
        gap: 1rem;
    }
    
    .info-row {
        flex-direction: column;
        gap: 0.25rem;
    }
}
</style>

<script>
// Returns management JavaScript functions
function showReturnModal(record) {
    document.getElementById('return_borrow_id').value = record.borrow_id;
    document.getElementById('return_book_title').textContent = record.title;
    document.getElementById('return_borrower_name').textContent = record.full_name + ' (' + record.id_number + ')';
    document.getElementById('return_due_date').textContent = new Date(record.due_date).toLocaleDateString();
    
    const daysOverdue = record.days_overdue > 0 ? record.days_overdue : 0;
    const fineAmount = record.potential_fine || 0;
    
    document.getElementById('return_days_overdue').textContent = daysOverdue > 0 ? daysOverdue + ' days' : 'On time';
    document.getElementById('return_fine_amount').textContent = fineAmount > 0 ? fineAmount.toFixed(2) + ' ETB' : 'No fine';
    
    // Set color based on status
    const daysElement = document.getElementById('return_days_overdue');
    const fineElement = document.getElementById('return_fine_amount');
    
    if (daysOverdue > 0) {
        daysElement.className = 'text-danger';
        fineElement.className = 'text-danger';
    } else {
        daysElement.className = 'text-success';
        fineElement.className = 'text-success';
    }
    
    document.getElementById('returnModal').style.display = 'flex';
}

function hideReturnModal() {
    document.getElementById('returnModal').style.display = 'none';
    document.getElementById('returnForm').reset();
}

function showPaymentModal(fineId, userName, fineAmount) {
    document.getElementById('payment_fine_id').value = fineId;
    document.getElementById('payment_user_name').textContent = userName;
    document.getElementById('payment_fine_amount').textContent = fineAmount.toFixed(2);
    
    document.getElementById('paymentModal').style.display = 'flex';
}

function hidePaymentModal() {
    document.getElementById('paymentModal').style.display = 'none';
    document.getElementById('paymentForm').reset();
}

function showWaiveModal(fineId, userName, fineAmount) {
    document.getElementById('waive_fine_id').value = fineId;
    document.getElementById('waive_user_name').textContent = userName;
    document.getElementById('waive_fine_amount').textContent = fineAmount.toFixed(2);
    
    document.getElementById('waiveModal').style.display = 'flex';
}

function hideWaiveModal() {
    document.getElementById('waiveModal').style.display = 'none';
    document.getElementById('waiveForm').reset();
}

// Close modals when clicking outside
window.onclick = function(event) {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
}

// Auto-refresh page every 3 minutes to keep data current
setInterval(function() {
    // Only refresh if no modals are open
    const openModals = document.querySelectorAll('.modal[style*="flex"]');
    if (openModals.length === 0) {
        location.reload();
    }
}, 180000); // 3 minutes
</script>

<?php include '../includes/footer.php'; ?>