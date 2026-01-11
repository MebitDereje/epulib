<?php
/**
 * Librarian Book Management - Ethiopian Police University Library Management System
 * Handles book viewing, searching, and limited editing operations for librarians
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

$page_title = 'Book Management';
$success_message = '';
$error_message = '';

// Handle form submissions (limited operations for librarians)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // Verify CSRF token
    if (!verify_csrf_token($csrf_token)) {
        $error_message = 'Invalid request. Please try again.';
    } else {
        switch ($action) {
            case 'update_status':
                $book_id = (int)($_POST['book_id'] ?? 0);
                $status = sanitize_input($_POST['status'] ?? '');
                
                if ($book_id <= 0 || !in_array($status, ['available', 'maintenance'])) {
                    $error_message = 'Invalid book ID or status.';
                } else {
                    try {
                        // Check if book is currently borrowed
                        $check_sql = "SELECT COUNT(*) as borrowed_count FROM borrow_records 
                                     WHERE book_id = ? AND return_date IS NULL";
                        $check_result = execute_query($check_sql, [$book_id]);
                        $borrowed_count = $check_result->fetch()['borrowed_count'];
                        
                        if ($borrowed_count > 0 && $status === 'maintenance') {
                            $error_message = 'Cannot set book to maintenance. It is currently borrowed by users.';
                        } else {
                            // Get book title for logging
                            $title_sql = "SELECT title FROM books WHERE book_id = ?";
                            $title_result = execute_query($title_sql, [$book_id]);
                            $book_title = $title_result->fetch()['title'] ?? 'Unknown';
                            
                            $sql = "UPDATE books SET status = ? WHERE book_id = ?";
                            execute_query($sql, [$status, $book_id]);
                            $success_message = 'Book status updated successfully!';
                            log_security_event("Book status updated: $book_title to $status", $_SESSION['user_id']);
                        }
                    } catch (Exception $e) {
                        $error_message = 'Error updating book status. Please try again.';
                    }
                }
                break;
        }
    }
}

// Get search parameters
$search = sanitize_input($_GET['search'] ?? '');
$category_filter = (int)($_GET['category'] ?? 0);
$status_filter = sanitize_input($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build search query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(title LIKE ? OR author LIKE ? OR isbn LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($category_filter > 0) {
    $where_conditions[] = "b.category_id = ?";
    $params[] = $category_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "b.status = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM books b $where_clause";
$count_result = execute_query($count_sql, $params);
$total_books = $count_result->fetch()['total'];
$total_pages = ceil($total_books / $per_page);

// Get books with pagination
$sql = "SELECT b.*, c.category_name,
               (SELECT COUNT(*) FROM borrow_records br WHERE br.book_id = b.book_id AND br.return_date IS NULL) as currently_borrowed,
               (SELECT GROUP_CONCAT(CONCAT(u.full_name, ' (', u.id_number, ')') SEPARATOR ', ') 
                FROM borrow_records br 
                JOIN users u ON br.user_id = u.user_id 
                WHERE br.book_id = b.book_id AND br.return_date IS NULL) as borrower_info
        FROM books b 
        JOIN categories c ON b.category_id = c.category_id 
        $where_clause
        ORDER BY b.title ASC 
        LIMIT $per_page OFFSET $offset";

$books_result = execute_query($sql, $params);
$books = $books_result->fetchAll();

// Get categories for dropdown
$categories_sql = "SELECT * FROM categories ORDER BY category_name";
$categories_result = execute_query($categories_sql);
$categories = $categories_result->fetchAll();

include '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-book"></i> Book Management</h1>
        <div class="header-info">
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

    <!-- Search and Filter Section -->
    <div class="search-section">
        <form method="GET" class="search-form">
            <div class="search-row">
                <div class="search-input-group">
                    <input type="text" name="search" placeholder="Search by title, author, or ISBN..." 
                           value="<?php echo htmlspecialchars($search); ?>" class="form-control">
                </div>
                <div class="filter-group">
                    <select name="category" class="form-control">
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
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="available" <?php echo $status_filter === 'available' ? 'selected' : ''; ?>>Available</option>
                        <option value="borrowed" <?php echo $status_filter === 'borrowed' ? 'selected' : ''; ?>>Borrowed</option>
                        <option value="maintenance" <?php echo $status_filter === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                    </select>
                </div>
                <div class="search-buttons">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <a href="books.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Books Table -->
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>ISBN</th>
                    <th>Title</th>
                    <th>Author</th>
                    <th>Publisher</th>
                    <th>Category</th>
                    <th>Year</th>
                    <th>Copies</th>
                    <th>Available</th>
                    <th>Status</th>
                    <th>Current Borrowers</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($books)): ?>
                    <tr>
                        <td colspan="11" class="text-center">
                            <i class="fas fa-book-open"></i>
                            No books found. <?php echo !empty($search) || $category_filter > 0 || !empty($status_filter) ? 'Try adjusting your search criteria.' : 'No books available.'; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($books as $book): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($book['isbn']); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($book['title']); ?></strong>
                                <?php if ($book['currently_borrowed'] > 0): ?>
                                    <br><small class="text-warning">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <?php echo $book['currently_borrowed']; ?> copy(ies) borrowed
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($book['author']); ?></td>
                            <td><?php echo htmlspecialchars($book['publisher']); ?></td>
                            <td>
                                <span class="category-badge">
                                    <?php echo htmlspecialchars($book['category_name']); ?>
                                </span>
                            </td>
                            <td><?php echo $book['publication_year']; ?></td>
                            <td><?php echo $book['total_copies']; ?></td>
                            <td><?php echo $book['available_copies']; ?></td>
                            <td>
                                <?php if ($book['status'] === 'available'): ?>
                                    <span class="status-badge status-available">Available</span>
                                <?php elseif ($book['status'] === 'borrowed'): ?>
                                    <span class="status-badge status-borrowed">Borrowed</span>
                                <?php else: ?>
                                    <span class="status-badge status-maintenance">Maintenance</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($book['borrower_info']): ?>
                                    <small class="borrower-info">
                                        <?php echo htmlspecialchars($book['borrower_info']); ?>
                                    </small>
                                <?php else: ?>
                                    <span class="text-muted">None</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button type="button" class="btn btn-sm btn-info" 
                                            onclick="showBookDetails(<?php echo htmlspecialchars(json_encode($book)); ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <?php if ($book['currently_borrowed'] == 0): ?>
                                        <button type="button" class="btn btn-sm btn-warning" 
                                                onclick="showStatusModal(<?php echo $book['book_id']; ?>, '<?php echo htmlspecialchars($book['title']); ?>', '<?php echo $book['status']; ?>')">
                                            <i class="fas fa-tools"></i> Status
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-secondary" disabled title="Cannot change status - book is currently borrowed">
                                            <i class="fas fa-lock"></i> Locked
                                        </button>
                                    <?php endif; ?>
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
                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_books); ?> 
                of <?php echo $total_books; ?> books
            </div>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&status=<?php echo urlencode($status_filter); ?>" 
                       class="btn btn-sm btn-secondary">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&status=<?php echo urlencode($status_filter); ?>" 
                       class="btn btn-sm <?php echo $i == $page ? 'btn-primary' : 'btn-secondary'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&status=<?php echo urlencode($status_filter); ?>" 
                       class="btn btn-sm btn-secondary">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Book Details Modal -->
<div id="bookDetailsModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-book"></i> Book Details</h2>
            <button type="button" class="close-btn" onclick="hideBookDetailsModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="book-details">
                <div class="detail-row">
                    <label>ISBN:</label>
                    <span id="detail_isbn"></span>
                </div>
                <div class="detail-row">
                    <label>Title:</label>
                    <span id="detail_title"></span>
                </div>
                <div class="detail-row">
                    <label>Author:</label>
                    <span id="detail_author"></span>
                </div>
                <div class="detail-row">
                    <label>Publisher:</label>
                    <span id="detail_publisher"></span>
                </div>
                <div class="detail-row">
                    <label>Category:</label>
                    <span id="detail_category"></span>
                </div>
                <div class="detail-row">
                    <label>Publication Year:</label>
                    <span id="detail_year"></span>
                </div>
                <div class="detail-row">
                    <label>Total Copies:</label>
                    <span id="detail_total_copies"></span>
                </div>
                <div class="detail-row">
                    <label>Available Copies:</label>
                    <span id="detail_available_copies"></span>
                </div>
                <div class="detail-row">
                    <label>Status:</label>
                    <span id="detail_status"></span>
                </div>
                <div class="detail-row">
                    <label>Current Borrowers:</label>
                    <span id="detail_borrowers"></span>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="hideBookDetailsModal()">Close</button>
        </div>
    </div>
</div>

<!-- Status Update Modal -->
<div id="statusModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-tools"></i> Update Book Status</h2>
            <button type="button" class="close-btn" onclick="hideStatusModal()">&times;</button>
        </div>
        <form method="POST" id="statusForm">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" id="status_book_id" name="book_id">
            
            <div class="modal-body">
                <p>Update status for: <strong id="status_book_title"></strong></p>
                
                <div class="form-group">
                    <label for="status">New Status:</label>
                    <select id="status" name="status" class="form-control" required>
                        <option value="available">Available</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>Note:</strong> You can only change status between Available and Maintenance. 
                    Books cannot be set to maintenance while borrowed.
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideStatusModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Status
                </button>
            </div>
        </form>
    </div>
</div>

<style>
/* Additional styles for librarian book management */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #e9ecef;
}

