<?php
/**
 * Fines Management - Ethiopian Police University Library Management System
 * View and manage fines for students and staff
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';

// Start secure session
start_secure_session();

// Check if user is logged in and has student or staff role
if (!is_logged_in() || (!has_role('student') && !has_role('staff'))) {
    header('Location: ../index.php');
    exit();
}

$page_title = 'My Fines';
$current_user = get_logged_in_user();
$user_id = $current_user['user_id'];

$success_message = '';
$error_message = '';

// Handle fine payment request (simulation - in real system this would integrate with payment gateway)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_payment') {
    $fine_id = (int)$_POST['fine_id'];
    
    try {
        // Verify the fine belongs to the current user
        $verify_sql = "SELECT f.*, b.title 
                       FROM fines f 
                       JOIN borrow_records br ON f.borrow_id = br.borrow_id 
                       JOIN books b ON br.book_id = b.book_id 
                       WHERE f.fine_id = ? AND f.user_id = ? AND f.payment_status = 'unpaid'";
        $fine_record = execute_query($verify_sql, [$fine_id, $user_id])->fetch();
        
        if ($fine_record) {
            // For now, we'll just show a message that the payment request has been submitted
            // In a full implementation, this would integrate with a payment system
            $success_message = "Payment request submitted for fine of {$fine_record['fine_amount']} ETB related to '{$fine_record['title']}'. Please visit the library to complete payment.";
        } else {
            $error_message = "Invalid fine record or fine already paid.";
        }
    } catch (Exception $e) {
        error_log("Fine payment request error: " . $e->getMessage());
        $error_message = "An error occurred while processing your payment request.";
    }
}

// Get filter parameters
$status_filter = sanitize_input($_GET['status'] ?? 'all');
$year_filter = sanitize_input($_GET['year'] ?? '');
$sort_by = sanitize_input($_GET['sort'] ?? 'recent');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

$fines_records = [];
$total_records = 0;
$fines_stats = [];

try {
    // Build search conditions
    $where_conditions = ['f.user_id = ?'];
    $params = [$user_id];
    
    // Status filter
    if ($status_filter !== 'all') {
        $where_conditions[] = 'f.payment_status = ?';
        $params[] = $status_filter;
    }
    
    // Year filter
    if (!empty($year_filter)) {
        $where_conditions[] = 'YEAR(f.created_at) = ?';
        $params[] = $year_filter;
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    // Sort options
    $sort_options = [
        'recent' => 'f.created_at DESC',
        'oldest' => 'f.created_at ASC',
        'amount_high' => 'f.fine_amount DESC',
        'amount_low' => 'f.fine_amount ASC',
        'book_title' => 'b.title ASC'
    ];
    $order_clause = 'ORDER BY ' . ($sort_options[$sort_by] ?? $sort_options['recent']);
    
    // Count total records
    $count_sql = "SELECT COUNT(*) as total 
                  FROM fines f 
                  JOIN borrow_records br ON f.borrow_id = br.borrow_id 
                  JOIN books b ON br.book_id = b.book_id 
                  $where_clause";
    $total_records = execute_query($count_sql, $params)->fetch()['total'];
    
    // Get fines records with pagination
    $fines_sql = "SELECT f.*, 
                         br.borrow_date, br.due_date, br.return_date,
                         b.title, b.author, b.isbn,
                         c.category_name,
                         DATEDIFF(COALESCE(br.return_date, CURDATE()), br.due_date) as days_overdue
                  FROM fines f
                  JOIN borrow_records br ON f.borrow_id = br.borrow_id
                  JOIN books b ON br.book_id = b.book_id
                  JOIN categories c ON b.category_id = c.category_id
                  $where_clause 
                  $order_clause 
                  LIMIT $per_page OFFSET $offset";
    
    $fines_records = execute_query($fines_sql, $params)->fetchAll();
    
    // Get fines statistics
    $stats_sql = "SELECT 
                    COUNT(*) as total_fines,
                    COUNT(CASE WHEN f.payment_status = 'unpaid' THEN 1 END) as unpaid_count,
                    COUNT(CASE WHEN f.payment_status = 'paid' THEN 1 END) as paid_count,
                    COUNT(CASE WHEN f.payment_status = 'waived' THEN 1 END) as waived_count,
                    COALESCE(SUM(f.fine_amount), 0) as total_amount,
                    COALESCE(SUM(CASE WHEN f.payment_status = 'unpaid' THEN f.fine_amount ELSE 0 END), 0) as unpaid_amount,
                    COALESCE(SUM(CASE WHEN f.payment_status = 'paid' THEN f.fine_amount ELSE 0 END), 0) as paid_amount,
                    COALESCE(SUM(CASE WHEN f.payment_status = 'waived' THEN f.fine_amount ELSE 0 END), 0) as waived_amount,
                    COALESCE(AVG(f.fine_amount), 0) as avg_fine_amount
                  FROM fines f
                  WHERE f.user_id = ?";
    
    $fines_stats = execute_query($stats_sql, [$user_id])->fetch();
    
} catch (Exception $e) {
    error_log("Error fetching fines: " . $e->getMessage());
    $error_message = "An error occurred while loading your fines.";
}

// Get available years for filter
try {
    $years_sql = "SELECT DISTINCT YEAR(created_at) as year 
                  FROM fines 
                  WHERE user_id = ? 
                  ORDER BY year DESC";
    $years = execute_query($years_sql, [$user_id])->fetchAll();
} catch (Exception $e) {
    $years = [];
}

// Calculate pagination
$total_pages = ceil($total_records / $per_page);

include '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-money-bill-wave"></i> My Fines</h1>
        <div class="header-actions">
            <span class="role-badge"><?php echo ucfirst($current_user['role']); ?> Portal</span>
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

    <!-- Fines Statistics -->
    <div class="stats-section">
        <div class="stats-grid">
            <div class="stat-card <?php echo $fines_stats['total_fines'] > 0 ? 'stat-info' : ''; ?>">
                <div class="stat-icon">
                    <i class="fas fa-receipt"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($fines_stats['total_fines']); ?></h3>
                    <p>Total Fines</p>
                </div>
            </div>
            
            <div class="stat-card <?php echo $fines_stats['unpaid_count'] > 0 ? 'stat-danger' : ''; ?>">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($fines_stats['unpaid_count']); ?></h3>
                    <p>Unpaid Fines</p>
                </div>
            </div>
            
            <div class="stat-card <?php echo $fines_stats['paid_count'] > 0 ? 'stat-success' : ''; ?>">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($fines_stats['paid_count']); ?></h3>
                    <p>Paid Fines</p>
                </div>
            </div>
            
            <div class="stat-card <?php echo $fines_stats['unpaid_amount'] > 0 ? 'stat-danger' : ''; ?>">
                <div class="stat-icon">
                    <i class="fas fa-money-bill"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($fines_stats['unpaid_amount'], 2); ?> ETB</h3>
                    <p>Amount Due</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-coins"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($fines_stats['total_amount'], 2); ?> ETB</h3>
                    <p>Total Amount</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calculator"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($fines_stats['avg_fine_amount'], 2); ?> ETB</h3>
                    <p>Average Fine</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Outstanding Fines Alert -->
    <?php if ($fines_stats['unpaid_amount'] > 0): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Outstanding Fines:</strong> You have <?php echo number_format($fines_stats['unpaid_amount'], 2); ?> ETB in unpaid fines. 
            Please settle these fines to continue borrowing books. Contact the library for payment options.
        </div>
    <?php endif; ?>

    <!-- Filters Section -->
    <div class="filters-section">
        <form method="GET" class="filters-form">
            <div class="filters-row">
                <div class="filter-group">
                    <label for="status">Status:</label>
                    <select name="status" id="status" class="filter-select">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="unpaid" <?php echo $status_filter === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                        <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="waived" <?php echo $status_filter === 'waived' ? 'selected' : ''; ?>>Waived</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="year">Year:</label>
                    <select name="year" id="year" class="filter-select">
                        <option value="">All Years</option>
                        <?php foreach ($years as $year_data): ?>
                            <option value="<?php echo $year_data['year']; ?>" 
                                    <?php echo $year_filter == $year_data['year'] ? 'selected' : ''; ?>>
                                <?php echo $year_data['year']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="sort">Sort By:</label>
                    <select name="sort" id="sort" class="filter-select">
                        <option value="recent" <?php echo $sort_by === 'recent' ? 'selected' : ''; ?>>Most Recent</option>
                        <option value="oldest" <?php echo $sort_by === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                        <option value="amount_high" <?php echo $sort_by === 'amount_high' ? 'selected' : ''; ?>>Highest Amount</option>
                        <option value="amount_low" <?php echo $sort_by === 'amount_low' ? 'selected' : ''; ?>>Lowest Amount</option>
                        <option value="book_title" <?php echo $sort_by === 'book_title' ? 'selected' : ''; ?>>Book Title</option>
                    </select>
                </div>
            </div>
            
            <div class="filters-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
                <a href="fines.php" class="btn btn-outline">
                    <i class="fas fa-times"></i> Clear Filters
                </a>
            </div>
        </form>
    </div>

    <!-- Fines List -->
    <div class="fines-section">
        <div class="section-header">
            <div class="section-info">
                <h2><i class="fas fa-list"></i> Fines History</h2>
                <?php if ($total_records > 0): ?>
                    <p>Showing <?php echo number_format($offset + 1); ?>-<?php echo number_format(min($offset + $per_page, $total_records)); ?> of <?php echo number_format($total_records); ?> fines</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($fines_records)): ?>
            <div class="fines-list">
                <?php foreach ($fines_records as $fine): ?>
                    <div class="fine-item <?php echo $fine['payment_status']; ?>-item">
                        <div class="fine-status">
                            <?php if ($fine['payment_status'] === 'unpaid'): ?>
                                <div class="status-badge unpaid">
                                    <i class="fas fa-exclamation-circle"></i>
                                    Unpaid
                                </div>
                            <?php elseif ($fine['payment_status'] === 'paid'): ?>
                                <div class="status-badge paid">
                                    <i class="fas fa-check-circle"></i>
                                    Paid
                                </div>
                            <?php else: ?>
                                <div class="status-badge waived">
                                    <i class="fas fa-hand-paper"></i>
                                    Waived
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="fine-content">
                            <div class="fine-header">
                                <div class="fine-amount">
                                    <h3><?php echo number_format($fine['fine_amount'], 2); ?> ETB</h3>
                                    <p>Fine Amount</p>
                                </div>
                                <div class="fine-date">
                                    <p><strong>Created:</strong> <?php echo date('M j, Y', strtotime($fine['created_at'])); ?></p>
                                    <?php if ($fine['payment_date']): ?>
                                        <p><strong>Paid:</strong> <?php echo date('M j, Y', strtotime($fine['payment_date'])); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="book-info">
                                <h4 class="book-title"><?php echo htmlspecialchars($fine['title']); ?></h4>
                                <p class="book-author">by <?php echo htmlspecialchars($fine['author']); ?></p>
                                <p class="book-isbn">ISBN: <?php echo htmlspecialchars($fine['isbn']); ?></p>
                                <span class="category-badge"><?php echo htmlspecialchars($fine['category_name']); ?></span>
                            </div>
                            
                            <div class="fine-details">
                                <div class="detail-row">
                                    <div class="detail-item">
                                        <label>Borrow Date:</label>
                                        <span><?php echo date('M j, Y', strtotime($fine['borrow_date'])); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <label>Due Date:</label>
                                        <span><?php echo date('M j, Y', strtotime($fine['due_date'])); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <label>Return Date:</label>
                                        <span>
                                            <?php if ($fine['return_date']): ?>
                                                <?php echo date('M j, Y', strtotime($fine['return_date'])); ?>
                                            <?php else: ?>
                                                <em>Not returned yet</em>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="detail-item">
                                        <label>Days Overdue:</label>
                                        <span class="text-danger"><?php echo abs($fine['days_overdue']); ?> day<?php echo abs($fine['days_overdue']) !== 1 ? 's' : ''; ?></span>
                                    </div>
                                </div>
                                
                                <?php if ($fine['payment_method']): ?>
                                    <div class="detail-row">
                                        <div class="detail-item">
                                            <label>Payment Method:</label>
                                            <span><?php echo htmlspecialchars($fine['payment_method']); ?></span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($fine['notes']): ?>
                                    <div class="detail-row">
                                        <div class="detail-item full-width">
                                            <label>Notes:</label>
                                            <span><?php echo htmlspecialchars($fine['notes']); ?></span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="fine-actions">
                            <?php if ($fine['payment_status'] === 'unpaid'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="request_payment">
                                    <input type="hidden" name="fine_id" value="<?php echo $fine['fine_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        <i class="fas fa-credit-card"></i> Request Payment
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <button class="btn btn-sm btn-outline" onclick="showFineDetails(<?php echo $fine['fine_id']; ?>)">
                                <i class="fas fa-info-circle"></i> Details
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination-section">
                    <div class="pagination">
                        <?php
                        $query_params = $_GET;
                        
                        // Previous page
                        if ($page > 1):
                            $query_params['page'] = $page - 1;
                        ?>
                            <a href="?<?php echo http_build_query($query_params); ?>" class="pagination-btn">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php endif; ?>
                        
                        <!-- Page numbers -->
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++):
                            $query_params['page'] = $i;
                        ?>
                            <a href="?<?php echo http_build_query($query_params); ?>" 
                               class="pagination-btn <?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <!-- Next page -->
                        <?php if ($page < $total_pages):
                            $query_params['page'] = $page + 1;
                        ?>
                            <a href="?<?php echo http_build_query($query_params); ?>" class="pagination-btn">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <!-- No Fines -->
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-smile"></i>
                </div>
                <h3>No Fines Found</h3>
                <?php if (!empty($status_filter) && $status_filter !== 'all' || !empty($year_filter)): ?>
                    <p>No fines match your current filters. Try adjusting your search criteria.</p>
                    <div class="empty-actions">
                        <a href="fines.php" class="btn btn-primary">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    </div>
                <?php else: ?>
                    <p>Great! You don't have any fines. Keep returning books on time to maintain this record.</p>
                    <div class="empty-actions">
                        <a href="my-books.php" class="btn btn-primary">
                            <i class="fas fa-book-reader"></i> View Current Books
                        </a>
                        <a href="search.php" class="btn btn-outline">
                            <i class="fas fa-search"></i> Search Books
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Payment Information -->
    <div class="info-section">
        <div class="info-card">
            <h3><i class="fas fa-info-circle"></i> Payment Information</h3>
            <div class="info-content">
                <div class="info-item">
                    <h4>How to Pay Fines</h4>
                    <ul>
                        <li>Visit the library circulation desk during operating hours</li>
                        <li>Bring your student/staff ID for verification</li>
                        <li>Payment can be made in cash or by bank transfer</li>
                        <li>Request a receipt for your records</li>
                    </ul>
                </div>
                
                <div class="info-item">
                    <h4>Fine Policies</h4>
                    <ul>
                        <li>Late return fine: 2.00 ETB per day per book</li>
                        <li>Fines must be paid before borrowing new books</li>
                        <li>Damaged books incur replacement costs</li>
                        <li>Lost books require full replacement value payment</li>
                    </ul>
                </div>
                
                <div class="info-item">
                    <h4>Need Help?</h4>
                    <ul>
                        <li>Contact the library for payment assistance</li>
                        <li>Discuss payment plans for large amounts</li>
                        <li>Report any discrepancies immediately</li>
                        <li>Keep all payment receipts for your records</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <a href="my-books.php" class="action-btn">
            <i class="fas fa-book-reader"></i>
            <span>Current Books</span>
        </a>
        <a href="history.php" class="action-btn">
            <i class="fas fa-history"></i>
            <span>Borrowing History</span>
        </a>
        <a href="search.php" class="action-btn">
            <i class="fas fa-search"></i>
            <span>Search Books</span>
        </a>
        <a href="../profile.php" class="action-btn">
            <i class="fas fa-user"></i>
            <span>My Profile</span>
        </a>
    </div>
</div>

<!-- Fine Details Modal -->
<div id="fineModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Fine Details</h3>
            <span class="modal-close" onclick="closeModal()">&times;</span>
        </div>
        <div class="modal-body" id="modalBody">
            <!-- Fine details will be loaded here -->
        </div>
    </div>
</div>

<style>
/* Fines Page Specific Styles */
.stats-section {
    margin-bottom: 2rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
}

