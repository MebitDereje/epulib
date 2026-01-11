<?php
/**
 * Borrowing History - Ethiopian Police University Library Management System
 * View complete borrowing history for students and staff
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

$page_title = 'Borrowing History';
$current_user = get_logged_in_user();
$user_id = $current_user['user_id'];

// Get filter parameters
$status_filter = sanitize_input($_GET['status'] ?? 'all');
$year_filter = sanitize_input($_GET['year'] ?? '');
$category_filter = (int)($_GET['category'] ?? 0);
$search_query = sanitize_input($_GET['q'] ?? '');
$sort_by = sanitize_input($_GET['sort'] ?? 'recent');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

$history_records = [];
$total_records = 0;
$history_stats = [];

try {
    // Build search conditions
    $where_conditions = ['br.user_id = ?'];
    $params = [$user_id];
    
    // Status filter
    if ($status_filter !== 'all') {
        $where_conditions[] = 'br.status = ?';
        $params[] = $status_filter;
    }
    
    // Year filter
    if (!empty($year_filter)) {
        $where_conditions[] = 'YEAR(br.borrow_date) = ?';
        $params[] = $year_filter;
    }
    
    // Category filter
    if ($category_filter > 0) {
        $where_conditions[] = 'b.category_id = ?';
        $params[] = $category_filter;
    }
    
    // Search query
    if (!empty($search_query)) {
        $where_conditions[] = '(b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ?)';
        $params = array_merge($params, [
            '%' . $search_query . '%',
            '%' . $search_query . '%',
            '%' . $search_query . '%'
        ]);
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    // Sort options
    $sort_options = [
        'recent' => 'br.borrow_date DESC',
        'oldest' => 'br.borrow_date ASC',
        'title' => 'b.title ASC',
        'author' => 'b.author ASC',
        'due_date' => 'br.due_date DESC',
        'return_date' => 'br.return_date DESC'
    ];
    $order_clause = 'ORDER BY ' . ($sort_options[$sort_by] ?? $sort_options['recent']);
    
    // Count total records
    $count_sql = "SELECT COUNT(*) as total 
                  FROM borrow_records br 
                  JOIN books b ON br.book_id = b.book_id 
                  JOIN categories c ON b.category_id = c.category_id 
                  $where_clause";
    $total_records = execute_query($count_sql, $params)->fetch()['total'];
    
    // Get history records with pagination
    $history_sql = "SELECT br.*, 
                           b.title, b.author, b.isbn, b.publisher, b.publication_year,
                           c.category_name,
                           DATEDIFF(COALESCE(br.return_date, CURDATE()), br.due_date) as days_difference,
                           CASE 
                               WHEN br.return_date IS NULL AND br.due_date < CURDATE() THEN 'overdue'
                               WHEN br.return_date IS NULL THEN 'current'
                               WHEN br.return_date > br.due_date THEN 'returned_late'
                               ELSE 'returned_on_time'
                           END as return_status,
                           (SELECT COUNT(*) FROM fines f WHERE f.borrow_id = br.borrow_id) as has_fines,
                           (SELECT COALESCE(SUM(fine_amount), 0) FROM fines f WHERE f.borrow_id = br.borrow_id) as total_fines
                    FROM borrow_records br
                    JOIN books b ON br.book_id = b.book_id
                    JOIN categories c ON b.category_id = c.category_id
                    $where_clause 
                    $order_clause 
                    LIMIT $per_page OFFSET $offset";
    
    $history_records = execute_query($history_sql, $params)->fetchAll();
    
    // Get history statistics
    $stats_sql = "SELECT 
                    COUNT(*) as total_borrowed,
                    COUNT(CASE WHEN br.status = 'returned' THEN 1 END) as total_returned,
                    COUNT(CASE WHEN br.status = 'borrowed' THEN 1 END) as currently_borrowed,
                    COUNT(CASE WHEN br.status = 'overdue' THEN 1 END) as overdue_count,
                    COUNT(CASE WHEN br.return_date > br.due_date THEN 1 END) as late_returns,
                    COALESCE(AVG(DATEDIFF(br.return_date, br.borrow_date)), 0) as avg_borrow_days,
                    (SELECT COALESCE(SUM(fine_amount), 0) FROM fines f 
                     JOIN borrow_records br2 ON f.borrow_id = br2.borrow_id 
                     WHERE br2.user_id = ?) as total_fines_ever,
                    (SELECT COALESCE(SUM(fine_amount), 0) FROM fines f 
                     JOIN borrow_records br3 ON f.borrow_id = br3.borrow_id 
                     WHERE br3.user_id = ? AND f.payment_status = 'unpaid') as unpaid_fines
                  FROM borrow_records br
                  WHERE br.user_id = ?";
    
    $history_stats = execute_query($stats_sql, [$user_id, $user_id, $user_id])->fetch();
    
} catch (Exception $e) {
    error_log("Error fetching borrowing history: " . $e->getMessage());
    $error_message = "An error occurred while loading your borrowing history.";
}

// Get categories for filter dropdown
try {
    $categories_sql = "SELECT * FROM categories ORDER BY category_name";
    $categories = execute_query($categories_sql)->fetchAll();
} catch (Exception $e) {
    $categories = [];
}

// Get available years for filter
try {
    $years_sql = "SELECT DISTINCT YEAR(borrow_date) as year 
                  FROM borrow_records 
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
        <h1><i class="fas fa-history"></i> Borrowing History</h1>
        <div class="header-actions">
            <span class="role-badge"><?php echo ucfirst($current_user['role']); ?> Portal</span>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <!-- History Statistics -->
    <div class="stats-section">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-books"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($history_stats['total_borrowed']); ?></h3>
                    <p>Total Books Borrowed</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($history_stats['total_returned']); ?></h3>
                    <p>Books Returned</p>
                </div>
            </div>
            
            <div class="stat-card <?php echo $history_stats['currently_borrowed'] > 0 ? 'stat-info-highlight' : ''; ?>">
                <div class="stat-icon">
                    <i class="fas fa-book-reader"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($history_stats['currently_borrowed']); ?></h3>
                    <p>Currently Borrowed</p>
                </div>
            </div>
            
            <div class="stat-card <?php echo $history_stats['late_returns'] > 0 ? 'stat-warning' : ''; ?>">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($history_stats['late_returns']); ?></h3>
                    <p>Late Returns</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($history_stats['avg_borrow_days'], 1); ?></h3>
                    <p>Avg. Borrow Days</p>
                </div>
            </div>
            
            <div class="stat-card <?php echo $history_stats['unpaid_fines'] > 0 ? 'stat-danger' : ''; ?>">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($history_stats['unpaid_fines'], 2); ?> ETB</h3>
                    <p>Unpaid Fines</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="filters-section">
        <form method="GET" class="filters-form">
            <div class="filters-row">
                <div class="filter-group">
                    <label for="q">Search Books:</label>
                    <input type="text" 
                           name="q" 
                           id="q"
                           value="<?php echo htmlspecialchars($search_query); ?>" 
                           placeholder="Search by title, author, or ISBN..."
                           class="filter-input">
                </div>
                
                <div class="filter-group">
                    <label for="status">Status:</label>
                    <select name="status" id="status" class="filter-select">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="returned" <?php echo $status_filter === 'returned' ? 'selected' : ''; ?>>Returned</option>
                        <option value="borrowed" <?php echo $status_filter === 'borrowed' ? 'selected' : ''; ?>>Currently Borrowed</option>
                        <option value="overdue" <?php echo $status_filter === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
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
                    <label for="category">Category:</label>
                    <select name="category" id="category" class="filter-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['category_id']; ?>" 
                                    <?php echo $category_filter == $category['category_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="sort">Sort By:</label>
                    <select name="sort" id="sort" class="filter-select">
                        <option value="recent" <?php echo $sort_by === 'recent' ? 'selected' : ''; ?>>Most Recent</option>
                        <option value="oldest" <?php echo $sort_by === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                        <option value="title" <?php echo $sort_by === 'title' ? 'selected' : ''; ?>>Title A-Z</option>
                        <option value="author" <?php echo $sort_by === 'author' ? 'selected' : ''; ?>>Author A-Z</option>
                        <option value="due_date" <?php echo $sort_by === 'due_date' ? 'selected' : ''; ?>>Due Date</option>
                        <option value="return_date" <?php echo $sort_by === 'return_date' ? 'selected' : ''; ?>>Return Date</option>
                    </select>
                </div>
            </div>
            
            <div class="filters-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
                <a href="history.php" class="btn btn-outline">
                    <i class="fas fa-times"></i> Clear Filters
                </a>
            </div>
        </form>
    </div>

    <!-- History Results -->
    <div class="history-section">
        <div class="section-header">
            <div class="section-info">
                <h2><i class="fas fa-list"></i> Borrowing History</h2>
                <?php if ($total_records > 0): ?>
                    <p>Showing <?php echo number_format($offset + 1); ?>-<?php echo number_format(min($offset + $per_page, $total_records)); ?> of <?php echo number_format($total_records); ?> records</p>
                <?php endif; ?>
            </div>
            
            <?php if ($total_records > 0): ?>
                <div class="section-actions">
                    <button class="btn btn-outline btn-sm" onclick="window.print()">
                        <i class="fas fa-print"></i> Print History
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($history_records)): ?>
            <div class="history-list">
                <?php foreach ($history_records as $record): ?>
                    <div class="history-item <?php echo $record['return_status']; ?>-item">
                        <div class="history-status">
                            <?php if ($record['return_status'] === 'current'): ?>
                                <div class="status-badge current">
                                    <i class="fas fa-book-reader"></i>
                                    Currently Borrowed
                                </div>
                            <?php elseif ($record['return_status'] === 'overdue'): ?>
                                <div class="status-badge overdue">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    Overdue
                                </div>
                            <?php elseif ($record['return_status'] === 'returned_late'): ?>
                                <div class="status-badge late">
                                    <i class="fas fa-clock"></i>
                                    Returned Late
                                </div>
                            <?php else: ?>
                                <div class="status-badge returned">
                                    <i class="fas fa-check-circle"></i>
                                    Returned On Time
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="history-content">
                            <div class="book-info">
                                <h3 class="book-title"><?php echo htmlspecialchars($record['title']); ?></h3>
                                <p class="book-author">by <?php echo htmlspecialchars($record['author']); ?></p>
                                <div class="book-meta">
                                    <span class="book-publisher"><?php echo htmlspecialchars($record['publisher']); ?></span>
                                    <?php if ($record['publication_year']): ?>
                                        <span class="book-year">(<?php echo $record['publication_year']; ?>)</span>
                                    <?php endif; ?>
                                    <span class="book-isbn">ISBN: <?php echo htmlspecialchars($record['isbn']); ?></span>
                                </div>
                                <div class="book-category">
                                    <span class="category-badge"><?php echo htmlspecialchars($record['category_name']); ?></span>
                                </div>
                            </div>
                            
                            <div class="borrow-details">
                                <div class="detail-row">
                                    <div class="detail-item">
                                        <label>Borrowed:</label>
                                        <span><?php echo date('M j, Y', strtotime($record['borrow_date'])); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <label>Due Date:</label>
                                        <span><?php echo date('M j, Y', strtotime($record['due_date'])); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <label>Return Date:</label>
                                        <span>
                                            <?php if ($record['return_date']): ?>
                                                <?php echo date('M j, Y', strtotime($record['return_date'])); ?>
                                            <?php else: ?>
                                                <em>Not returned yet</em>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="detail-item">
                                        <label>Duration:</label>
                                        <span>
                                            <?php if ($record['return_date']): ?>
                                                <?php echo abs(strtotime($record['return_date']) - strtotime($record['borrow_date'])) / (60*60*24); ?> days
                                            <?php else: ?>
                                                <?php echo abs(time() - strtotime($record['borrow_date'])) / (60*60*24); ?> days (ongoing)
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    
                                    <?php if ($record['return_status'] === 'returned_late' || $record['return_status'] === 'overdue'): ?>
                                        <div class="detail-item">
                                            <label>Days Late:</label>
                                            <span class="text-danger">
                                                <?php echo abs($record['days_difference']); ?> day<?php echo abs($record['days_difference']) !== 1 ? 's' : ''; ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($record['has_fines'] > 0): ?>
                                        <div class="detail-item">
                                            <label>Fines:</label>
                                            <span class="text-danger">
                                                <?php echo number_format($record['total_fines'], 2); ?> ETB
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($record['notes']): ?>
                                    <div class="detail-row">
                                        <div class="detail-item full-width">
                                            <label>Notes:</label>
                                            <span><?php echo htmlspecialchars($record['notes']); ?></span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="history-actions">
                            <?php if ($record['return_status'] === 'current'): ?>
                                <a href="my-books.php" class="btn btn-sm btn-primary">
                                    <i class="fas fa-book-reader"></i> View Current
                                </a>
                            <?php endif; ?>
                            
                            <button class="btn btn-sm btn-outline" onclick="showBookDetails(<?php echo $record['book_id']; ?>)">
                                <i class="fas fa-info-circle"></i> Book Details
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
            <!-- No History -->
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-history"></i>
                </div>
                <h3>No Borrowing History Found</h3>
                <?php if (!empty($search_query) || $status_filter !== 'all' || !empty($year_filter) || $category_filter > 0): ?>
                    <p>No records match your current filters. Try adjusting your search criteria.</p>
                    <div class="empty-actions">
                        <a href="history.php" class="btn btn-primary">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    </div>
                <?php else: ?>
                    <p>You haven't borrowed any books yet. Start exploring our collection!</p>
                    <div class="empty-actions">
                        <a href="search.php" class="btn btn-primary">
                            <i class="fas fa-search"></i> Search Books
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <a href="my-books.php" class="action-btn">
            <i class="fas fa-book-reader"></i>
            <span>Current Books</span>
        </a>
        <a href="search.php" class="action-btn">
            <i class="fas fa-search"></i>
            <span>Search Books</span>
        </a>
        <a href="fines.php" class="action-btn">
            <i class="fas fa-money-bill-wave"></i>
            <span>View Fines</span>
        </a>
        <a href="../profile.php" class="action-btn">
            <i class="fas fa-user"></i>
            <span>My Profile</span>
        </a>
    </div>
</div>

<!-- Book Details Modal -->
<div id="bookModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Book Details</h3>
            <span class="modal-close" onclick="closeModal()">&times;</span>
        </div>
        <div class="modal-body" id="modalBody">
            <!-- Book details will be loaded here -->
        </div>
    </div>
</div>

<style>
/* History Page Specific Styles */
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

