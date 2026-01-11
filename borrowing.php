<?php
/**
 * Librarian Borrowing Management - Ethiopian Police University Library Management System
 * Handles book borrowing operations for librarians
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

$page_title = 'Borrowing Management';
$success_message = '';
$error_message = '';

// Get system settings
$settings_sql = "SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('borrowing_period_days', 'max_books_per_user')";
$settings_result = execute_query($settings_sql);
$settings = [];
while ($row = $settings_result->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$borrowing_period = (int)($settings['borrowing_period_days'] ?? 14);
$max_books_per_user = (int)($settings['max_books_per_user'] ?? 3);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // Verify CSRF token
    if (!verify_csrf_token($csrf_token)) {
        $error_message = 'Invalid request. Please try again.';
    } else {
        switch ($action) {
            case 'borrow_book':
                $user_id = (int)($_POST['user_id'] ?? 0);
                $book_id = (int)($_POST['book_id'] ?? 0);
                $notes = sanitize_input($_POST['notes'] ?? '');
                
                if ($user_id <= 0 || $book_id <= 0) {
                    $error_message = 'Please select both user and book.';
                } else {
                    try {
                        // Check if user exists and is active
                        $user_sql = "SELECT full_name, id_number FROM users WHERE user_id = ? AND status = 'active'";
                        $user_result = execute_query($user_sql, [$user_id]);
                        $user = $user_result->fetch();
                        
                        if (!$user) {
                            $error_message = 'User not found or inactive.';
                            break;
                        }
                        
                        // Check if book exists and is available
                        $book_sql = "SELECT title, available_copies FROM books WHERE book_id = ? AND status = 'available'";
                        $book_result = execute_query($book_sql, [$book_id]);
                        $book = $book_result->fetch();
                        
                        if (!$book) {
                            $error_message = 'Book not found or not available.';
                            break;
                        }
                        
                        if ($book['available_copies'] <= 0) {
                            $error_message = 'No copies of this book are currently available.';
                            break;
                        }
                        
                        // Check if user has reached borrowing limit
                        $current_borrows_sql = "SELECT COUNT(*) as current_count FROM borrow_records 
                                               WHERE user_id = ? AND return_date IS NULL";
                        $current_result = execute_query($current_borrows_sql, [$user_id]);
                        $current_count = $current_result->fetch()['current_count'];
                        
                        if ($current_count >= $max_books_per_user) {
                            $error_message = "User has reached the maximum borrowing limit of {$max_books_per_user} books.";
                            break;
                        }
                        
                        // Check if user already has this book
                        $duplicate_sql = "SELECT COUNT(*) as duplicate_count FROM borrow_records 
                                         WHERE user_id = ? AND book_id = ? AND return_date IS NULL";
                        $duplicate_result = execute_query($duplicate_sql, [$user_id, $book_id]);
                        $duplicate_count = $duplicate_result->fetch()['duplicate_count'];
                        
                        if ($duplicate_count > 0) {
                            $error_message = 'User already has this book borrowed.';
                            break;
                        }
                        
                        // Calculate due date
                        $borrow_date = date('Y-m-d');
                        $due_date = date('Y-m-d', strtotime("+{$borrowing_period} days"));
                        
                        // Create borrow record
                        $borrow_sql = "INSERT INTO borrow_records (user_id, book_id, borrow_date, due_date, notes) 
                                      VALUES (?, ?, ?, ?, ?)";
                        execute_query($borrow_sql, [$user_id, $book_id, $borrow_date, $due_date, $notes]);
                        
                        $success_message = "Book '{$book['title']}' successfully borrowed to {$user['full_name']} ({$user['id_number']}).";
                        log_security_event("Book borrowed: {$book['title']} to {$user['full_name']}", $_SESSION['user_id']);
                        
                    } catch (Exception $e) {
                        $error_message = 'Error processing borrowing request. Please try again.';
                        error_log("Borrowing error: " . $e->getMessage());
                    }
                }
                break;
                
            case 'extend_due_date':
                $borrow_id = (int)($_POST['borrow_id'] ?? 0);
                $new_due_date = sanitize_input($_POST['new_due_date'] ?? '');
                
                if ($borrow_id <= 0 || empty($new_due_date)) {
                    $error_message = 'Invalid borrowing record or due date.';
                } else {
                    try {
                        // Validate date format and ensure it's in the future
                        $date_obj = DateTime::createFromFormat('Y-m-d', $new_due_date);
                        if (!$date_obj || $date_obj->format('Y-m-d') !== $new_due_date) {
                            $error_message = 'Invalid date format.';
                            break;
                        }
                        
                        if ($date_obj <= new DateTime()) {
                            $error_message = 'Due date must be in the future.';
                            break;
                        }
                        
                        // Get borrow record info
                        $borrow_info_sql = "SELECT br.*, u.full_name, u.id_number, b.title 
                                           FROM borrow_records br 
                                           JOIN users u ON br.user_id = u.user_id 
                                           JOIN books b ON br.book_id = b.book_id 
                                           WHERE br.borrow_id = ? AND br.return_date IS NULL";
                        $borrow_info_result = execute_query($borrow_info_sql, [$borrow_id]);
                        $borrow_info = $borrow_info_result->fetch();
                        
                        if (!$borrow_info) {
                            $error_message = 'Borrowing record not found or already returned.';
                            break;
                        }
                        
                        // Update due date
                        $update_sql = "UPDATE borrow_records SET due_date = ? WHERE borrow_id = ?";
                        execute_query($update_sql, [$new_due_date, $borrow_id]);
                        
                        $success_message = "Due date extended for '{$borrow_info['title']}' borrowed by {$borrow_info['full_name']}.";
                        log_security_event("Due date extended for borrow ID: {$borrow_id}", $_SESSION['user_id']);
                        
                    } catch (Exception $e) {
                        $error_message = 'Error extending due date. Please try again.';
                        error_log("Due date extension error: " . $e->getMessage());
                    }
                }
                break;
        }
    }
}

// Get filter parameters
$search = sanitize_input($_GET['search'] ?? '');
$status_filter = sanitize_input($_GET['status'] ?? '');
$department_filter = sanitize_input($_GET['department'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Build search query for active borrowings
$where_conditions = ['br.return_date IS NULL'];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(u.full_name LIKE ? OR u.id_number LIKE ? OR b.title LIKE ? OR b.author LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($status_filter)) {
    $where_conditions[] = "br.status = ?";
    $params[] = $status_filter;
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
$total_pages = ceil($total_records / $per_page);

// Get active borrowings with pagination
$borrowings_sql = "SELECT br.*, u.full_name, u.id_number, u.department, u.phone, u.email,
                          b.title, b.author, b.isbn, c.category_name,
                          DATEDIFF(CURDATE(), br.due_date) as days_overdue,
                          CASE 
                              WHEN br.due_date < CURDATE() THEN 'overdue'
                              WHEN DATEDIFF(br.due_date, CURDATE()) <= 3 THEN 'due_soon'
                              ELSE 'normal'
                          END as urgency_status
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

$borrowings_result = execute_query($borrowings_sql, $params);
$borrowings = $borrowings_result->fetchAll();

// Get departments for filter
$departments_sql = "SELECT DISTINCT department FROM users WHERE status = 'active' ORDER BY department";
$departments_result = execute_query($departments_sql);
$departments = $departments_result->fetchAll();

// Get available books for borrowing
$available_books_sql = "SELECT b.book_id, b.title, b.author, b.isbn, b.available_copies, c.category_name 
                        FROM books b 
                        JOIN categories c ON b.category_id = c.category_id 
                        WHERE b.status = 'available' AND b.available_copies > 0 
                        ORDER BY b.title";
$available_books_result = execute_query($available_books_sql);
$available_books = $available_books_result->fetchAll();

// Get active users for borrowing
$active_users_sql = "SELECT user_id, full_name, id_number, department, 
                            (SELECT COUNT(*) FROM borrow_records WHERE user_id = u.user_id AND return_date IS NULL) as current_borrows
                     FROM users u 
                     WHERE status = 'active' 
                     ORDER BY full_name";
$active_users_result = execute_query($active_users_sql);
$active_users = $active_users_result->fetchAll();

include '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-handshake"></i> Borrowing Management</h1>
        <div class="header-actions">
            <button type="button" class="btn btn-primary" onclick="showBorrowModal()">
                <i class="fas fa-plus"></i> New Borrowing
            </button>
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
                <i class="fas fa-book-reader"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $total_records; ?></h3>
                <p>Active Borrowings</p>
            </div>
        </div>
        <div class="stat-card overdue">
            <div class="stat-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="stat-content">
                <?php
                $overdue_count = 0;
                foreach ($borrowings as $borrow) {
                    if ($borrow['urgency_status'] === 'overdue') $overdue_count++;
                }
                ?>
                <h3><?php echo $overdue_count; ?></h3>
                <p>Overdue Books</p>
            </div>
        </div>
        <div class="stat-card warning">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
                <?php
                $due_soon_count = 0;
                foreach ($borrowings as $borrow) {
                    if ($borrow['urgency_status'] === 'due_soon') $due_soon_count++;
                }
                ?>
                <h3><?php echo $due_soon_count; ?></h3>
                <p>Due Soon</p>
            </div>
        </div>
        <div class="stat-card info">
            <div class="stat-icon">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $borrowing_period; ?> days</h3>
                <p>Borrowing Period</p>
            </div>
        </div>
    </div>

    <!-- Search and Filter Section -->
    <div class="search-section">
        <form method="GET" class="search-form">
            <div class="search-row">
                <div class="search-input-group">
                    <input type="text" name="search" placeholder="Search by user name, ID, book title, or author..." 
                           value="<?php echo htmlspecialchars($search); ?>" class="form-control">
                </div>
                <div class="filter-group">
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="borrowed" <?php echo $status_filter === 'borrowed' ? 'selected' : ''; ?>>Borrowed</option>
                        <option value="overdue" <?php echo $status_filter === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                    </select>
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
                    <a href="borrowing.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Borrowings Table -->
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Borrower</th>
                    <th>Book Details</th>
                    <th>Borrow Date</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Contact</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($borrowings)): ?>
                    <tr>
                        <td colspan="7" class="text-center">
                            <i class="fas fa-book-open"></i>
                            No active borrowings found. <?php echo !empty($search) || !empty($status_filter) || !empty($department_filter) ? 'Try adjusting your search criteria.' : 'No books are currently borrowed.'; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($borrowings as $borrow): ?>
                        <tr class="<?php echo $borrow['urgency_status']; ?>-row">
                            <td>
                                <div class="borrower-info">
                                    <strong><?php echo htmlspecialchars($borrow['full_name']); ?></strong>
                                    <br><small><?php echo htmlspecialchars($borrow['id_number']); ?></small>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($borrow['department']); ?></small>
                                </div>
                            </td>
                            <td>
                                <div class="book-info">
                                    <strong><?php echo htmlspecialchars($borrow['title']); ?></strong>
                                    <br><small>by <?php echo htmlspecialchars($borrow['author']); ?></small>
                                    <br><small class="text-muted">ISBN: <?php echo htmlspecialchars($borrow['isbn']); ?></small>
                                    <br><span class="category-badge"><?php echo htmlspecialchars($borrow['category_name']); ?></span>
                                </div>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($borrow['borrow_date'])); ?></td>
                            <td>
                                <?php echo date('M j, Y', strtotime($borrow['due_date'])); ?>
                                <?php if ($borrow['urgency_status'] === 'overdue'): ?>
                                    <br><small class="text-danger">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <?php echo abs($borrow['days_overdue']); ?> days overdue
                                    </small>
                                <?php elseif ($borrow['urgency_status'] === 'due_soon'): ?>
                                    <br><small class="text-warning">
                                        <i class="fas fa-clock"></i>
                                        Due in <?php echo -$borrow['days_overdue']; ?> days
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($borrow['urgency_status'] === 'overdue'): ?>
                                    <span class="status-badge status-overdue">Overdue</span>
                                <?php elseif ($borrow['urgency_status'] === 'due_soon'): ?>
                                    <span class="status-badge status-due-soon">Due Soon</span>
                                <?php else: ?>
                                    <span class="status-badge status-borrowed">Borrowed</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small>
                                    <?php if ($borrow['phone']): ?>
                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($borrow['phone']); ?><br>
                                    <?php endif; ?>
                                    <?php if ($borrow['email']): ?>
                                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($borrow['email']); ?>
                                    <?php endif; ?>
                                </small>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button type="button" class="btn btn-sm btn-warning" 
                                            onclick="showExtendModal(<?php echo $borrow['borrow_id']; ?>, '<?php echo htmlspecialchars($borrow['title']); ?>', '<?php echo htmlspecialchars($borrow['full_name']); ?>', '<?php echo $borrow['due_date']; ?>')">
                                        <i class="fas fa-calendar-plus"></i> Extend
                                    </button>
                                    <a href="../student/index.php?user_id=<?php echo $borrow['user_id']; ?>" 
                                       class="btn btn-sm btn-info" target="_blank">
                                        <i class="fas fa-user"></i> Profile
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination-container">
            <div class="pagination-info">
                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_records); ?> 
                of <?php echo $total_records; ?> borrowings
            </div>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&department=<?php echo urlencode($department_filter); ?>" 
                       class="btn btn-sm btn-secondary">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&department=<?php echo urlencode($department_filter); ?>" 
                       class="btn btn-sm <?php echo $i == $page ? 'btn-primary' : 'btn-secondary'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&department=<?php echo urlencode($department_filter); ?>" 
                       class="btn btn-sm btn-secondary">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- New Borrowing Modal -->
<div id="borrowModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-plus"></i> New Book Borrowing</h2>
            <button type="button" class="close-btn" onclick="hideBorrowModal()">&times;</button>
        </div>
        <form method="POST" id="borrowForm">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="borrow_book">
            
            <div class="modal-body">
                <div class="form-group">
                    <label for="user_id">Select User *</label>
                    <select id="user_id" name="user_id" class="form-control" required>
                        <option value="">Choose a user...</option>
                        <?php foreach ($active_users as $user): ?>
                            <option value="<?php echo $user['user_id']; ?>" 
                                    data-current-borrows="<?php echo $user['current_borrows']; ?>"
                                    <?php echo $user['current_borrows'] >= $max_books_per_user ? 'disabled' : ''; ?>>
                                <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo htmlspecialchars($user['id_number']); ?>) - 
                                <?php echo htmlspecialchars($user['department']); ?>
                                <?php if ($user['current_borrows'] > 0): ?>
                                    - Currently has <?php echo $user['current_borrows']; ?> book(s)
                                <?php endif; ?>
                                <?php if ($user['current_borrows'] >= $max_books_per_user): ?>
                                    - LIMIT REACHED
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="book_id">Select Book *</label>
                    <select id="book_id" name="book_id" class="form-control" required>
                        <option value="">Choose a book...</option>
                        <?php foreach ($available_books as $book): ?>
                            <option value="<?php echo $book['book_id']; ?>">
                                <?php echo htmlspecialchars($book['title']); ?> by <?php echo htmlspecialchars($book['author']); ?>
                                (<?php echo $book['available_copies']; ?> available) - <?php echo htmlspecialchars($book['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="notes">Notes (Optional)</label>
                    <textarea id="notes" name="notes" class="form-control" rows="3" 
                              placeholder="Any special notes about this borrowing..."></textarea>
                </div>
                
                <div class="borrowing-info">
                    <div class="info-item">
                        <strong>Borrowing Period:</strong> <?php echo $borrowing_period; ?> days
                    </div>
                    <div class="info-item">
                        <strong>Due Date:</strong> <?php echo date('M j, Y', strtotime("+{$borrowing_period} days")); ?>
                    </div>
                    <div class="info-item">
                        <strong>Max Books per User:</strong> <?php echo $max_books_per_user; ?> books
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideBorrowModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-handshake"></i> Process Borrowing
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Extend Due Date Modal -->
<div id="extendModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-calendar-plus"></i> Extend Due Date</h2>
            <button type="button" class="close-btn" onclick="hideExtendModal()">&times;</button>
        </div>
        <form method="POST" id="extendForm">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="extend_due_date">
            <input type="hidden" id="extend_borrow_id" name="borrow_id">
            
            <div class="modal-body">
                <p>Extend due date for:</p>
                <div class="extend-info">
                    <strong>Book:</strong> <span id="extend_book_title"></span><br>
                    <strong>Borrower:</strong> <span id="extend_borrower_name"></span><br>
                    <strong>Current Due Date:</strong> <span id="extend_current_due"></span>
                </div>
                
                <div class="form-group">
                    <label for="new_due_date">New Due Date *</label>
                    <input type="date" id="new_due_date" name="new_due_date" class="form-control" 
                           min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>Note:</strong> The new due date must be in the future. Consider the borrowing policies when extending due dates.
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideExtendModal()">Cancel</button>
                <button type="submit" class="btn btn-warning">
                    <i class="fas fa-calendar-plus"></i> Extend Due Date
                </button>
            </div>
        </form>
    </div>
</div>

<style>
/* Borrowing Management Styles */
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