.stat-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    border-left: 4px solid #007bff;
}

.stat-card.stat-danger {
    border-left-color: #dc3545;
}

.stat-card.stat-success {
    border-left-color: #28a745;
}

.stat-card.stat-info {
    border-left-color: #17a2b8;
}

.stat-icon {
    font-size: 2rem;
    color: #007bff;
    flex-shrink: 0;
}

.stat-danger .stat-icon {
    color: #dc3545;
}

.stat-success .stat-icon {
    color: #28a745;
}

.stat-info .stat-icon {
    color: #17a2b8;
}

.stat-info h3 {
    margin: 0 0 0.25rem 0;
    font-size: 1.5rem;
    color: #1e3c72;
}

.stat-info p {
    margin: 0;
    color: #6c757d;
    font-size: 0.9rem;
}

/* Filters Section */
.filters-section {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    padding: 2rem;
    margin-bottom: 2rem;
}

.filters-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-group label {
    font-weight: 600;
    color: #495057;
    font-size: 0.9rem;
}

.filter-select {
    padding: 0.75rem;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    background: white;
}

.filter-select:focus {
    outline: none;
    border-color: #007bff;
}

.filters-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
}

/* Fines Section */
.fines-section {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    overflow: hidden;
    margin-bottom: 2rem;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
}

