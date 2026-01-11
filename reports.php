<?php
/**
 * Librarian Reports - Ethiopian Police University Library Management System
 * Operational reporting system for librarians with focused analytics
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

$page_title = 'Reports & Analytics';
$success_message = '';
$error_message = '';

// Get report parameters
$report_type = sanitize_input($_GET['type'] ?? 'daily_summary');
$date_from = sanitize_input($_GET['date_from'] ?? date('Y-m-01')); // First day of current month
$date_to = sanitize_input($_GET['date_to'] ?? date('Y-m-d')); // Today
$department_filter = sanitize_input($_GET['department'] ?? '');
$category_filter = (int)($_GET['category'] ?? 0);
$export_format = sanitize_input($_GET['export'] ?? '');

// Handle export requests
if (!empty($export_format) && in_array($export_format, ['csv', 'print'])) {
    handleExport($report_type, $date_from, $date_to, $department_filter, $category_filter, $export_format);
    exit();
}

// Generate report data based on type
$report_data = generateReportData($report_type, $date_from, $date_to, $department_filter, $category_filter);

/**
 * Generate report data based on type and filters
 */
function generateReportData($type, $date_from, $date_to, $department = '', $category = 0) {
    $data = [];
    
    try {
        switch ($type) {
            case 'daily_summary':
                $data = getDailySummaryReport($date_from, $date_to);
                break;
            case 'current_borrowings':
                $data = getCurrentBorrowingsReport($department, $category);
                break;
            case 'overdue_books':
                $data = getOverdueBooksReport($department);
                break;
            case 'popular_books':
                $data = getPopularBooksReport($date_from, $date_to, $category);
                break;
            case 'user_activity':
                $data = getUserActivityReport($date_from, $date_to, $department);
                break;
            case 'fines_summary':
                $data = getFinesSummaryReport($date_from, $date_to, $department);
                break;
            case 'collection_status':
                $data = getCollectionStatusReport($category);
                break;
            default:
                $data = getDailySummaryReport($date_from, $date_to);
        }
    } catch (Exception $e) {
        error_log("Report generation error: " . $e->getMessage());
        $data = ['error' => 'Error generating report data'];
    }
    
    return $data;
}

/**
 * Daily Summary Report - Daily operations overview
 */
function getDailySummaryReport($date_from, $date_to) {
    // Daily statistics
    $daily_sql = "SELECT 
                    DATE(borrow_date) as date,
                    COUNT(*) as new_borrowings,
                    COUNT(CASE WHEN return_date IS NOT NULL AND DATE(return_date) = DATE(borrow_date) THEN 1 END) as same_day_returns
                  FROM borrow_records 
                  WHERE borrow_date BETWEEN ? AND ?
                  GROUP BY DATE(borrow_date)
                  ORDER BY date DESC";
    $daily_data = execute_query($daily_sql, [$date_from, $date_to])->fetchAll();
    
    // Returns by date
    $returns_sql = "SELECT 
                      DATE(return_date) as date,
                      COUNT(*) as returns,
                      COUNT(CASE WHEN return_date > due_date THEN 1 END) as late_returns
                    FROM borrow_records 
                    WHERE return_date BETWEEN ? AND ?
                    GROUP BY DATE(return_date)
                    ORDER BY date DESC";
    $returns_data = execute_query($returns_sql, [$date_from, $date_to])->fetchAll();
    
    // Current status
    $status_sql = "SELECT 
                     (SELECT COUNT(*) FROM borrow_records WHERE return_date IS NULL) as active_borrowings,
                     (SELECT COUNT(*) FROM borrow_records WHERE return_date IS NULL AND due_date < CURDATE()) as overdue_books,
                     (SELECT COUNT(*) FROM fines WHERE payment_status = 'unpaid') as unpaid_fines,
                     (SELECT COALESCE(SUM(fine_amount), 0) FROM fines WHERE payment_status = 'unpaid') as total_unpaid_amount";
    $status = execute_query($status_sql)->fetch();
    
    return [
        'daily_data' => $daily_data,
        'returns_data' => $returns_data,
        'current_status' => $status,
        'date_range' => ['from' => $date_from, 'to' => $date_to]
    ];
}

