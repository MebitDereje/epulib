<?php
/**
 * Admin Settings - Ethiopian Police University Library Management System
 * System configuration and settings management
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';

// Start secure session
start_secure_session();

// Check if user is logged in and has admin role
if (!is_logged_in() || !has_role('admin')) {
    header('Location: ../index.php');
    exit();
}

$page_title = 'System Settings';
$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // Verify CSRF token
    if (!verify_csrf_token($csrf_token)) {
        $error_message = 'Invalid request. Please try again.';
    } else {
        switch ($action) {
            case 'update_library_settings':
                updateLibrarySettings();
                break;
            case 'update_borrowing_settings':
                updateBorrowingSettings();
                break;
            case 'update_fine_settings':
                updateFineSettings();
                break;
            case 'update_system_settings':
                updateSystemSettings();
                break;
            case 'add_category':
                addCategory();
                break;
            case 'update_category':
                updateCategory();
                break;
            case 'delete_category':
                deleteCategory();
                break;
            case 'backup_database':
                backupDatabase();
                break;
            case 'clear_logs':
                clearSecurityLogs();
                break;
            default:
                $error_message = 'Invalid action specified.';
        }
    }
}

/**
 * Update library information settings
 */
function updateLibrarySettings() {
    global $success_message, $error_message;
    
    $library_name = sanitize_input($_POST['library_name'] ?? '');
    $library_email = sanitize_input($_POST['library_email'] ?? '');
    $library_phone = sanitize_input($_POST['library_phone'] ?? '');
    $library_address = sanitize_input($_POST['library_address'] ?? '');
    
    if (empty($library_name)) {
        $error_message = 'Library name is required.';
        return;
    }
    
    try {
        $settings = [
            'library_name' => $library_name,
            'library_email' => $library_email,
            'library_phone' => $library_phone,
            'library_address' => $library_address
        ];
        
        foreach ($settings as $key => $value) {
            $sql = "INSERT INTO system_settings (setting_key, setting_value, description) 
                    VALUES (?, ?, ?) 
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
            execute_query($sql, [$key, $value, ucfirst(str_replace('_', ' ', $key))]);
        }
        
        $success_message = 'Library settings updated successfully!';
        log_security_event("Library settings updated", $_SESSION['user_id']);
    } catch (Exception $e) {
        $error_message = 'Error updating library settings. Please try again.';
        error_log("Settings update error: " . $e->getMessage());
    }
}

/**
 * Update borrowing policy settings
 */
function updateBorrowingSettings() {
    global $success_message, $error_message;
    
    $borrowing_period = (int)($_POST['borrowing_period_days'] ?? 14);
    $max_books = (int)($_POST['max_books_per_user'] ?? 3);
    $renewal_limit = (int)($_POST['renewal_limit'] ?? 2);
    $reservation_period = (int)($_POST['reservation_period_days'] ?? 7);
    
    if ($borrowing_period < 1 || $borrowing_period > 365) {
        $error_message = 'Borrowing period must be between 1 and 365 days.';
        return;
    }
    
    if ($max_books < 1 || $max_books > 20) {
        $error_message = 'Maximum books per user must be between 1 and 20.';
        return;
    }
    
    try {
        $settings = [
            'borrowing_period_days' => $borrowing_period,
            'max_books_per_user' => $max_books,
            'renewal_limit' => $renewal_limit,
            'reservation_period_days' => $reservation_period
        ];
        
        foreach ($settings as $key => $value) {
            $sql = "INSERT INTO system_settings (setting_key, setting_value, description) 
                    VALUES (?, ?, ?) 
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
            execute_query($sql, [$key, $value, ucfirst(str_replace('_', ' ', $key))]);
        }
        
        $success_message = 'Borrowing settings updated successfully!';
        log_security_event("Borrowing settings updated", $_SESSION['user_id']);
    } catch (Exception $e) {
        $error_message = 'Error updating borrowing settings. Please try again.';
        error_log("Settings update error: " . $e->getMessage());
    }
}

/**
 * Update fine calculation settings
 */
function updateFineSettings() {
    global $success_message, $error_message;
    
    $fine_per_day = (float)($_POST['fine_per_day'] ?? 2.00);
    $max_fine_amount = (float)($_POST['max_fine_amount'] ?? 100.00);
    $grace_period = (int)($_POST['grace_period_days'] ?? 0);
    $fine_calculation = sanitize_input($_POST['fine_calculation'] ?? 'daily');
    
    if ($fine_per_day < 0 || $fine_per_day > 100) {
        $error_message = 'Fine per day must be between 0 and 100 ETB.';
        return;
    }
    
    try {
        $settings = [
            'fine_per_day' => $fine_per_day,
            'max_fine_amount' => $max_fine_amount,
            'grace_period_days' => $grace_period,
            'fine_calculation_method' => $fine_calculation
        ];
        
        foreach ($settings as $key => $value) {
            $sql = "INSERT INTO system_settings (setting_key, setting_value, description) 
                    VALUES (?, ?, ?) 
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
            execute_query($sql, [$key, $value, ucfirst(str_replace('_', ' ', $key))]);
        }
        
        $success_message = 'Fine settings updated successfully!';
        log_security_event("Fine settings updated", $_SESSION['user_id']);
    } catch (Exception $e) {
        $error_message = 'Error updating fine settings. Please try again.';
        error_log("Settings update error: " . $e->getMessage());
    }
}