.section-info h2 {
    margin: 0 0 0.5rem 0;
    color: #1e3c72;
}

.section-info p {
    margin: 0;
    color: #6c757d;
    font-size: 0.9rem;
}

.fines-list {
    padding: 0;
}

.fine-item {
    display: flex;
    align-items: flex-start;
    gap: 1.5rem;
    padding: 1.5rem;
    border-bottom: 1px solid #f8f9fa;
    transition: all 0.3s ease;
}

.fine-item:last-child {
    border-bottom: none;
}

.fine-item:hover {
    background: #f8f9fa;
}

.unpaid-item {
    background: #fff5f5;
    border-left: 4px solid #dc3545;
}

.paid-item {
    background: #f0fff4;
    border-left: 4px solid #28a745;
}

.waived-item {
    background: #f8f9fa;
    border-left: 4px solid #6c757d;
}

.fine-status {
    flex-shrink: 0;
}

.status-badge {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    white-space: nowrap;
}

.status-badge.unpaid {
    background: #f8d7da;
    color: #721c24;
}

.status-badge.paid {
    background: #d4edda;
    color: #155724;
}

.status-badge.waived {
    background: #e2e3e5;
    color: #383d41;
}

.fine-content {
    flex: 1;
    min-width: 0;
}

.fine-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.fine-amount h3 {
    margin: 0 0 0.25rem 0;
    color: #dc3545;
    font-size: 1.5rem;
}

