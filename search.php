<?php
/**
 * Book Search - Ethiopian Police University Library Management System
 * Advanced book search interface for students and staff
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

$page_title = 'Search Books';
$current_user = get_logged_in_user();

// Get search parameters
$search_query = sanitize_input($_GET['q'] ?? '');
$search_type = sanitize_input($_GET['type'] ?? 'all');
$category_filter = (int)($_GET['category'] ?? 0);
$availability_filter = sanitize_input($_GET['availability'] ?? 'all');
$sort_by = sanitize_input($_GET['sort'] ?? 'title');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 12;
$offset = ($page - 1) * $per_page;

$books = [];
$total_books = 0;
$search_performed = false;

// Perform search if query is provided
if (!empty($search_query) || $category_filter > 0 || $availability_filter !== 'all') {
    $search_performed = true;
    
    try {
        // Build search conditions
        $where_conditions = [];
        $params = [];
        
        // Text search
        if (!empty($search_query)) {
            switch ($search_type) {
                case 'title':
                    $where_conditions[] = 'b.title LIKE ?';
                    $params[] = '%' . $search_query . '%';
                    break;
                case 'author':
                    $where_conditions[] = 'b.author LIKE ?';
                    $params[] = '%' . $search_query . '%';
                    break;
                case 'isbn':
                    $where_conditions[] = 'b.isbn LIKE ?';
                    $params[] = '%' . $search_query . '%';
                    break;
                case 'publisher':
                    $where_conditions[] = 'b.publisher LIKE ?';
                    $params[] = '%' . $search_query . '%';
                    break;
                default: // 'all'
                    $where_conditions[] = '(b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ? OR b.publisher LIKE ?)';
                    $params = array_merge($params, [
                        '%' . $search_query . '%',
                        '%' . $search_query . '%',
                        '%' . $search_query . '%',
                        '%' . $search_query . '%'
                    ]);
            }
        }
        
        // Category filter
        if ($category_filter > 0) {
            $where_conditions[] = 'b.category_id = ?';
            $params[] = $category_filter;
        }
        
        // Availability filter
        if ($availability_filter === 'available') {
            $where_conditions[] = 'b.available_copies > 0';
        } elseif ($availability_filter === 'borrowed') {
            $where_conditions[] = 'b.available_copies = 0';
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Sort options
        $sort_options = [
            'title' => 'b.title ASC',
            'author' => 'b.author ASC',
            'year' => 'b.publication_year DESC',
            'category' => 'c.category_name ASC, b.title ASC',
            'availability' => 'b.available_copies DESC, b.title ASC'
        ];
        $order_clause = 'ORDER BY ' . ($sort_options[$sort_by] ?? $sort_options['title']);
        
        // Count total results
        $count_sql = "SELECT COUNT(*) as total 
                      FROM books b 
                      JOIN categories c ON b.category_id = c.category_id 
                      $where_clause";
        $total_books = execute_query($count_sql, $params)->fetch()['total'];
        
        // Get books with pagination
        $books_sql = "SELECT b.*, c.category_name,
                             CASE 
                                 WHEN b.available_copies > 0 THEN 'Available'
                                 ELSE 'Not Available'
                             END as availability_status,
                             (SELECT COUNT(*) FROM borrow_records br WHERE br.book_id = b.book_id AND br.return_date IS NULL) as current_borrowers
                      FROM books b 
                      JOIN categories c ON b.category_id = c.category_id 
                      $where_clause 
                      $order_clause 
                      LIMIT $per_page OFFSET $offset";
        
        $books = execute_query($books_sql, $params)->fetchAll();
        
    } catch (Exception $e) {
        error_log("Search error: " . $e->getMessage());
        $error_message = "An error occurred while searching. Please try again.";
    }
}

// Get categories for filter dropdown
try {
    $categories_sql = "SELECT * FROM categories ORDER BY category_name";
    $categories = execute_query($categories_sql)->fetchAll();
} catch (Exception $e) {
    $categories = [];
}

// Calculate pagination
$total_pages = ceil($total_books / $per_page);

include '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-search"></i> Search Books</h1>
        <div class="header-actions">
            <span class="role-badge"><?php echo ucfirst($current_user['role']); ?> Portal</span>
        </div>
    </div>

    <!-- Search Form -->
    <div class="search-section">
        <form method="GET" class="search-form">
            <div class="search-main">
                <div class="search-input-group">
                    <input type="text" 
                           name="q" 
                           value="<?php echo htmlspecialchars($search_query); ?>" 
                           placeholder="Search for books, authors, ISBN, or publishers..." 
                           class="search-input">
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
            </div>
            
            <div class="search-filters">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="type">Search In:</label>
                        <select name="type" id="type" class="filter-select">
                            <option value="all" <?php echo $search_type === 'all' ? 'selected' : ''; ?>>All Fields</option>
                            <option value="title" <?php echo $search_type === 'title' ? 'selected' : ''; ?>>Title</option>
                            <option value="author" <?php echo $search_type === 'author' ? 'selected' : ''; ?>>Author</option>
                            <option value="isbn" <?php echo $search_type === 'isbn' ? 'selected' : ''; ?>>ISBN</option>
                            <option value="publisher" <?php echo $search_type === 'publisher' ? 'selected' : ''; ?>>Publisher</option>
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
                        <label for="availability">Availability:</label>
                        <select name="availability" id="availability" class="filter-select">
                            <option value="all" <?php echo $availability_filter === 'all' ? 'selected' : ''; ?>>All Books</option>
                            <option value="available" <?php echo $availability_filter === 'available' ? 'selected' : ''; ?>>Available</option>
                            <option value="borrowed" <?php echo $availability_filter === 'borrowed' ? 'selected' : ''; ?>>Currently Borrowed</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="sort">Sort By:</label>
                        <select name="sort" id="sort" class="filter-select">
                            <option value="title" <?php echo $sort_by === 'title' ? 'selected' : ''; ?>>Title A-Z</option>
                            <option value="author" <?php echo $sort_by === 'author' ? 'selected' : ''; ?>>Author A-Z</option>
                            <option value="year" <?php echo $sort_by === 'year' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="category" <?php echo $sort_by === 'category' ? 'selected' : ''; ?>>Category</option>
                            <option value="availability" <?php echo $sort_by === 'availability' ? 'selected' : ''; ?>>Availability</option>
                        </select>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Search Results -->
    <?php if ($search_performed): ?>
        <div class="results-section">
            <div class="results-header">
                <div class="results-info">
                    <?php if ($total_books > 0): ?>
                        <h3>Found <?php echo number_format($total_books); ?> book<?php echo $total_books !== 1 ? 's' : ''; ?></h3>
                        <?php if (!empty($search_query)): ?>
                            <p>Search results for: "<strong><?php echo htmlspecialchars($search_query); ?></strong>"</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <h3>No books found</h3>
                        <p>Try adjusting your search criteria or browse by category.</p>
                    <?php endif; ?>
                </div>
                
                <?php if ($total_books > 0): ?>
                    <div class="results-pagination-info">
                        <span>Showing <?php echo number_format($offset + 1); ?>-<?php echo number_format(min($offset + $per_page, $total_books)); ?> of <?php echo number_format($total_books); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($books)): ?>
                <div class="books-grid">
                    <?php foreach ($books as $book): ?>
                        <div class="book-card">
                            <div class="book-header">
                                <div class="book-availability">
                                    <?php if ($book['available_copies'] > 0): ?>
                                        <span class="availability-badge available">
                                            <i class="fas fa-check-circle"></i> Available
                                        </span>
                                    <?php else: ?>
                                        <span class="availability-badge unavailable">
                                            <i class="fas fa-times-circle"></i> Not Available
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="book-category">
                                    <span class="category-badge"><?php echo htmlspecialchars($book['category_name']); ?></span>
                                </div>
                            </div>
                            
                            <div class="book-content">
                                <h3 class="book-title"><?php echo htmlspecialchars($book['title']); ?></h3>
                                <p class="book-author">by <?php echo htmlspecialchars($book['author']); ?></p>
                                <p class="book-publisher"><?php echo htmlspecialchars($book['publisher']); ?></p>
                                <?php if ($book['publication_year']): ?>
                                    <p class="book-year">Published: <?php echo $book['publication_year']; ?></p>
                                <?php endif; ?>
                                <p class="book-isbn">ISBN: <?php echo htmlspecialchars($book['isbn']); ?></p>
                            </div>
                            
                            <div class="book-footer">
                                <div class="book-stats">
                                    <div class="stat-item">
                                        <i class="fas fa-books"></i>
                                        <span><?php echo $book['available_copies']; ?>/<?php echo $book['total_copies']; ?> available</span>
                                    </div>
                                    <?php if ($book['current_borrowers'] > 0): ?>
                                        <div class="stat-item">
                                            <i class="fas fa-users"></i>
                                            <span><?php echo $book['current_borrowers']; ?> borrowed</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="book-actions">
                                    <?php if ($book['available_copies'] > 0): ?>
                                        <button class="btn btn-primary btn-sm" onclick="showBookDetails(<?php echo $book['book_id']; ?>)">
                                            <i class="fas fa-info-circle"></i> Details
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-secondary btn-sm" disabled>
                                            <i class="fas fa-clock"></i> Not Available
                                        </button>
                                    <?php endif; ?>
                                </div>
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
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- Welcome/Browse Section -->
        <div class="browse-section">
            <div class="welcome-card">
                <h2><i class="fas fa-book-open"></i> Welcome to the Library Catalog</h2>
                <p>Search our extensive collection of books, journals, and resources. Use the search bar above or browse by category below.</p>
            </div>
            
            <?php if (!empty($categories)): ?>
                <div class="categories-section">
                    <h3><i class="fas fa-tags"></i> Browse by Category</h3>
                    <div class="categories-grid">
                        <?php foreach ($categories as $category): ?>
                            <a href="?category=<?php echo $category['category_id']; ?>" class="category-card">
                                <div class="category-icon">
                                    <?php
                                    // Category-specific icons
                                    $icons = [
                                        'Computer Science' => 'fas fa-laptop-code',
                                        'Law Enforcement' => 'fas fa-balance-scale',
                                        'Criminal Justice' => 'fas fa-gavel',
                                        'Management' => 'fas fa-chart-line',
                                        'Psychology' => 'fas fa-brain',
                                        'History' => 'fas fa-landmark',
                                        'Literature' => 'fas fa-feather-alt',
                                        'Science' => 'fas fa-atom',
                                        'Mathematics' => 'fas fa-calculator',
                                        'Research Methods' => 'fas fa-microscope'
                                    ];
                                    $icon = $icons[$category['category_name']] ?? 'fas fa-book';
                                    ?>
                                    <i class="<?php echo $icon; ?>"></i>
                                </div>
                                <div class="category-info">
                                    <h4><?php echo htmlspecialchars($category['category_name']); ?></h4>
                                    <?php if ($category['description']): ?>
                                        <p><?php echo htmlspecialchars($category['description']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
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
/* Search Page Specific Styles */
.search-section {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    padding: 2rem;
    margin-bottom: 2rem;
}