.stat-card.stat-warning {
    border-left-color: #ffc107;
}

.stat-card.stat-info-highlight {
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

.stat-warning .stat-icon {
    color: #ffc107;
}

.stat-info-highlight .stat-icon {
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

.filter-input,
.filter-select {
    padding: 0.75rem;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    background: white;
}

.filter-input:focus,
.filter-select:focus {
    outline: none;
    border-color: #007bff;
}

.filters-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
}

/* History Section */
.history-section {
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

.history-list {
    padding: 0;
}

.history-item {
    display: flex;
    align-items: flex-start;
    gap: 1.5rem;
    padding: 1.5rem;
    border-bottom: 1px solid #f8f9fa;
    transition: all 0.3s ease;
}

.history-item:last-child {
    border-bottom: none;
}

.history-item:hover {
    background: #f8f9fa;
}

.current-item {
    background: #f0f8ff;
    border-left: 4px solid #17a2b8;
}

.overdue-item {
    background: #fff5f5;
    border-left: 4px solid #dc3545;
}

.late-item {
    background: #fffbf0;
    border-left: 4px solid #ffc107;
}

.returned-item {
    border-left: 4px solid #28a745;
}

.history-status {
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

.status-badge.current {
    background: #d1ecf1;
    color: #0c5460;
}

.status-badge.overdue {
    background: #f8d7da;
    color: #721c24;
}

.status-badge.late {
    background: #fff3cd;
    color: #856404;
}

.status-badge.returned {
    background: #d4edda;
    color: #155724;
}

.history-content {
    flex: 1;
    min-width: 0;
}

.book-info {
    margin-bottom: 1rem;
}

.book-title {
    margin: 0 0 0.5rem 0;
    color: #1e3c72;
    font-size: 1.2rem;
    line-height: 1.3;
}

.book-author {
    margin: 0 0 0.75rem 0;
    color: #6c757d;
    font-style: italic;
    font-weight: 600;
}

.book-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 0.75rem;
    font-size: 0.9rem;
    color: #6c757d;
}

.book-category {
    margin-top: 0.5rem;
}

.category-badge {
    background: #e9ecef;
    color: #495057;
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 600;
}

.borrow-details {
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

.history-actions {
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
    color: #e9ecef;
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

/* Print Styles */
@media print {
    .filters-section,
    .quick-actions,
    .pagination-section,
    .history-actions,
    .section-actions {
        display: none !important;
    }
    
    .history-item {
        break-inside: avoid;
        border: 1px solid #ddd;
        margin-bottom: 1rem;
    }
    
    .stats-grid {
        grid-template-columns: repeat(3, 1fr);
    }
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
    
    .history-item {
        flex-direction: column;
        gap: 1rem;
    }
    
    .history-actions {
        align-items: stretch;
        flex-direction: row;
    }
    
    .detail-row {
        grid-template-columns: 1fr;
    }
    
    .book-meta {
        flex-direction: column;
        gap: 0.5rem;
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
function showBookDetails(bookId) {
    document.getElementById('modalTitle').textContent = 'Book Details';
    document.getElementById('modalBody').innerHTML = `
        <div class="loading">
            <i class="fas fa-spinner fa-spin"></i> Loading book details...
        </div>
    `;
    document.getElementById('bookModal').style.display = 'block';
    
    // Simulate loading (in real implementation, this would be an AJAX call)
    setTimeout(() => {
        document.getElementById('modalBody').innerHTML = `
            <div class="book-details">
                <p><strong>Book ID:</strong> ${bookId}</p>
                <p><strong>Status:</strong> This feature will show detailed book information including description, availability, and complete borrowing history for this specific book.</p>
                <p><strong>Note:</strong> Contact the librarian for additional information about this book.</p>
            </div>
        `;
    }, 1000);
}

function closeModal() {
    document.getElementById('bookModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('bookModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}

// Auto-submit form when filters change (optional)
document.addEventListener('DOMContentLoaded', function() {
    const filterSelects = document.querySelectorAll('.filter-select');
    filterSelects.forEach(select => {
        select.addEventListener('change', function() {
            // Uncomment to auto-submit on filter change
            // this.form.submit();
        });
    });
    
    // Add confirmation for print
    const printBtn = document.querySelector('button[onclick="window.print()"]');
    if (printBtn) {
        printBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('This will print your complete borrowing history. Continue?')) {
                window.print();
            }
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>