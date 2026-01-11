<?php
/**
 * My Books - Ethiopian Police University Library Management System
 * View currently borrowed books for students and staff
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

$page_title = 'My Borrowed Books';
$current_user = get_logged_in_user();
$user_id = $current_user['user_id'];

$success_message = '';
$error_message = '';

// Handle renewal request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_renewal') {
    $borrow_id = (int)$_POST['borrow_id'];
    
    try {
        // Verify the borrow record belongs to the current user
        $verify_sql = "SELECT br.*, b.title 
                       FROM borrow_records br 
                       JOIN books b ON br.book_id = b.book_id 
                       WHERE br.borrow_id = ? AND br.user_id = ? AND br.return_date IS NULL";
        $borrow_record = execute_query($verify_sql, [$borrow_id, $user_id])->fetch();
        
        if ($borrow_record) {
            // For now, we'll just show a message that the request has been submitted
            // In a full implementation, this would create a renewal request record
            $success_message = "Renewal request submitted for '{$borrow_record['title']}'. Please contact the librarian for processing.";
        } else {
            $error_message = "Invalid book record or book not currently borrowed by you.";
        }
    } catch (Exception $e) {
        error_log("Renewal request error: " . $e->getMessage());
        $error_message = "An error occurred while processing your renewal request.";
    }
}

// Get currently borrowed books
$borrowed_books = [];
$borrowing_stats = [];

try {
    // Get borrowed books with details
    $borrowed_sql = "SELECT br.borrow_id, br.borrow_date, br.due_date, br.status,
                            b.book_id, b.title, b.author, b.isbn, b.publisher, b.publication_year,
                            c.category_name,
                            DATEDIFF(br.due_date, CURDATE()) as days_until_due,
                            DATEDIFF(CURDATE(), br.due_date) as days_overdue,
                            CASE 
                                WHEN br.due_date < CURDATE() THEN 'overdue'
                                WHEN DATEDIFF(br.due_date, CURDATE()) <= 3 THEN 'due_soon'
                                ELSE 'normal'
                            END as urgency_status,
                            (SELECT COUNT(*) FROM fines f WHERE f.borrow_id = br.borrow_id AND f.payment_status = 'unpaid') as has_unpaid_fines
                     FROM borrow_records br
                     JOIN books b ON br.book_id = b.book_id
                     JOIN categories c ON b.category_id = c.category_id
                     WHERE br.user_id = ? AND br.return_date IS NULL
                     ORDER BY 
                         CASE 
                             WHEN br.due_date < CURDATE() THEN 1
                             WHEN DATEDIFF(br.due_date, CURDATE()) <= 3 THEN 2
                             ELSE 3
                         END,
                         br.due_date ASC";
    
    $borrowed_books = execute_query($borrowed_sql, [$user_id])->fetchAll();
    
    // Get borrowing statistics
    $stats_sql = "SELECT 
                    COUNT(*) as total_borrowed,
                    COUNT(CASE WHEN br.due_date < CURDATE() THEN 1 END) as overdue_count,
                    COUNT(CASE WHEN DATEDIFF(br.due_date, CURDATE()) <= 3 AND br.due_date >= CURDATE() THEN 1 END) as due_soon_count,
                    (SELECT setting_value FROM system_settings WHERE setting_key = 'max_books_per_user') as max_allowed,
                    (SELECT COALESCE(SUM(fine_amount), 0) FROM fines f 
                     JOIN borrow_records br2 ON f.borrow_id = br2.borrow_id 
                     WHERE br2.user_id = ? AND f.payment_status = 'unpaid') as total_unpaid_fines
                  FROM borrow_records br
                  WHERE br.user_id = ? AND br.return_date IS NULL";
    
    $borrowing_stats = execute_query($stats_sql, [$user_id, $user_id])->fetch();
    
} catch (Exception $e) {
    error_log("Error fetching borrowed books: " . $e->getMessage());
    $error_message = "An error occurred while loading your borrowed books.";
}

include '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-book-reader"></i> My Borrowed Books</h1>
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

    <!-- Borrowing Statistics -->
    <div class="stats-section">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-books"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $borrowing_stats['total_borrowed']; ?>/<?php echo $borrowing_stats['max_allowed']; ?></h3>
                    <p>Books Borrowed</p>
                </div>
            </div>
            
            <div class="stat-card <?php echo $borrowing_stats['overdue_count'] > 0 ? 'stat-danger' : ''; ?>">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $borrowing_stats['overdue_count']; ?></h3>
                    <p>Overdue Books</p>
                </div>
            </div>
            
            <div class="stat-card <?php echo $borrowing_stats['due_soon_count'] > 0 ? 'stat-warning' : ''; ?>">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $borrowing_stats['due_soon_count']; ?></h3>
                    <p>Due Soon (3 days)</p>
                </div>
            </div>
            
            <div class="stat-card <?php echo $borrowing_stats['total_unpaid_fines'] > 0 ? 'stat-danger' : ''; ?>">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($borrowing_stats['total_unpaid_fines'], 2); ?> ETB</h3>
                    <p>Unpaid Fines</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Borrowed Books List -->
    <div class="books-section">
        <?php if (!empty($borrowed_books)): ?>
            <div class="section-header">
                <h2><i class="fas fa-list"></i> Currently Borrowed Books</h2>
                <div class="section-actions">
                    <span class="books-count"><?php echo count($borrowed_books); ?> book<?php echo count($borrowed_books) !== 1 ? 's' : ''; ?></span>
                </div>
            </div>
            
            <div class="books-list">
                <?php foreach ($borrowed_books as $book): ?>
                    <div class="book-item <?php echo $book['urgency_status']; ?>-item">
                        <div class="book-status-indicator">
                            <?php if ($book['urgency_status'] === 'overdue'): ?>
                                <div class="status-badge overdue">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    Overdue
                                </div>
                            <?php elseif ($book['urgency_status'] === 'due_soon'): ?>
                                <div class="status-badge due-soon">
                                    <i class="fas fa-clock"></i>
                                    Due Soon
                                </div>
                            <?php else: ?>
                                <div class="status-badge normal">
                                    <i class="fas fa-check-circle"></i>
                                    On Time
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="book-content">
                            <div class="book-main-info">
                                <h3 class="book-title"><?php echo htmlspecialchars($book['title']); ?></h3>
                                <p class="book-author">by <?php echo htmlspecialchars($book['author']); ?></p>
                                <div class="book-meta">
                                    <span class="book-publisher"><?php echo htmlspecialchars($book['publisher']); ?></span>
                                    <?php if ($book['publication_year']): ?>
                                        <span class="book-year">(<?php echo $book['publication_year']; ?>)</span>
                                    <?php endif; ?>
                                    <span class="book-isbn">ISBN: <?php echo htmlspecialchars($book['isbn']); ?></span>
                                </div>
                                <div class="book-category">
                                    <span class="category-badge"><?php echo htmlspecialchars($book['category_name']); ?></span>
                                </div>
                            </div>
                            
                            <div class="book-dates">
                                <div class="date-info">
                                    <label>Borrowed:</label>
                                    <span><?php echo date('M j, Y', strtotime($book['borrow_date'])); ?></span>
                                </div>
                                <div class="date-info">
                                    <label>Due Date:</label>
                                    <span class="<?php echo $book['urgency_status'] === 'overdue' ? 'text-danger' : ($book['urgency_status'] === 'due_soon' ? 'text-warning' : ''); ?>">
                                        <?php echo date('M j, Y', strtotime($book['due_date'])); ?>
                                    </span>
                                </div>
                                <div class="date-info">
                                    <label>Status:</label>
                                    <span class="status-text <?php echo $book['urgency_status']; ?>">
                                        <?php if ($book['urgency_status'] === 'overdue'): ?>
                                            <?php echo $book['days_overdue']; ?> day<?php echo $book['days_overdue'] !== 1 ? 's' : ''; ?> overdue
                                        <?php elseif ($book['urgency_status'] === 'due_soon'): ?>
                                            Due in <?php echo $book['days_until_due']; ?> day<?php echo $book['days_until_due'] !== 1 ? 's' : ''; ?>
                                        <?php else: ?>
                                            <?php echo $book['days_until_due']; ?> day<?php echo $book['days_until_due'] !== 1 ? 's' : ''; ?> remaining
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="book-actions">
                            <?php if ($book['has_unpaid_fines'] > 0): ?>
                                <div class="fine-notice">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <span>Has unpaid fines</span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="action-buttons">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="request_renewal">
                                    <input type="hidden" name="borrow_id" value="<?php echo $book['borrow_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline" 
                                            <?php echo $book['urgency_status'] === 'overdue' ? 'disabled title="Cannot renew overdue books"' : ''; ?>>
                                        <i class="fas fa-redo"></i> Request Renewal
                                    </button>
                                </form>
                                
                                <button class="btn btn-sm btn-info" onclick="showBookDetails(<?php echo $book['book_id']; ?>)">
                                    <i class="fas fa-info-circle"></i> Details
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
        <?php else: ?>
            <!-- No Books Borrowed -->
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-book-open"></i>
                </div>
                <h3>No Books Currently Borrowed</h3>
                <p>You don't have any books checked out at the moment.</p>
                <div class="empty-actions">
                    <a href="search.php" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search Books
                    </a>
                    <a href="history.php" class="btn btn-outline">
                        <i class="fas fa-history"></i> View History
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Important Information -->
    <div class="info-section">
        <div class="info-card">
            <h3><i class="fas fa-info-circle"></i> Important Information</h3>
            <div class="info-content">
                <div class="info-item">
                    <h4>Borrowing Policies</h4>
                    <ul>
                        <li>Standard borrowing period is 14 days</li>
                        <li>Maximum of <?php echo $borrowing_stats['max_allowed']; ?> books can be borrowed at once</li>
                        <li>Renewals may be requested before the due date</li>
                        <li>Overdue books cannot be renewed</li>
                    </ul>
                </div>
                
                <div class="info-item">
                    <h4>Fines and Penalties</h4>
                    <ul>
                        <li>Late return fine: 2.00 ETB per day per book</li>
                        <li>Fines must be paid before borrowing new books</li>
                        <li>Contact the librarian for fine payment options</li>
                        <li>Damaged or lost books incur replacement costs</li>
                    </ul>
                </div>
                
                <div class="info-item">
                    <h4>Need Help?</h4>
                    <ul>
                        <li>Contact the library for renewal requests</li>
                        <li>Report damaged books immediately</li>
                        <li>Visit the library for fine payments</li>
                        <li>Ask librarians for research assistance</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <a href="search.php" class="action-btn">
            <i class="fas fa-search"></i>
            <span>Search More Books</span>
        </a>
        <a href="history.php" class="action-btn">
            <i class="fas fa-history"></i>
            <span>Borrowing History</span>
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
/* My Books Page Specific Styles */
.stats-section {
    margin-bottom: 2rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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

/* Books Section */
.books-section {
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

.section-header h2 {
    margin: 0;
    color: #1e3c72;
}

.books-count {
    color: #6c757d;
    font-size: 0.9rem;
}

.books-list {
    padding: 0;
}

.book-item {
    display: flex;
    align-items: flex-start;
    gap: 1.5rem;
    padding: 1.5rem;
    border-bottom: 1px solid #f8f9fa;
    transition: all 0.3s ease;
}

.book-item:last-child {
    border-bottom: none;
}

.book-item:hover {
    background: #f8f9fa;
}

.overdue-item {
    background: #fff5f5;
    border-left: 4px solid #dc3545;
}

.due-soon-item {
    background: #fffbf0;
    border-left: 4px solid #ffc107;
}

.normal-item {
    border-left: 4px solid #28a745;
}

.book-status-indicator {
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
}

.status-badge.overdue {
    background: #f8d7da;
    color: #721c24;
}

.status-badge.due-soon {
    background: #fff3cd;
    color: #856404;
}

.status-badge.normal {
    background: #d4edda;
    color: #155724;
}

.book-content {
    flex: 1;
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
}

.book-main-info {
    min-width: 0;
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

.book-dates {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.date-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.date-info label {
    font-size: 0.8rem;
    font-weight: 600;
    color: #6c757d;
    text-transform: uppercase;
}

.date-info span {
    font-weight: 600;
}

.status-text.overdue {
    color: #dc3545;
}

.status-text.due-soon {
    color: #ffc107;
}

.status-text.normal {
    color: #28a745;
}

.book-actions {
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    align-items: flex-end;
}

.fine-notice {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #dc3545;
    font-size: 0.9rem;
    font-weight: 600;
}

.action-buttons {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
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
    
    .book-content {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .book-item {
        flex-direction: column;
        gap: 1rem;
    }
    
    .book-actions {
        align-items: stretch;
    }
    
    .action-buttons {
        flex-direction: row;
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
                <p><strong>Status:</strong> This feature will show detailed book information including description, availability, and borrowing history.</p>
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

// Confirmation for renewal requests
document.addEventListener('DOMContentLoaded', function() {
    const renewalForms = document.querySelectorAll('form[action=""][method="POST"]');
    renewalForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const bookTitle = this.closest('.book-item').querySelector('.book-title').textContent;
            if (!confirm(`Request renewal for "${bookTitle}"?`)) {
                e.preventDefault();
            }
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>