.search-main {
    margin-bottom: 1.5rem;
}

.search-input-group {
    display: flex;
    gap: 0;
    max-width: 600px;
    margin: 0 auto;
}

.search-input {
    flex: 1;
    padding: 1rem;
    border: 2px solid #e9ecef;
    border-right: none;
    border-radius: 8px 0 0 8px;
    font-size: 1rem;
}

.search-input:focus {
    outline: none;
    border-color: #007bff;
}

.search-btn {
    padding: 1rem 2rem;
    background: #007bff;
    color: white;
    border: 2px solid #007bff;
    border-radius: 0 8px 8px 0;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s ease;
}

.search-btn:hover {
    background: #0056b3;
    border-color: #0056b3;
}

.search-filters {
    border-top: 1px solid #e9ecef;
    padding-top: 1.5rem;
}

.filter-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
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

/* Results Section */
.results-section {
    margin-bottom: 2rem;
}

.results-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #e9ecef;
}

.results-info h3 {
    margin: 0 0 0.5rem 0;
    color: #1e3c72;
}

.results-info p {
    margin: 0;
    color: #6c757d;
}

.results-pagination-info {
    color: #6c757d;
    font-size: 0.9rem;
}

/* Books Grid */
.books-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.book-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    overflow: hidden;
    transition: all 0.3s ease;
}