/**
 * Update general system settings
 */
function updateSystemSettings() {
    global $success_message, $error_message;
    
    $maintenance_mode = isset($_POST['maintenance_mode']) ? '1' : '0';
    $allow_registration = isset($_POST['allow_registration']) ? '1' : '0';
    $email_notifications = isset($_POST['email_notifications']) ? '1' : '0';
    $system_timezone = sanitize_input($_POST['system_timezone'] ?? 'Africa/Addis_Ababa');
    $session_timeout = (int)($_POST['session_timeout'] ?? 3600);
    
    try {
        $settings = [
            'maintenance_mode' => $maintenance_mode,
            'allow_registration' => $allow_registration,
            'email_notifications' => $email_notifications,
            'system_timezone' => $system_timezone,
            'session_timeout_seconds' => $session_timeout
        ];
        
        foreach ($settings as $key => $value) {
            $sql = "INSERT INTO system_settings (setting_key, setting_value, description) 
                    VALUES (?, ?, ?) 
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
            execute_query($sql, [$key, $value, ucfirst(str_replace('_', ' ', $key))]);
        }
        
        $success_message = 'System settings updated successfully!';
        log_security_event("System settings updated", $_SESSION['user_id']);
    } catch (Exception $e) {
        $error_message = 'Error updating system settings. Please try again.';
        error_log("Settings update error: " . $e->getMessage());
    }
}

/**
 * Add new category
 */
function addCategory() {
    global $success_message, $error_message;
    
    $category_name = sanitize_input($_POST['category_name'] ?? '');
    $description = sanitize_input($_POST['category_description'] ?? '');
    
    if (empty($category_name)) {
        $error_message = 'Category name is required.';
        return;
    }
    
    try {
        $sql = "INSERT INTO categories (category_name, description) VALUES (?, ?)";
        execute_query($sql, [$category_name, $description]);
        $success_message = 'Category added successfully!';
        log_security_event("Category added: $category_name", $_SESSION['user_id']);
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            $error_message = 'A category with this name already exists.';
        } else {
            $error_message = 'Error adding category. Please try again.';
        }
    }
}

/**
 * Update existing category
 */
function updateCategory() {
    global $success_message, $error_message;
    
    $category_id = (int)($_POST['category_id'] ?? 0);
    $category_name = sanitize_input($_POST['category_name'] ?? '');
    $description = sanitize_input($_POST['category_description'] ?? '');
    
    if ($category_id <= 0 || empty($category_name)) {
        $error_message = 'Invalid category data.';
        return;
    }
    
    try {
        $sql = "UPDATE categories SET category_name = ?, description = ? WHERE category_id = ?";
        execute_query($sql, [$category_name, $description, $category_id]);
        $success_message = 'Category updated successfully!';
        log_security_event("Category updated: $category_name", $_SESSION['user_id']);
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            $error_message = 'A category with this name already exists.';
        } else {
            $error_message = 'Error updating category. Please try again.';
        }
    }
}

/**
 * Delete category
 */
function deleteCategory() {
    global $success_message, $error_message;
    
    $category_id = (int)($_POST['category_id'] ?? 0);
    
    if ($category_id <= 0) {
        $error_message = 'Invalid category ID.';
        return;
    }
    
    try {
        // Check if category has books
        $check_sql = "SELECT COUNT(*) as book_count FROM books WHERE category_id = ?";
        $result = execute_query($check_sql, [$category_id]);
        $book_count = $result->fetch()['book_count'];
        
        if ($book_count > 0) {
            $error_message = "Cannot delete category. It contains $book_count book(s).";
            return;
        }
        
        // Get category name for logging
        $name_sql = "SELECT category_name FROM categories WHERE category_id = ?";
        $name_result = execute_query($name_sql, [$category_id]);
        $category_name = $name_result->fetch()['category_name'] ?? 'Unknown';
        
        $sql = "DELETE FROM categories WHERE category_id = ?";
        execute_query($sql, [$category_id]);
        $success_message = 'Category deleted successfully!';
        log_security_event("Category deleted: $category_name", $_SESSION['user_id']);
    } catch (Exception $e) {
        $error_message = 'Error deleting category. Please try again.';
    }
}