.search-section {
    background: white;
    padding: 1.5rem;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.search-form .search-row {
    display: grid;
    grid-template-columns: 1fr auto auto auto;
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

.borrowing-info {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 5px;
    margin-top: 1rem;
}

.info-item {
    margin-bottom: 0.5rem;
}

.extend-info {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 5px;
    margin-bottom: 1rem;
    line-height: 1.6;
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
    
    .header-actions {
        justify-content: space-between;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .pagination-container {
        flex-direction: column;
        gap: 1rem;
    }
}
</style>

<script>
// Borrowing management JavaScript functions
function showBorrowModal() {
    document.getElementById('borrowModal').style.display = 'flex';
    document.getElementById('user_id').focus();
}

function hideBorrowModal() {
    document.getElementById('borrowModal').style.display = 'none';
    document.getElementById('borrowForm').reset();
}

function showExtendModal(borrowId, bookTitle, borrowerName, currentDueDate) {
    document.getElementById('extend_borrow_id').value = borrowId;
    document.getElementById('extend_book_title').textContent = bookTitle;
    document.getElementById('extend_borrower_name').textContent = borrowerName;
    document.getElementById('extend_current_due').textContent = new Date(currentDueDate).toLocaleDateString();
    
    // Set minimum date to tomorrow
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    document.getElementById('new_due_date').min = tomorrow.toISOString().split('T')[0];
    
    document.getElementById('extendModal').style.display = 'flex';
}

function hideExtendModal() {
    document.getElementById('extendModal').style.display = 'none';
    document.getElementById('extendForm').reset();
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

// Form validation
document.addEventListener('DOMContentLoaded', function() {
    // User selection validation
    const userSelect = document.getElementById('user_id');
    if (userSelect) {
        userSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const currentBorrows = parseInt(selectedOption.dataset.currentBorrows || 0);
            const maxBooks = <?php echo $max_books_per_user; ?>;
            
            if (currentBorrows >= maxBooks) {
                alert('This user has reached the maximum borrowing limit of ' + maxBooks + ' books.');
                this.value = '';
            }
        });
    }
    
    // Auto-refresh page every 2 minutes to keep data current
    setInterval(function() {
        // Only refresh if no modals are open
        const openModals = document.querySelectorAll('.modal[style*="flex"]');
        if (openModals.length === 0) {
            location.reload();
        }
    }, 120000); // 2 minutes
});
</script>

<?php include '../includes/footer.php'; ?>