.book-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.book-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
}

.availability-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.availability-badge.available {
    background: #d4edda;
    color: #155724;
}

.availability-badge.unavailable {
    background: #f8d7da;
    color: #721c24;
}

.category-badge {
    background: #e9ecef;
    color: #495057;
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 600;
}

.book-content {
    padding: 1.5rem;
}

.book-title {
    margin: 0 0 0.5rem 0;
    color: #1e3c72;
    font-size: 1.2rem;
    line-height: 1.3;
}

.book-author {
    margin: 0 0 0.5rem 0;
    color: #6c757d;
    font-style: italic;
    font-weight: 600;
}

.book-publisher,
.book-year,
.book-isbn {
    margin: 0 0 0.25rem 0;
    color: #6c757d;
    font-size: 0.9rem;
}

.book-footer {
    padding: 1rem 1.5rem;
    background: #f8f9fa;
    border-top: 1px solid #e9ecef;
}

.book-stats {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    color: #6c757d;
}

.stat-item i {
    color: #007bff;
}

.book-actions {
    display: flex;
    gap: 0.5rem;
}

/* Browse Section */
.browse-section {
    margin-bottom: 2rem;
}

.welcome-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    padding: 2rem;
    text-align: center;
    margin-bottom: 2rem;
}

.welcome-card h2 {
    margin: 0 0 1rem 0;
    color: #1e3c72;
}