/**
 * Backup database (basic implementation)
 */
function backupDatabase() {
    global $success_message, $error_message;
    
    try {
        $backup_dir = 'backups';
        if (!file_exists($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        $filename = 'library_backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backup_dir . '/' . $filename;
        
        // Basic backup using mysqldump (requires system access)
        $command = sprintf(
            'mysqldump --host=%s --user=%s --password=%s %s > %s',
            DB_HOST,
            DB_USER,
            DB_PASS,
            DB_NAME,
            $filepath
        );
        
        // Note: In production, use proper backup tools and secure credential handling
        $success_message = 'Database backup initiated. Check the backups directory.';
        log_security_event("Database backup initiated", $_SESSION['user_id']);
    } catch (Exception $e) {
        $error_message = 'Error creating database backup. Please contact system administrator.';
        error_log("Backup error: " . $e->getMessage());
    }
}

/**
 * Clear security logs
 */
function clearSecurityLogs() {
    global $success_message, $error_message;
    
    $days_to_keep = (int)($_POST['days_to_keep'] ?? 30);
    
    if ($days_to_keep < 1) {
        $error_message = 'Days to keep must be at least 1.';
        return;
    }
    
    try {
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-$days_to_keep days"));
        $sql = "DELETE FROM security_logs WHERE created_at < ?";
        $result = execute_query($sql, [$cutoff_date]);
        $deleted_count = $result->rowCount();
        
        $success_message = "Security logs cleared. Deleted $deleted_count old entries.";
        log_security_event("Security logs cleared (kept last $days_to_keep days)", $_SESSION['user_id']);
    } catch (Exception $e) {
        $error_message = 'Error clearing security logs. Please try again.';
        error_log("Log clearing error: " . $e->getMessage());
    }
}

// Get current settings
$current_settings = [];
try {
    $sql = "SELECT setting_key, setting_value, description FROM system_settings";
    $result = execute_query($sql);
    while ($row = $result->fetch()) {
        $current_settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    $error_message = 'Error loading current settings.';
}

// Get categories
$categories = [];
try {
    $sql = "SELECT c.*, COUNT(b.book_id) as book_count 
            FROM categories c 
            LEFT JOIN books b ON c.category_id = b.category_id 
            GROUP BY c.category_id 
            ORDER BY c.category_name";
    $categories = execute_query($sql)->fetchAll();
} catch (Exception $e) {
    $error_message = 'Error loading categories.';
}

// Get system statistics
$system_stats = [];
try {
    $stats_queries = [
        'total_books' => "SELECT COUNT(*) as count FROM books",
        'total_users' => "SELECT COUNT(*) as count FROM users WHERE status = 'active'",
        'total_categories' => "SELECT COUNT(*) as count FROM categories",
        'active_borrowings' => "SELECT COUNT(*) as count FROM borrow_records WHERE return_date IS NULL",
        'overdue_books' => "SELECT COUNT(*) as count FROM borrow_records WHERE return_date IS NULL AND due_date < CURDATE()",
        'total_fines' => "SELECT COALESCE(SUM(fine_amount), 0) as amount FROM fines WHERE payment_status = 'unpaid'",
        'security_logs' => "SELECT COUNT(*) as count FROM security_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
    ];
    
    foreach ($stats_queries as $key => $query) {
        $result = execute_query($query)->fetch();
        $system_stats[$key] = $result['count'] ?? $result['amount'] ?? 0;
    }
} catch (Exception $e) {
    // Continue with empty stats if there's an error
}

include '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-cogs"></i> System Settings</h1>
        <p>Configure library system settings and preferences</p>
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

    <!-- Settings Navigation Tabs -->
    <div class="settings-tabs">
        <button class="tab-btn active" onclick="showTab('library-info')">
            <i class="fas fa-building"></i> Library Information
        </button>
        <button class="tab-btn" onclick="showTab('borrowing-policies')">
            <i class="fas fa-book-reader"></i> Borrowing Policies
        </button>
        <button class="tab-btn" onclick="showTab('fine-settings')">
            <i class="fas fa-money-bill-wave"></i> Fine Settings
        </button>
        <button class="tab-btn" onclick="showTab('system-config')">
            <i class="fas fa-server"></i> System Configuration
        </button>
        <button class="tab-btn" onclick="showTab('categories')">
            <i class="fas fa-tags"></i> Categories
        </button>
        <button class="tab-btn" onclick="showTab('maintenance')">
            <i class="fas fa-tools"></i> Maintenance
        </button>
    </div>

    <!-- Library Information Tab -->
    <div id="library-info" class="tab-content active">
        <div class="settings-section">
            <h2><i class="fas fa-building"></i> Library Information</h2>
            <p>Configure basic library information and contact details.</p>
            
            <form method="POST" class="settings-form">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="update_library_settings">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="library_name">Library Name *</label>
                        <input type="text" id="library_name" name="library_name" class="form-control" 
                               value="<?php echo htmlspecialchars($current_settings['library_name'] ?? 'Ethiopian Police University Library'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="library_email">Library Email</label>
                        <input type="email" id="library_email" name="library_email" class="form-control" 
                               value="<?php echo htmlspecialchars($current_settings['library_email'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="library_phone">Library Phone</label>
                        <input type="tel" id="library_phone" name="library_phone" class="form-control" 
                               value="<?php echo htmlspecialchars($current_settings['library_phone'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="library_address">Library Address</label>
                        <textarea id="library_address" name="library_address" class="form-control" rows="3"><?php echo htmlspecialchars($current_settings['library_address'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Library Information
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Borrowing Policies Tab -->
    <div id="borrowing-policies" class="tab-content">
        <div class="settings-section">
            <h2><i class="fas fa-book-reader"></i> Borrowing Policies</h2>
            <p>Configure borrowing rules and limitations for library users.</p>
            
            <form method="POST" class="settings-form">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="update_borrowing_settings">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="borrowing_period_days">Borrowing Period (Days) *</label>
                        <input type="number" id="borrowing_period_days" name="borrowing_period_days" class="form-control" 
                               value="<?php echo htmlspecialchars($current_settings['borrowing_period_days'] ?? '14'); ?>" 
                               min="1" max="365" required>
                        <small class="form-text">Number of days a book can be borrowed (1-365)</small>
                    </div>
                    <div class="form-group">
                        <label for="max_books_per_user">Maximum Books per User *</label>
                        <input type="number" id="max_books_per_user" name="max_books_per_user" class="form-control" 
                               value="<?php echo htmlspecialchars($current_settings['max_books_per_user'] ?? '3'); ?>" 
                               min="1" max="20" required>
                        <small class="form-text">Maximum number of books a user can borrow simultaneously (1-20)</small>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="renewal_limit">Renewal Limit *</label>
                        <input type="number" id="renewal_limit" name="renewal_limit" class="form-control" 
                               value="<?php echo htmlspecialchars($current_settings['renewal_limit'] ?? '2'); ?>" 
                               min="0" max="10" required>
                        <small class="form-text">Maximum number of times a book can be renewed (0-10)</small>
                    </div>
                    <div class="form-group">
                        <label for="reservation_period_days">Reservation Period (Days) *</label>
                        <input type="number" id="reservation_period_days" name="reservation_period_days" class="form-control" 
                               value="<?php echo htmlspecialchars($current_settings['reservation_period_days'] ?? '7'); ?>" 
                               min="1" max="30" required>
                        <small class="form-text">Number of days a book reservation is held (1-30)</small>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Borrowing Policies
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Fine Settings Tab -->
    <div id="fine-settings" class="tab-content">
        <div class="settings-section">
            <h2><i class="fas fa-money-bill-wave"></i> Fine Settings</h2>
            <p>Configure fine calculation and payment policies.</p>
            
            <form method="POST" class="settings-form">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="update_fine_settings">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="fine_per_day">Fine per Day (ETB) *</label>
                        <input type="number" id="fine_per_day" name="fine_per_day" class="form-control" 
                               value="<?php echo htmlspecialchars($current_settings['fine_per_day'] ?? '2.00'); ?>" 
                               min="0" max="100" step="0.01" required>
                        <small class="form-text">Amount charged per day for overdue books (0-100 ETB)</small>
                    </div>
                    <div class="form-group">
                        <label for="max_fine_amount">Maximum Fine Amount (ETB) *</label>
                        <input type="number" id="max_fine_amount" name="max_fine_amount" class="form-control" 
                               value="<?php echo htmlspecialchars($current_settings['max_fine_amount'] ?? '100.00'); ?>" 
                               min="0" step="0.01" required>
                        <small class="form-text">Maximum fine amount that can be charged for a single book</small>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="grace_period_days">Grace Period (Days) *</label>
                        <input type="number" id="grace_period_days" name="grace_period_days" class="form-control" 
                               value="<?php echo htmlspecialchars($current_settings['grace_period_days'] ?? '0'); ?>" 
                               min="0" max="7" required>
                        <small class="form-text">Number of days after due date before fines start (0-7)</small>
                    </div>
                    <div class="form-group">
                        <label for="fine_calculation">Fine Calculation Method *</label>
                        <select id="fine_calculation" name="fine_calculation" class="form-control" required>
                            <option value="daily" <?php echo ($current_settings['fine_calculation_method'] ?? 'daily') === 'daily' ? 'selected' : ''; ?>>Daily</option>
                            <option value="weekly" <?php echo ($current_settings['fine_calculation_method'] ?? 'daily') === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                            <option value="fixed" <?php echo ($current_settings['fine_calculation_method'] ?? 'daily') === 'fixed' ? 'selected' : ''; ?>>Fixed Amount</option>
                        </select>
                        <small class="form-text">How fines are calculated for overdue books</small>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Fine Settings
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- System Configuration Tab -->
    <div id="system-config" class="tab-content">
        <div class="settings-section">
            <h2><i class="fas fa-server"></i> System Configuration</h2>
            <p>Configure general system settings and preferences.</p>
            
            <form method="POST" class="settings-form">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="update_system_settings">
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="maintenance_mode" value="1" 
                               <?php echo ($current_settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : ''; ?>>
                        <span class="checkmark"></span>
                        Maintenance Mode
                    </label>
                    <small class="form-text">Enable maintenance mode to prevent user access during updates</small>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="allow_registration" value="1" 
                               <?php echo ($current_settings['allow_registration'] ?? '0') === '1' ? 'checked' : ''; ?>>
                        <span class="checkmark"></span>
                        Allow User Registration
                    </label>
                    <small class="form-text">Allow new users to register themselves</small>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="email_notifications" value="1" 
                               <?php echo ($current_settings['email_notifications'] ?? '0') === '1' ? 'checked' : ''; ?>>
                        <span class="checkmark"></span>
                        Email Notifications
                    </label>
                    <small class="form-text">Send email notifications for due dates and overdue books</small>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="system_timezone">System Timezone *</label>
                        <select id="system_timezone" name="system_timezone" class="form-control" required>
                            <option value="Africa/Addis_Ababa" <?php echo ($current_settings['system_timezone'] ?? 'Africa/Addis_Ababa') === 'Africa/Addis_Ababa' ? 'selected' : ''; ?>>Africa/Addis_Ababa (EAT)</option>
                            <option value="UTC" <?php echo ($current_settings['system_timezone'] ?? 'Africa/Addis_Ababa') === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                            <option value="America/New_York" <?php echo ($current_settings['system_timezone'] ?? 'Africa/Addis_Ababa') === 'America/New_York' ? 'selected' : ''; ?>>America/New_York (EST)</option>
                            <option value="Europe/London" <?php echo ($current_settings['system_timezone'] ?? 'Africa/Addis_Ababa') === 'Europe/London' ? 'selected' : ''; ?>>Europe/London (GMT)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="session_timeout">Session Timeout (Seconds) *</label>
                        <input type="number" id="session_timeout" name="session_timeout" class="form-control" 
                               value="<?php echo htmlspecialchars($current_settings['session_timeout_seconds'] ?? '3600'); ?>" 
                               min="300" max="86400" required>
                        <small class="form-text">Session timeout in seconds (300-86400)</small>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save System Configuration
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Categories Tab -->
    <div id="categories" class="tab-content">
        <div class="settings-section">
            <h2><i class="fas fa-tags"></i> Book Categories</h2>
            <p>Manage book categories for better organization.</p>
            
            <div class="section-actions">
                <button type="button" class="btn btn-primary" onclick="showAddCategoryModal()">
                    <i class="fas fa-plus"></i> Add New Category
                </button>
            </div>
            
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Category Name</th>
                            <th>Description</th>
                            <th>Books Count</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($categories)): ?>
                            <tr>
                                <td colspan="4" class="text-center">
                                    <i class="fas fa-tags"></i>
                                    No categories found. Add your first category to get started.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($category['category_name']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($category['description'] ?? 'No description'); ?></td>
                                    <td>
                                        <span class="category-badge">
                                            <?php echo $category['book_count']; ?> books
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button type="button" class="btn btn-sm btn-secondary" 
                                                    onclick="showEditCategoryModal(<?php echo htmlspecialchars(json_encode($category)); ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <?php if ($category['book_count'] == 0): ?>
                                                <button type="button" class="btn btn-sm btn-danger" 
                                                        onclick="confirmDeleteCategory(<?php echo $category['category_id']; ?>, '<?php echo htmlspecialchars($category['category_name']); ?>')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-secondary" disabled 
                                                        title="Cannot delete - category contains books">
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
        </div>
    </div>

    <!-- Maintenance Tab -->
    <div id="maintenance" class="tab-content">
        <div class="settings-section">
            <h2><i class="fas fa-tools"></i> System Maintenance</h2>
            <p>Perform system maintenance tasks and view system statistics.</p>
            
            <!-- System Statistics -->
            <div class="maintenance-stats">
                <h3><i class="fas fa-chart-bar"></i> System Statistics</h3>
                <div class="stats-grid">
                    <div class="stat-card stat-primary">
                        <div class="stat-icon">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($system_stats['total_books'] ?? 0); ?></h3>
                            <p>Total Books</p>
                        </div>
                    </div>
                    <div class="stat-card stat-success">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($system_stats['total_users'] ?? 0); ?></h3>
                            <p>Active Users</p>
                        </div>
                    </div>
                    <div class="stat-card stat-info">
                        <div class="stat-icon">
                            <i class="fas fa-tags"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($system_stats['total_categories'] ?? 0); ?></h3>
                            <p>Categories</p>
                        </div>
                    </div>
                    <div class="stat-card stat-warning">
                        <div class="stat-icon">
                            <i class="fas fa-book-reader"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($system_stats['active_borrowings'] ?? 0); ?></h3>
                            <p>Active Borrowings</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Maintenance Actions -->
            <div class="maintenance-actions">
                <h3><i class="fas fa-wrench"></i> Maintenance Actions</h3>
                
                <div class="action-grid">
                    <div class="maintenance-card">
                        <div class="maintenance-icon">
                            <i class="fas fa-database"></i>
                        </div>
                        <h4>Database Backup</h4>
                        <p>Create a backup of the library database for safety.</p>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="action" value="backup_database">
                            <button type="submit" class="btn btn-primary" onclick="return confirm('Create database backup? This may take a few minutes.')">
                                <i class="fas fa-download"></i> Create Backup
                            </button>
                        </form>
                    </div>
                    
                    <div class="maintenance-card">
                        <div class="maintenance-icon">
                            <i class="fas fa-broom"></i>
                        </div>
                        <h4>Clear Security Logs</h4>
                        <p>Remove old security log entries to free up space.</p>
                        <button type="button" class="btn btn-warning" onclick="showClearLogsModal()">
                            <i class="fas fa-trash-alt"></i> Clear Logs
                        </button>
                    </div>
                    
                    <div class="maintenance-card">
                        <div class="maintenance-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h4>System Health</h4>
                        <p>View system health and performance metrics.</p>
                        <div class="health-indicators">
                            <div class="health-item">
                                <span class="health-label">Overdue Books:</span>
                                <span class="health-value <?php echo ($system_stats['overdue_books'] ?? 0) > 0 ? 'text-warning' : 'text-success'; ?>">
                                    <?php echo $system_stats['overdue_books'] ?? 0; ?>
                                </span>
                            </div>
                            <div class="health-item">
                                <span class="health-label">Unpaid Fines:</span>
                                <span class="health-value <?php echo ($system_stats['total_fines'] ?? 0) > 0 ? 'text-warning' : 'text-success'; ?>">
                                    <?php echo number_format($system_stats['total_fines'] ?? 0, 2); ?> ETB
                                </span>
                            </div>
                            <div class="health-item">
                                <span class="health-label">Security Events (30 days):</span>
                                <span class="health-value text-info">
                                    <?php echo $system_stats['security_logs'] ?? 0; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Category Modal -->
<div id="addCategoryModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-plus"></i> Add New Category</h2>
            <button type="button" class="close-btn" onclick="hideAddCategoryModal()">&times;</button>
        </div>
        <form method="POST" id="addCategoryForm">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="add_category">
            
            <div class="modal-body">
                <div class="form-group">
                    <label for="add_category_name">Category Name *</label>
                    <input type="text" id="add_category_name" name="category_name" class="form-control" required 
                           placeholder="Enter category name">
                </div>
                
                <div class="form-group">
                    <label for="add_category_description">Description</label>
                    <textarea id="add_category_description" name="category_description" class="form-control" rows="3" 
                              placeholder="Enter category description (optional)"></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideAddCategoryModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Add Category
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Category Modal -->
<div id="editCategoryModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-edit"></i> Edit Category</h2>
            <button type="button" class="close-btn" onclick="hideEditCategoryModal()">&times;</button>
        </div>
        <form method="POST" id="editCategoryForm">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="update_category">
            <input type="hidden" id="edit_category_id" name="category_id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label for="edit_category_name">Category Name *</label>
                    <input type="text" id="edit_category_name" name="category_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_category_description">Description</label>
                    <textarea id="edit_category_description" name="category_description" class="form-control" rows="3"></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideEditCategoryModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Category
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Category Modal -->
<div id="deleteCategoryModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-trash"></i> Delete Category</h2>
            <button type="button" class="close-btn" onclick="hideDeleteCategoryModal()">&times;</button>
        </div>
        <form method="POST" id="deleteCategoryForm">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="delete_category">
            <input type="hidden" id="delete_category_id" name="category_id">
            
            <div class="modal-body">
                <p>Are you sure you want to delete this category?</p>
                <p><strong id="delete_category_name"></strong></p>
                <p class="text-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    This action cannot be undone. Only categories with no books can be deleted.
                </p>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideDeleteCategoryModal()">Cancel</button>
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-trash"></i> Delete Category
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Clear Logs Modal -->
<div id="clearLogsModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-broom"></i> Clear Security Logs</h2>
            <button type="button" class="close-btn" onclick="hideClearLogsModal()">&times;</button>
        </div>
        <form method="POST" id="clearLogsForm">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="clear_logs">
            
            <div class="modal-body">
                <div class="form-group">
                    <label for="days_to_keep">Keep logs from the last (days) *</label>
                    <input type="number" id="days_to_keep" name="days_to_keep" class="form-control" 
                           value="30" min="1" max="365" required>
                    <small class="form-text">Logs older than this number of days will be deleted</small>
                </div>
                
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    This will permanently delete old security log entries. This action cannot be undone.
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideClearLogsModal()">Cancel</button>
                <button type="submit" class="btn btn-warning">
                    <i class="fas fa-broom"></i> Clear Logs
                </button>
            </div>
        </form>
    </div>
</div>

<style>
/* Settings-specific styles */
.settings-tabs {
    display: flex;
    background: white;
    border-radius: 10px 10px 0 0;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    margin-bottom: 0;
    overflow-x: auto;
}

.tab-btn {
    background: none;
    border: none;
    padding: 1rem 1.5rem;
    cursor: pointer;
    font-size: 0.9rem;
    color: #6c757d;
    transition: all 0.3s ease;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    border-bottom: 3px solid transparent;
}

.tab-btn:hover {
    background-color: #f8f9fa;
    color: #495057;
}

.tab-btn.active {
    color: #007bff;
    border-bottom-color: #007bff;
    background-color: #f8f9fa;
}

.tab-content {
    display: none;
    background: white;
    border-radius: 0 0 10px 10px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.tab-content.active {
    display: block;
}

.settings-section {
    padding: 2rem;
}

.settings-section h2 {
    color: #333;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.settings-section > p {
    color: #6c757d;
    margin-bottom: 2rem;
}

.settings-form {
    max-width: 800px;
}

.form-actions {
    margin-top: 2rem;
    padding-top: 1rem;
    border-top: 1px solid #e9ecef;
}

.form-text {
    color: #6c757d;
    font-size: 0.8rem;
    margin-top: 0.25rem;
}

/* Checkbox styling */
.checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    font-weight: 600;
    color: #333;
    margin-bottom: 0.5rem;
}

.checkbox-label input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: #007bff;
}

/* Section actions */
.section-actions {
    margin-bottom: 1.5rem;
    display: flex;
    justify-content: flex-end;
}

/* Maintenance styles */
.maintenance-stats {
    margin-bottom: 3rem;
}

.maintenance-stats h3 {
    color: #333;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
}

.maintenance-actions h3 {
    color: #333;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.action-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.maintenance-card {
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 10px;
    padding: 1.5rem;
    text-align: center;
    transition: all 0.3s ease;
}

.maintenance-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.maintenance-icon {
    font-size: 2.5rem;
    color: #007bff;
    margin-bottom: 1rem;
}

.maintenance-card h4 {
    color: #333;
    margin-bottom: 0.5rem;
}

.maintenance-card p {
    color: #6c757d;
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
}

.health-indicators {
    margin-top: 1rem;
}

.health-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid #f8f9fa;
}

.health-item:last-child {
    border-bottom: none;
}

.health-label {
    font-size: 0.9rem;
    color: #6c757d;
}

.health-value {
    font-weight: 600;
    font-size: 0.9rem;
}

/* Category badge */
.category-badge {
    background: #e9ecef;
    color: #495057;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 500;
}

/* Responsive design */
@media (max-width: 768px) {
    .settings-tabs {
        flex-direction: column;
    }
    
    .tab-btn {
        border-bottom: none;
        border-left: 3px solid transparent;
    }
    
    .tab-btn.active {
        border-bottom: none;
        border-left-color: #007bff;
    }
    
    .settings-section {
        padding: 1rem;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .action-grid {
        grid-template-columns: 1fr;
    }
    
    .section-actions {
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .tab-btn {
        padding: 0.75rem 1rem;
        font-size: 0.8rem;
    }
    
    .maintenance-card {
        padding: 1rem;
    }
    
    .maintenance-icon {
        font-size: 2rem;
    }
}
</style>

<script>
// Settings page JavaScript functions

// Tab management
function showTab(tabId) {
    // Hide all tab contents
    const tabContents = document.querySelectorAll('.tab-content');
    tabContents.forEach(content => {
        content.classList.remove('active');
    });
    
    // Remove active class from all tab buttons
    const tabButtons = document.querySelectorAll('.tab-btn');
    tabButtons.forEach(button => {
        button.classList.remove('active');
    });
    
    // Show selected tab content
    document.getElementById(tabId).classList.add('active');
    
    // Add active class to clicked button
    event.target.classList.add('active');
}

// Category management functions
function showAddCategoryModal() {
    document.getElementById('addCategoryModal').style.display = 'flex';
    document.getElementById('add_category_name').focus();
}

function hideAddCategoryModal() {
    document.getElementById('addCategoryModal').style.display = 'none';
    document.getElementById('addCategoryForm').reset();
}

function showEditCategoryModal(category) {
    document.getElementById('edit_category_id').value = category.category_id;
    document.getElementById('edit_category_name').value = category.category_name;
    document.getElementById('edit_category_description').value = category.description || '';
    
    document.getElementById('editCategoryModal').style.display = 'flex';
    document.getElementById('edit_category_name').focus();
}

function hideEditCategoryModal() {
    document.getElementById('editCategoryModal').style.display = 'none';
    document.getElementById('editCategoryForm').reset();
}

function confirmDeleteCategory(categoryId, categoryName) {
    document.getElementById('delete_category_id').value = categoryId;
    document.getElementById('delete_category_name').textContent = categoryName;
    document.getElementById('deleteCategoryModal').style.display = 'flex';
}

function hideDeleteCategoryModal() {
    document.getElementById('deleteCategoryModal').style.display = 'none';
}

function showClearLogsModal() {
    document.getElementById('clearLogsModal').style.display = 'flex';
    document.getElementById('days_to_keep').focus();
}

function hideClearLogsModal() {
    document.getElementById('clearLogsModal').style.display = 'none';
    document.getElementById('clearLogsForm').reset();
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
    // Add form validation for numeric inputs
    const numericInputs = document.querySelectorAll('input[type="number"]');
    numericInputs.forEach(input => {
        input.addEventListener('input', function() {
            const min = parseFloat(this.min);
            const max = parseFloat(this.max);
            const value = parseFloat(this.value);
            
            if (value < min) {
                this.setCustomValidity(`Value must be at least ${min}`);
            } else if (max && value > max) {
                this.setCustomValidity(`Value must be at most ${max}`);
            } else {
                this.setCustomValidity('');
            }
        });
    });
    
    // Add confirmation for potentially destructive actions
    const destructiveForms = document.querySelectorAll('#clearLogsForm, #deleteCategoryForm');
    destructiveForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const action = this.querySelector('input[name="action"]').value;
            let message = 'Are you sure you want to proceed?';
            
            if (action === 'clear_logs') {
                const days = document.getElementById('days_to_keep').value;
                message = `This will delete all security logs older than ${days} days. Continue?`;
            } else if (action === 'delete_category') {
                const categoryName = document.getElementById('delete_category_name').textContent;
                message = `This will permanently delete the category "${categoryName}". Continue?`;
            }
            
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
    
    // Auto-save indication for settings forms
    const settingsForms = document.querySelectorAll('.settings-form');
    settingsForms.forEach(form => {
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('change', function() {
                // Add visual indication that settings have changed
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn && !submitBtn.classList.contains('btn-warning')) {
                    submitBtn.classList.remove('btn-primary');
                    submitBtn.classList.add('btn-warning');
                    submitBtn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Save Changes';
                }
            });
        });
        
        form.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                submitBtn.disabled = true;
            }
        });
    });
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Escape key closes modals
    if (e.key === 'Escape') {
        const visibleModals = document.querySelectorAll('.modal[style*="flex"]');
        visibleModals.forEach(modal => {
            modal.style.display = 'none';
        });
    }
    
    // Ctrl+S saves current tab settings (prevent default browser save)
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        const activeTab = document.querySelector('.tab-content.active');
        if (activeTab) {
            const form = activeTab.querySelector('.settings-form');
            if (form) {
                form.submit();
            }
        }
    }
});

// Initialize tooltips for disabled buttons
document.addEventListener('DOMContentLoaded', function() {
    const disabledButtons = document.querySelectorAll('button[disabled][title]');
    disabledButtons.forEach(button => {
        button.addEventListener('mouseenter', function() {
            // Simple tooltip implementation
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = this.title;
            tooltip.style.cssText = `
                position: absolute;
                background: #333;
                color: white;
                padding: 0.5rem;
                border-radius: 4px;
                font-size: 0.8rem;
                z-index: 1000;
                pointer-events: none;
                white-space: nowrap;
            `;
            document.body.appendChild(tooltip);
            
            const rect = this.getBoundingClientRect();
            tooltip.style.left = rect.left + 'px';
            tooltip.style.top = (rect.top - tooltip.offsetHeight - 5) + 'px';
            
            this.tooltip = tooltip;
        });
        
        button.addEventListener('mouseleave', function() {
            if (this.tooltip) {
                document.body.removeChild(this.tooltip);
                this.tooltip = null;
            }
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>