.header-info {
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

.search-input-group {
    position: relative;
}

.filter-group select {
    min-width: 150px;
}

.search-buttons {
    display: flex;
    gap: 0.5rem;
}

.category-badge {
    background: #e9ecef;
    color: #495057;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.8rem;
}

.status-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 600;
}

.status-available {
    background: #d4edda;
    color: #155724;
}

.status-borrowed {
    background: #fff3cd;
    color: #856404;
}

.status-maintenance {
    background: #f8d7da;
    color: #721c24;
}

.borrower-info {
    color: #6c757d;
    font-size: 0.8rem;
    line-height: 1.2;
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

.book-details {
    display: grid;
    gap: 1rem;
}

.detail-row {
    display: grid;
    grid-template-columns: 150px 1fr;
    gap: 1rem;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid #f8f9fa;
}

.detail-row label {
    font-weight: 600;
    color: #495057;
}

.detail-row span {
    color: #212529;
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
    
    .action-buttons {
        flex-direction: column;
    }
    
    .pagination-container {
        flex-direction: column;
        gap: 1rem;
    }
    
    .detail-row {
        grid-template-columns: 1fr;
        gap: 0.5rem;
    }
}
</style>

<script>
// Librarian book management JavaScript functions
function showBookDetails(book) {
    document.getElementById('detail_isbn').textContent = book.isbn;
    document.getElementById('detail_title').textContent = book.title;
    document.getElementById('detail_author').textContent = book.author;
    document.getElementById('detail_publisher').textContent = book.publisher;
    document.getElementById('detail_category').textContent = book.category_name;
    document.getElementById('detail_year').textContent = book.publication_year;
    document.getElementById('detail_total_copies').textContent = book.total_copies;
    document.getElementById('detail_available_copies').textContent = book.available_copies;
    
    // Format status with badge
    const statusSpan = document.getElementById('detail_status');
    statusSpan.innerHTML = `<span class="status-badge status-${book.status}">${book.status.charAt(0).toUpperCase() + book.status.slice(1)}</span>`;
    
    // Show borrower info
    const borrowersSpan = document.getElementById('detail_borrowers');
    borrowersSpan.textContent = book.borrower_info || 'None';
    
    document.getElementById('bookDetailsModal').style.display = 'flex';
}

function hideBookDetailsModal() {
    document.getElementById('bookDetailsModal').style.display = 'none';
}

function showStatusModal(bookId, bookTitle, currentStatus) {
    document.getElementById('status_book_id').value = bookId;
    document.getElementById('status_book_title').textContent = bookTitle;
    document.getElementById('status').value = currentStatus;
    
    document.getElementById('statusModal').style.display = 'flex';
}

function hideStatusModal() {
    document.getElementById('statusModal').style.display = 'none';
    document.getElementById('statusForm').reset();
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

// Auto-refresh page every 5 minutes to keep data current
setInterval(function() {
    // Only refresh if no modals are open
    const openModals = document.querySelectorAll('.modal[style*="flex"]');
    if (openModals.length === 0) {
        location.reload();
    }
}, 300000); // 5 minutes
</script>

<?php include '../includes/footer.php'; ?>