.fine-amount p {
    margin: 0;
    color: #6c757d;
    font-size: 0.9rem;
}

.fine-date p {
    margin: 0 0 0.25rem 0;
    color: #6c757d;
    font-size: 0.9rem;
}

.book-info {
    margin-bottom: 1rem;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 6px;
}

.book-title {
    margin: 0 0 0.5rem 0;
    color: #1e3c72;
    font-size: 1.1rem;
}

.book-author {
    margin: 0 0 0.5rem 0;
    color: #6c757d;
    font-style: italic;
}

.book-isbn {
    margin: 0 0 0.5rem 0;
    color: #6c757d;
    font-size: 0.9rem;
}

.category-badge {
    background: #e9ecef;
    color: #495057;
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 600;
}

.fine-details {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.detail-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.detail-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.detail-item.full-width {
    grid-column: 1 / -1;
}

.detail-item label {
    font-size: 0.8rem;
    font-weight: 600;
    color: #6c757d;
    text-transform: uppercase;
}

.detail-item span {
    font-weight: 600;
}

.text-danger {
    color: #dc3545 !important;
}

.fine-actions {
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    align-items: flex-end;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-icon {
    font-size: 4rem;
    color: #28a745;
    margin-bottom: 1.5rem;
}

.empty-state h3 {
    margin: 0 0 1rem 0;
    color: #6c757d;
}

.empty-state p {
    margin: 0 0 2rem 0;
    color: #6c757d;
}

.empty-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
}

/* Info Section */
.info-section {
    margin-bottom: 2rem;
}

.info-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    padding: 2rem;
}