.welcome-card p {
    margin: 0;
    color: #6c757d;
    font-size: 1.1rem;
}

.categories-section {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    padding: 2rem;
}

.categories-section h3 {
    margin: 0 0 1.5rem 0;
    color: #1e3c72;
}

.categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1rem;
}

.category-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem;
    background: #f8f9fa;
    border-radius: 8px;
    text-decoration: none;
    color: inherit;
    transition: all 0.3s ease;
    border-left: 4px solid #007bff;
}

.category-card:hover {
    background: #e9ecef;
    transform: translateX(5px);
}

.category-icon {
    font-size: 2rem;
    color: #007bff;
    flex-shrink: 0;
}

.category-info h4 {
    margin: 0 0 0.5rem 0;
    color: #1e3c72;
}

.category-info p {
    margin: 0;
    color: #6c757d;
    font-size: 0.9rem;
}

/* Pagination */
.pagination-section {
    display: flex;
    justify-content: center;
    margin-top: 2rem;
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
    .search-input-group {
        flex-direction: column;
    }
    
    .search-input,
    .search-btn {
        border-radius: 8px;
        border: 2px solid #e9ecef;
    }
    
    .search-btn {
        margin-top: 0.5rem;
    }
    
    .filter-row {
        grid-template-columns: 1fr;
    }
    
    .results-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .books-grid {
        grid-template-columns: 1fr;
    }
    
    .categories-grid {
        grid-template-columns: 1fr;
    }
    
    .category-card {
        flex-direction: column;
        text-align: center;
    }
}
</style>

<script>
function showBookDetails(bookId) {
    // This would typically make an AJAX call to get book details
    // For now, we'll show a placeholder
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
                <p><strong>Status:</strong> This feature will be implemented to show detailed book information, borrowing history, and availability.</p>
                <p><strong>Note:</strong> Students can contact the librarian for more information about this book.</p>
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

// Auto-submit form when filters change
document.addEventListener('DOMContentLoaded', function() {
    const filterSelects = document.querySelectorAll('.filter-select');
    filterSelects.forEach(select => {
        select.addEventListener('change', function() {
            // Auto-submit form when filter changes (optional)
            // this.form.submit();
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>