/**
 * Current Borrowings Report - All active borrowings
 */
function getCurrentBorrowingsReport($department = '', $category = 0) {
    $where_conditions = ['br.return_date IS NULL'];
    $params = [];
    
    if (!empty($department)) {
        $where_conditions[] = 'u.department = ?';
        $params[] = $department;
    }
    
    if ($category > 0) {
        $where_conditions[] = 'b.category_id = ?';
        $params[] = $category;
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    $sql = "SELECT br.borrow_id, br.borrow_date, br.due_date,
                   u.full_name, u.id_number, u.department, u.phone, u.email,
                   b.title, b.author, b.isbn, c.category_name,
                   DATEDIFF(CURDATE(), br.due_date) as days_overdue,
                   CASE 
                       WHEN br.due_date < CURDATE() THEN 'overdue'
                       WHEN DATEDIFF(br.due_date, CURDATE()) <= 3 THEN 'due_soon'
                       ELSE 'normal'
                   END as status
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
                br.due_date ASC";
    
    $borrowings = execute_query($sql, $params)->fetchAll();
    
    // Summary statistics
    $summary = [
        'total' => count($borrowings),
        'overdue' => count(array_filter($borrowings, fn($b) => $b['status'] === 'overdue')),
        'due_soon' => count(array_filter($borrowings, fn($b) => $b['status'] === 'due_soon')),
        'normal' => count(array_filter($borrowings, fn($b) => $b['status'] === 'normal'))
    ];
    
    return [
        'borrowings' => $borrowings,
        'summary' => $summary
    ];
}

/**
 * Overdue Books Report - Books past due date
 */
function getOverdueBooksReport($department = '') {
    $where_conditions = ['br.return_date IS NULL', 'br.due_date < CURDATE()'];
    $params = [];
    
    if (!empty($department)) {
        $where_conditions[] = 'u.department = ?';
        $params[] = $department;
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    $sql = "SELECT br.borrow_id, br.borrow_date, br.due_date,
                   u.full_name, u.id_number, u.department, u.phone, u.email,
                   b.title, b.author, b.isbn, c.category_name,
                   DATEDIFF(CURDATE(), br.due_date) as days_overdue,
                   (DATEDIFF(CURDATE(), br.due_date) * 2.00) as potential_fine
            FROM borrow_records br
            JOIN users u ON br.user_id = u.user_id
            JOIN books b ON br.book_id = b.book_id
            JOIN categories c ON b.category_id = c.category_id
            $where_clause
            ORDER BY days_overdue DESC, u.department, u.full_name";
    
    $overdue_books = execute_query($sql, $params)->fetchAll();
    
    // Calculate totals
    $total_fine_amount = array_sum(array_column($overdue_books, 'potential_fine'));
    $avg_days_overdue = count($overdue_books) > 0 ? 
        array_sum(array_column($overdue_books, 'days_overdue')) / count($overdue_books) : 0;
    
    return [
        'overdue_books' => $overdue_books,
        'total_count' => count($overdue_books),
        'total_fine_amount' => $total_fine_amount,
        'avg_days_overdue' => round($avg_days_overdue, 1)
    ];
}

/**
 * Popular Books Report - Most borrowed books
 */
function getPopularBooksReport($date_from, $date_to, $category = 0) {
    $where_conditions = ['br.borrow_date BETWEEN ? AND ?'];
    $params = [$date_from, $date_to];
    
    if ($category > 0) {
        $where_conditions[] = 'b.category_id = ?';
        $params[] = $category;
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    $sql = "SELECT b.book_id, b.title, b.author, b.isbn, c.category_name,
                   COUNT(*) as borrow_count,
                   COUNT(CASE WHEN br.return_date IS NOT NULL THEN 1 END) as return_count,
                   COUNT(CASE WHEN br.return_date > br.due_date THEN 1 END) as late_returns,
                   AVG(CASE WHEN br.return_date IS NOT NULL 
                       THEN DATEDIFF(br.return_date, br.borrow_date) END) as avg_borrow_days
            FROM borrow_records br
            JOIN books b ON br.book_id = b.book_id
            JOIN categories c ON b.category_id = c.category_id
            $where_clause
            GROUP BY b.book_id, b.title, b.author, b.isbn, c.category_name
            ORDER BY borrow_count DESC, title
            LIMIT 50";
    
    return execute_query($sql, $params)->fetchAll();
}

/**
 * User Activity Report - User borrowing patterns
 */
function getUserActivityReport($date_from, $date_to, $department = '') {
    $where_conditions = ['br.borrow_date BETWEEN ? AND ?'];
    $params = [$date_from, $date_to];
    
    if (!empty($department)) {
        $where_conditions[] = 'u.department = ?';
        $params[] = $department;
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    $sql = "SELECT u.user_id, u.full_name, u.id_number, u.department, u.role,
                   COUNT(*) as total_borrowings,
                   COUNT(CASE WHEN br.return_date IS NOT NULL THEN 1 END) as returned_books,
                   COUNT(CASE WHEN br.return_date IS NULL THEN 1 END) as current_borrowings,
                   COUNT(CASE WHEN br.return_date > br.due_date THEN 1 END) as late_returns,
                   AVG(CASE WHEN br.return_date IS NOT NULL 
                       THEN DATEDIFF(br.return_date, br.borrow_date) END) as avg_borrow_days,
                   (SELECT COALESCE(SUM(fine_amount), 0) FROM fines f 
                    WHERE f.user_id = u.user_id AND f.payment_status = 'unpaid') as unpaid_fines
            FROM borrow_records br
            JOIN users u ON br.user_id = u.user_id
            $where_clause
            GROUP BY u.user_id, u.full_name, u.id_number, u.department, u.role
            ORDER BY total_borrowings DESC, u.department, u.full_name
            LIMIT 100";
    
    return execute_query($sql, $params)->fetchAll();
}

/**
 * Fines Summary Report - Fine collection overview
 */
function getFinesSummaryReport($date_from, $date_to, $department = '') {
    $where_conditions = ['f.created_at BETWEEN ? AND ?'];
    $params = [$date_from, $date_to];
    
    if (!empty($department)) {
        $where_conditions[] = 'u.department = ?';
        $params[] = $department;
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    // Fines by status
    $fines_sql = "SELECT f.fine_id, f.fine_amount, f.payment_status, f.payment_date, f.payment_method,
                         u.full_name, u.id_number, u.department,
                         b.title, b.author,
                         br.due_date, br.return_date,
                         DATEDIFF(br.return_date, br.due_date) as days_overdue
                  FROM fines f
                  JOIN borrow_records br ON f.borrow_id = br.borrow_id
                  JOIN users u ON f.user_id = u.user_id
                  JOIN books b ON br.book_id = b.book_id
                  $where_clause
                  ORDER BY f.created_at DESC";
    
    $fines = execute_query($fines_sql, $params)->fetchAll();
    
    // Summary statistics
    $total_fines = count($fines);
    $paid_fines = count(array_filter($fines, fn($f) => $f['payment_status'] === 'paid'));
    $waived_fines = count(array_filter($fines, fn($f) => $f['payment_status'] === 'waived'));
    $unpaid_fines = count(array_filter($fines, fn($f) => $f['payment_status'] === 'unpaid'));
    
    $total_amount = array_sum(array_column($fines, 'fine_amount'));
    $paid_amount = array_sum(array_column(
        array_filter($fines, fn($f) => $f['payment_status'] === 'paid'), 
        'fine_amount'
    ));
    $unpaid_amount = array_sum(array_column(
        array_filter($fines, fn($f) => $f['payment_status'] === 'unpaid'), 
        'fine_amount'
    ));
    
    return [
        'fines' => $fines,
        'summary' => [
            'total_fines' => $total_fines,
            'paid_fines' => $paid_fines,
            'waived_fines' => $waived_fines,
            'unpaid_fines' => $unpaid_fines,
            'total_amount' => $total_amount,
            'paid_amount' => $paid_amount,
            'unpaid_amount' => $unpaid_amount,
            'collection_rate' => $total_amount > 0 ? ($paid_amount / $total_amount) * 100 : 0
        ]
    ];
}

/**
 * Collection Status Report - Book collection overview
 */
function getCollectionStatusReport($category = 0) {
    $where_conditions = [];
    $params = [];
    
    if ($category > 0) {
        $where_conditions[] = 'b.category_id = ?';
        $params[] = $category;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Books by category and status
    $collection_sql = "SELECT c.category_name,
                              COUNT(b.book_id) as total_books,
                              SUM(b.total_copies) as total_copies,
                              SUM(b.available_copies) as available_copies,
                              SUM(b.total_copies - b.available_copies) as borrowed_copies,
                              COUNT(CASE WHEN b.status = 'available' THEN 1 END) as available_titles,
                              COUNT(CASE WHEN b.status = 'borrowed' THEN 1 END) as borrowed_titles,
                              COUNT(CASE WHEN b.status = 'maintenance' THEN 1 END) as maintenance_titles
                       FROM books b
                       JOIN categories c ON b.category_id = c.category_id
                       $where_clause
                       GROUP BY c.category_id, c.category_name
                       ORDER BY c.category_name";
    
    $collection_data = execute_query($collection_sql, $params)->fetchAll();
    
    // Overall statistics
    $totals = [
        'total_books' => array_sum(array_column($collection_data, 'total_books')),
        'total_copies' => array_sum(array_column($collection_data, 'total_copies')),
        'available_copies' => array_sum(array_column($collection_data, 'available_copies')),
        'borrowed_copies' => array_sum(array_column($collection_data, 'borrowed_copies'))
    ];
    
    $totals['utilization_rate'] = $totals['total_copies'] > 0 ? 
        ($totals['borrowed_copies'] / $totals['total_copies']) * 100 : 0;
    
    return [
        'collection_data' => $collection_data,
        'totals' => $totals
    ];
}

/**
 * Handle export requests
 */
function handleExport($type, $date_from, $date_to, $department, $category, $format) {
    $data = generateReportData($type, $date_from, $date_to, $department, $category);
    
    if ($format === 'csv') {
        exportToCSV($type, $data);
    } elseif ($format === 'print') {
        exportToPrint($type, $data, $date_from, $date_to);
    }
}

/**
 * Export to CSV
 */
function exportToCSV($type, $data) {
    $filename = "librarian_report_{$type}_" . date('Y-m-d') . ".csv";
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    switch ($type) {
        case 'current_borrowings':
            fputcsv($output, ['Borrower Name', 'ID Number', 'Department', 'Book Title', 'Author', 'Borrow Date', 'Due Date', 'Status', 'Days Overdue']);
            foreach ($data['borrowings'] as $row) {
                fputcsv($output, [
                    $row['full_name'],
                    $row['id_number'],
                    $row['department'],
                    $row['title'],
                    $row['author'],
                    $row['borrow_date'],
                    $row['due_date'],
                    ucfirst($row['status']),
                    $row['days_overdue'] > 0 ? $row['days_overdue'] : 'On time'
                ]);
            }
            break;
            
        case 'overdue_books':
            fputcsv($output, ['Borrower Name', 'ID Number', 'Department', 'Phone', 'Book Title', 'Author', 'Due Date', 'Days Overdue', 'Potential Fine (ETB)']);
            foreach ($data['overdue_books'] as $row) {
                fputcsv($output, [
                    $row['full_name'],
                    $row['id_number'],
                    $row['department'],
                    $row['phone'],
                    $row['title'],
                    $row['author'],
                    $row['due_date'],
                    $row['days_overdue'],
                    number_format($row['potential_fine'], 2)
                ]);
            }
            break;
            
        case 'popular_books':
            fputcsv($output, ['Book Title', 'Author', 'Category', 'Times Borrowed', 'Times Returned', 'Late Returns', 'Avg Borrow Days']);
            foreach ($data as $row) {
                fputcsv($output, [
                    $row['title'],
                    $row['author'],
                    $row['category_name'],
                    $row['borrow_count'],
                    $row['return_count'],
                    $row['late_returns'],
                    round($row['avg_borrow_days'], 1)
                ]);
            }
            break;
    }
    
    fclose($output);
}

/**
 * Export to Print
 */
function exportToPrint($type, $data, $date_from, $date_to) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Library Report - <?php echo ucfirst(str_replace('_', ' ', $type)); ?></title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; }
            .report-info { margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
            .summary { background-color: #f9f9f9; padding: 15px; margin-bottom: 20px; }
            @media print {
                body { margin: 0; }
                .no-print { display: none; }
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Ethiopian Police University Library</h1>
            <h2><?php echo ucfirst(str_replace('_', ' ', $type)); ?> Report</h2>
            <p>Generated on: <?php echo date('F j, Y g:i A'); ?></p>
            <?php if ($type !== 'collection_status'): ?>
                <p>Period: <?php echo date('M j, Y', strtotime($date_from)); ?> - <?php echo date('M j, Y', strtotime($date_to)); ?></p>
            <?php endif; ?>
        </div>
        
        <?php
        switch ($type) {
            case 'current_borrowings':
                ?>
                <div class="summary">
                    <h3>Summary</h3>
                    <p>Total Active Borrowings: <?php echo $data['summary']['total']; ?></p>
                    <p>Overdue Books: <?php echo $data['summary']['overdue']; ?></p>
                    <p>Due Soon: <?php echo $data['summary']['due_soon']; ?></p>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Borrower</th>
                            <th>Book</th>
                            <th>Borrow Date</th>
                            <th>Due Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['borrowings'] as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['full_name'] . ' (' . $row['id_number'] . ')'); ?></td>
                                <td><?php echo htmlspecialchars($row['title'] . ' by ' . $row['author']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($row['borrow_date'])); ?></td>
                                <td><?php echo date('M j, Y', strtotime($row['due_date'])); ?></td>
                                <td><?php echo ucfirst($row['status']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php
                break;
                
            case 'overdue_books':
                ?>
                <div class="summary">
                    <h3>Summary</h3>
                    <p>Total Overdue Books: <?php echo $data['total_count']; ?></p>
                    <p>Total Potential Fines: <?php echo number_format($data['total_fine_amount'], 2); ?> ETB</p>
                    <p>Average Days Overdue: <?php echo $data['avg_days_overdue']; ?> days</p>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Borrower</th>
                            <th>Contact</th>
                            <th>Book</th>
                            <th>Due Date</th>
                            <th>Days Overdue</th>
                            <th>Fine Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['overdue_books'] as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['full_name'] . ' (' . $row['id_number'] . ')'); ?></td>
                                <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                <td><?php echo htmlspecialchars($row['title']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($row['due_date'])); ?></td>
                                <td><?php echo $row['days_overdue']; ?></td>
                                <td><?php echo number_format($row['potential_fine'], 2); ?> ETB</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php
                break;
        }
        ?>
        
        <script>
            window.print();
        </script>
    </body>
    </html>
    <?php
}

// Get departments and categories for filters
$departments_sql = "SELECT DISTINCT department FROM users WHERE status = 'active' ORDER BY department";
$departments = execute_query($departments_sql)->fetchAll();

$categories_sql = "SELECT * FROM categories ORDER BY category_name";
$categories = execute_query($categories_sql)->fetchAll();

include '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-chart-bar"></i> Reports & Analytics</h1>
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

    <!-- Report Selection and Filters -->
    <div class="report-controls">
        <form method="GET" class="report-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="type">Report Type</label>
                    <select id="type" name="type" class="form-control" onchange="toggleDateFilters()">
                        <option value="daily_summary" <?php echo $report_type === 'daily_summary' ? 'selected' : ''; ?>>Daily Summary</option>
                        <option value="current_borrowings" <?php echo $report_type === 'current_borrowings' ? 'selected' : ''; ?>>Current Borrowings</option>
                        <option value="overdue_books" <?php echo $report_type === 'overdue_books' ? 'selected' : ''; ?>>Overdue Books</option>
                        <option value="popular_books" <?php echo $report_type === 'popular_books' ? 'selected' : ''; ?>>Popular Books</option>
                        <option value="user_activity" <?php echo $report_type === 'user_activity' ? 'selected' : ''; ?>>User Activity</option>
                        <option value="fines_summary" <?php echo $report_type === 'fines_summary' ? 'selected' : ''; ?>>Fines Summary</option>
                        <option value="collection_status" <?php echo $report_type === 'collection_status' ? 'selected' : ''; ?>>Collection Status</option>
                    </select>
                </div>
                
                <div class="form-group date-filter">
                    <label for="date_from">From Date</label>
                    <input type="date" id="date_from" name="date_from" value="<?php echo $date_from; ?>" class="form-control">
                </div>
                
                <div class="form-group date-filter">
                    <label for="date_to">To Date</label>
                    <input type="date" id="date_to" name="date_to" value="<?php echo $date_to; ?>" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="department">Department</label>
                    <select id="department" name="department" class="form-control">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept['department']); ?>" 
                                    <?php echo $department_filter === $dept['department'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['department']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="category">Category</label>
                    <select id="category" name="category" class="form-control">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['category_id']; ?>" 
                                    <?php echo $category_filter == $cat['category_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-chart-bar"></i> Generate Report
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Export Options -->
    <div class="export-options">
        <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" 
           class="btn btn-success">
            <i class="fas fa-file-csv"></i> Export CSV
        </a>
        <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'print'])); ?>" 
           class="btn btn-info" target="_blank">
            <i class="fas fa-print"></i> Print Report
        </a>
    </div>

    <!-- Report Content -->
    <div class="report-content">
        <?php if (isset($report_data['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($report_data['error']); ?>
            </div>
        <?php else: ?>
            <?php
            switch ($report_type) {
                case 'daily_summary':
                    include 'reports/daily_summary.php';
                    break;
                case 'current_borrowings':
                    include 'reports/current_borrowings.php';
                    break;
                case 'overdue_books':
                    include 'reports/overdue_books.php';
                    break;
                case 'popular_books':
                    include 'reports/popular_books.php';
                    break;
                case 'user_activity':
                    include 'reports/user_activity.php';
                    break;
                case 'fines_summary':
                    include 'reports/fines_summary.php';
                    break;
                case 'collection_status':
                    include 'reports/collection_status.php';
                    break;
                default:
                    include 'reports/daily_summary.php';
            }
            ?>
        <?php endif; ?>
    </div>
</div>

<style>
/* Reports Management Styles */
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

.report-controls {
    background: white;
    padding: 2rem;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.report-form .form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    align-items: end;
}

.export-options {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
    justify-content: flex-end;
}

.report-content {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    overflow: hidden;
}

.report-header {
    background: #f8f9fa;
    padding: 1.5rem;
    border-bottom: 1px solid #e9ecef;
}

.report-header h2 {
    margin: 0;
    color: #1e3c72;
}

.report-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    padding: 1.5rem;
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
}

.summary-card {
    background: white;
    padding: 1rem;
    border-radius: 8px;
    text-align: center;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.summary-card h3 {
    margin: 0 0 0.5rem 0;
    font-size: 2rem;
    color: #007bff;
}

.summary-card p {
    margin: 0;
    color: #6c757d;
    font-size: 0.9rem;
}

.report-table {
    width: 100%;
    border-collapse: collapse;
}

.report-table th,
.report-table td {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid #e9ecef;
}

.report-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #495057;
}

.report-table tbody tr:hover {
    background: #f8f9fa;
}

.status-overdue {
    color: #dc3545;
    font-weight: 600;
}

.status-due-soon {
    color: #ffc107;
    font-weight: 600;
}

.status-normal {
    color: #28a745;
    font-weight: 600;
}

.fine-amount {
    color: #dc3545;
    font-weight: 600;
}

@media (max-width: 768px) {
    .report-form .form-row {
        grid-template-columns: 1fr;
    }
    
    .export-options {
        flex-direction: column;
    }
    
    .report-summary {
        grid-template-columns: 1fr;
    }
    
    .page-header {
        flex-direction: column;
        gap: 1rem;
        align-items: stretch;
    }
}
</style>

<script>
function toggleDateFilters() {
    const reportType = document.getElementById('type').value;
    const dateFilters = document.querySelectorAll('.date-filter');
    
    // Hide date filters for reports that don't need them
    const noDateReports = ['current_borrowings', 'overdue_books', 'collection_status'];
    
    dateFilters.forEach(filter => {
        if (noDateReports.includes(reportType)) {
            filter.style.display = 'none';
        } else {
            filter.style.display = 'block';
        }
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleDateFilters();
});
</script>

<?php include '../includes/footer.php'; ?>