.info-card h3 {
    margin: 0 0 1.5rem 0;
    color: #1e3c72;
}

.info-content {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
}

.info-item h4 {
    margin: 0 0 1rem 0;
    color: #495057;
}

.info-item ul {
    margin: 0;
    padding-left: 1.5rem;
}

.info-item li {
    margin-bottom: 0.5rem;
    color: #6c757d;
}

/* Quick Actions */
.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.action-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    padding: 1.5rem;
    background: #007bff;
    color: white;
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.action-btn:hover {
    background: #0056b3;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.action-btn i {
    font-size: 2rem;
}

.action-btn span {
    font-weight: 600;
}

/* Pagination */
.pagination-section {
    display: flex;
    justify-content: center;
    margin-top: 2rem;
    padding: 1.5rem;
}

.pagination {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.pagination-btn {
    padding: 0.75rem 1rem;
    background: white;
    color: #007bff;
    text-decoration: none;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.pagination-btn:hover {
    background: #f8f9fa;
    border-color: #007bff;
}

.pagination-btn.active {
    background: #007bff;
    color: white;
    border-color: #007bff;
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: white;
    margin: 5% auto;
    padding: 0;
    border-radius: 10px;
    width: 90%;
    max-width: 600px;
    max-height: 80vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid #e9ecef;
    background: #f8f9fa;
    border-radius: 10px 10px 0 0;
}

.modal-header h3 {
    margin: 0;
    color: #1e3c72;
}

.modal-close {
    font-size: 2rem;
    cursor: pointer;
    color: #6c757d;
}

.modal-close:hover {
    color: #dc3545;
}

.modal-body {
    padding: 1.5rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .filters-row {
        grid-template-columns: 1fr;
    }
    
    .filters-actions {
        flex-direction: column;
    }
    
    .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .fine-item {
        flex-direction: column;
        gap: 1rem;
    }
    
    .fine-header {
        flex-direction: column;
        gap: 1rem;
    }
    
    .fine-actions {
        align-items: stretch;
        flex-direction: row;
    }
    
    .detail-row {
        grid-template-columns: 1fr;
    }
    
    .info-content {
        grid-template-columns: 1fr;
    }
    
    .empty-actions {
        flex-direction: column;
        align-items: center;
    }
    
    .quick-actions {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
function showFineDetails(fineId) {
    document.getElementById('modalTitle').textContent = 'Fine Details';
    document.getElementById('modalBody').innerHTML = `
        <div class="loading">
            <i class="fas fa-spinner fa-spin"></i> Loading fine details...
        </div>
    `;
    document.getElementById('fineModal').style.display = 'block';
    
    // Simulate loading (in real implementation, this would be an AJAX call)
    setTimeout(() => {
        document.getElementById('modalBody').innerHTML = `
            <div class="fine-details">
                <p><strong>Fine ID:</strong> ${fineId}</p>
                <p><strong>Status:</strong> This feature will show detailed fine information including calculation breakdown, payment history, and related borrowing details.</p>
                <p><strong>Note:</strong> Contact the librarian for detailed fine information or payment assistance.</p>
            </div>
        `;
    }, 1000);
}

function closeModal() {
    document.getElementById('fineModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('fineModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}

// Confirmation for payment requests
document.addEventListener('DOMContentLoaded', function() {
    const paymentForms = document.querySelectorAll('form[method="POST"]');
    paymentForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const fineAmount = this.closest('.fine-item').querySelector('.fine-amount h3').textContent;
            if (!confirm(`Submit payment request for ${fineAmount}? You will need to visit the library to complete payment.`)) {
                e.preventDefault();
            }
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>