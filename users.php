<?php
/**
 * Admin User Management - Ethiopian Police University Library Management System
 * Handles user CRUD operations for students and staff
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

$page_title = 'User Management';
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
            case 'add_user':
                $id_number = sanitize_input($_POST['id_number'] ?? '');
                $full_name = sanitize_input($_POST['full_name'] ?? '');
                $department = sanitize_input($_POST['department'] ?? '');
                $role = sanitize_input($_POST['role'] ?? '');
                $email = sanitize_input($_POST['email'] ?? '');
                $phone = sanitize_input($_POST['phone'] ?? '');
                
                if (empty($id_number) || empty($full_name) || empty($department) || empty($role)) {
                    $error_message = 'Please fill in all required fields.';
                } elseif (!in_array($role, ['student', 'staff'])) {
                    $error_message = 'Invalid role selected.';
                } else {
                    try {
                        $sql = "INSERT INTO users (id_number, full_name, department, role, email, phone) 
                                VALUES (?, ?, ?, ?, ?, ?)";
                        execute_query($sql, [$id_number, $full_name, $department, $role, $email, $phone]);
                        $success_message = 'User added successfully!';
                        log_security_event("User registered: $id_number ($full_name)", $_SESSION['user_id']);
                    } catch (Exception $e) {
                        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                            $error_message = 'A user with this ID number already exists.';
                        } else {
                            $error_message = 'Error adding user. Please try again.';
                        }
                    }
                }
                break;
                
            case 'update_user':
                $user_id = (int)($_POST['user_id'] ?? 0);
                $id_number = sanitize_input($_POST['id_number'] ?? '');
                $full_name = sanitize_input($_POST['full_name'] ?? '');
                $department = sanitize_input($_POST['department'] ?? '');
                $role = sanitize_input($_POST['role'] ?? '');
                $email = sanitize_input($_POST['email'] ?? '');
                $phone = sanitize_input($_POST['phone'] ?? '');
                $status = sanitize_input($_POST['status'] ?? 'active');
                
                if ($user_id <= 0 || empty($full_name) || empty($department) || empty($role)) {
                    $error_message = 'Please fill in all required fields.';
                } elseif (!in_array($role, ['student', 'staff'])) {
                    $error_message = 'Invalid role selected.';
                } elseif (!in_array($status, ['active', 'inactive'])) {
                    $error_message = 'Invalid status selected.';
                } else {
                    try {
                        $sql = "UPDATE users SET id_number = ?, full_name = ?, department = ?, role = ?, 
                                email = ?, phone = ?, status = ? WHERE user_id = ?";
                        execute_query($sql, [$id_number, $full_name, $department, $role, $email, $phone, $status, $user_id]);
                        $success_message = 'User updated successfully!';
                        log_security_event("User updated: $id_number ($full_name)", $_SESSION['user_id']);
                    } catch (Exception $e) {
                        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                            $error_message = 'A user with this ID number already exists.';
                        } else {
                            $error_message = 'Error updating user. Please try again.';
                        }
                    }
                }
                break;
                
            case 'deactivate_user':
                $user_id = (int)($_POST['user_id'] ?? 0);
                
                if ($user_id <= 0) {
                    $error_message = 'Invalid user ID.';
                } else {
                    try {
                        // Check if user has active borrowings
                        $check_sql = "SELECT COUNT(*) as active_borrowings FROM borrow_records 
                                     WHERE user_id = ? AND return_date IS NULL";
                        $check_result = execute_query($check_sql, [$user_id]);
                        $active_borrowings = $check_result->fetch()['active_borrowings'];
                        
                        if ($active_borrowings > 0) {
                            $error_message = 'Cannot deactivate user. They have active book borrowings.';
                        } else {
                            // Get user info for logging
                            $user_sql = "SELECT id_number, full_name FROM users WHERE user_id = ?";
                            $user_result = execute_query($user_sql, [$user_id]);
                            $user_info = $user_result->fetch();
                            
                            $sql = "UPDATE users SET status = 'inactive' WHERE user_id = ?";
                            execute_query($sql, [$user_id]);
                            $success_message = 'User deactivated successfully!';
                            log_security_event("User deactivated: {$user_info['id_number']} ({$user_info['full_name']})", $_SESSION['user_id']);
                        }
                    } catch (Exception $e) {
                        $error_message = 'Error deactivating user. Please try again.';
                    }
                }
                break;
                
            case 'activate_user':
                $user_id = (int)($_POST['user_id'] ?? 0);
                
                if ($user_id <= 0) {
                    $error_message = 'Invalid user ID.';
                } else {
                    try {
                        // Get user info for logging
                        $user_sql = "SELECT id_number, full_name FROM users WHERE user_id = ?";
                        $user_result = execute_query($user_sql, [$user_id]);
                        $user_info = $user_result->fetch();
                        
                        $sql = "UPDATE users SET status = 'active' WHERE user_id = ?";
                        execute_query($sql, [$user_id]);
                        $success_message = 'User activated successfully!';
                        log_security_event("User activated: {$user_info['id_number']} ({$user_info['full_name']})", $_SESSION['user_id']);
                    } catch (Exception $e) {
                        $error_message = 'Error activating user. Please try again.';
                    }
                }
                break;
        }
    }
}

// Get search parameters
$search = sanitize_input($_GET['search'] ?? '');
$role_filter = sanitize_input($_GET['role'] ?? '');
$department_filter = sanitize_input($_GET['department'] ?? '');
$status_filter = sanitize_input($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Build search query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(full_name LIKE ? OR id_number LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($role_filter)) {
    $where_conditions[] = "role = ?";
    $params[] = $role_filter;
}

if (!empty($department_filter)) {
    $where_conditions[] = "department = ?";
    $params[] = $department_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM users $where_clause";
$count_result = execute_query($count_sql, $params);
$total_users = $count_result->fetch()['total'];
$total_pages = ceil($total_users / $per_page);

// Get users with pagination and borrowing stats
$sql = "SELECT u.*,
               (SELECT COUNT(*) FROM borrow_records br WHERE br.user_id = u.user_id AND br.return_date IS NULL) as active_borrowings,
               (SELECT COUNT(*) FROM borrow_records br WHERE br.user_id = u.user_id) as total_borrowings,
               (SELECT COALESCE(SUM(fine_amount), 0) FROM fines f WHERE f.user_id = u.user_id AND f.payment_status = 'unpaid') as unpaid_fines
        FROM users u 
        $where_clause
        ORDER BY u.full_name ASC 
        LIMIT $per_page OFFSET $offset";

$users_result = execute_query($sql, $params);
$users = $users_result->fetchAll();

// Get departments for filter dropdown
$departments_sql = "SELECT DISTINCT department FROM users ORDER BY department";
$departments_result = execute_query($departments_sql);
$departments = $departments_result->fetchAll();

include '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-users"></i> User Management</h1>
        <button type="button" class="btn btn-primary" onclick="showAddUserModal()">
            <i class="fas fa-user-plus"></i> Add New User
        </button>
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
                    <input type="text" name="search" placeholder="Search by name, ID number, or email..." 
                           value="<?php echo htmlspecialchars($search); ?>" class="form-control">
                </div>
                <div class="filter-group">
                    <select name="role" class="form-control">
                        <option value="">All Roles</option>
                        <option value="student" <?php echo $role_filter === 'student' ? 'selected' : ''; ?>>Student</option>
                        <option value="staff" <?php echo $role_filter === 'staff' ? 'selected' : ''; ?>>Staff</option>
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
                <div class="filter-group">
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="search-buttons">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <a href="users.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Users Table -->
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>ID Number</th>
                    <th>Full Name</th>
                    <th>Department</th>
                    <th>Role</th>
                    <th>Contact</th>
                    <th>Status</th>
                    <th>Borrowing Stats</th>
                    <th>Fines</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="9" class="text-center">
                            <i class="fas fa-users"></i>
                            No users found. <?php echo !empty($search) || !empty($role_filter) || !empty($department_filter) || !empty($status_filter) ? 'Try adjusting your search criteria.' : 'Add your first user to get started.'; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($user['id_number']); ?></strong>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($user['full_name']); ?>
                                <?php if ($user['active_borrowings'] > 0): ?>
                                    <br><small class="text-warning">
                                        <i class="fas fa-book"></i>
                                        <?php echo $user['active_borrowings']; ?> active borrowing(s)
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($user['department']); ?></td>
                            <td>
                                <span class="role-badge role-<?php echo $user['role']; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($user['email'])): ?>
                                    <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($user['phone'])): ?>
                                    <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['phone']); ?></div>
                                <?php endif; ?>
                                <?php if (empty($user['email']) && empty($user['phone'])): ?>
                                    <span class="text-muted">No contact info</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['status'] === 'active'): ?>
                                    <span class="status-badge status-active">Active</span>
                                <?php else: ?>
                                    <span class="status-badge status-inactive">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="stats-info">
                                    <div><strong><?php echo $user['active_borrowings']; ?></strong> Active</div>
                                    <div><strong><?php echo $user['total_borrowings']; ?></strong> Total</div>
                                </div>
                            </td>
                            <td>
                                <?php if ($user['unpaid_fines'] > 0): ?>
                                    <span class="fine-amount">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <?php echo number_format($user['unpaid_fines'], 2); ?> ETB
                                    </span>
                                <?php else: ?>
                                    <span class="text-success">
                                        <i class="fas fa-check"></i> Clear
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button type="button" class="btn btn-sm btn-secondary" 
                                            onclick="showEditUserModal(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <?php if ($user['status'] === 'active'): ?>
                                        <?php if ($user['active_borrowings'] == 0): ?>
                                            <button type="button" class="btn btn-sm btn-warning" 
                                                    onclick="confirmDeactivateUser(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['full_name']); ?>')">
                                                <i class="fas fa-user-times"></i> Deactivate
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-secondary" disabled 
                                                    title="Cannot deactivate - user has active borrowings">
                                                <i class="fas fa-lock"></i> Locked
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-success" 
                                                onclick="confirmActivateUser(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['full_name']); ?>')">
                                            <i class="fas fa-user-check"></i> Activate
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
                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_users); ?> 
                of <?php echo $total_users; ?> users
            </div>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&department=<?php echo urlencode($department_filter); ?>&status=<?php echo urlencode($status_filter); ?>" 
                       class="btn btn-sm btn-secondary">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&department=<?php echo urlencode($department_filter); ?>&status=<?php echo urlencode($status_filter); ?>" 
                       class="btn btn-sm <?php echo $i == $page ? 'btn-primary' : 'btn-secondary'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&department=<?php echo urlencode($department_filter); ?>&status=<?php echo urlencode($status_filter); ?>" 
                       class="btn btn-sm btn-secondary">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Add User Modal -->
<div id="addUserModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-user-plus"></i> Add New User</h2>
            <button type="button" class="close-btn" onclick="hideAddUserModal()">&times;</button>
        </div>
        <form method="POST" id="addUserForm">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="add_user">
            
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label for="add_id_number">ID Number *</label>
                        <input type="text" id="add_id_number" name="id_number" class="form-control" required 
                               placeholder="Enter ID number (e.g., STU001, STAFF001)">
                    </div>
                    <div class="form-group">
                        <label for="add_role">Role *</label>
                        <select id="add_role" name="role" class="form-control" required>
                            <option value="">Select Role</option>
                            <option value="student">Student</option>
                            <option value="staff">Staff</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="add_full_name">Full Name *</label>
                    <input type="text" id="add_full_name" name="full_name" class="form-control" required 
                           placeholder="Enter full name">
                </div>
                
                <div class="form-group">
                    <label for="add_department">Department *</label>
                    <select id="add_department" name="department" class="form-control" required>
                        <option value="">Select Department</option>
                        <option value="Computer Science">Computer Science</option>
                        <option value="Criminal Justice">Criminal Justice</option>
                        <option value="Law Enforcement">Law Enforcement</option>
                        <option value="Management">Management</option>
                        <option value="Psychology">Psychology</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="add_email">Email</label>
                        <input type="email" id="add_email" name="email" class="form-control" 
                               placeholder="Enter email address">
                    </div>
                    <div class="form-group">
                        <label for="add_phone">Phone</label>
                        <input type="tel" id="add_phone" name="phone" class="form-control" 
                               placeholder="Enter phone number">
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideAddUserModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Add User
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-user-edit"></i> Edit User</h2>
            <button type="button" class="close-btn" onclick="hideEditUserModal()">&times;</button>
        </div>
        <form method="POST" id="editUserForm">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="update_user">
            <input type="hidden" id="edit_user_id" name="user_id">
            
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_id_number">ID Number *</label>
                        <input type="text" id="edit_id_number" name="id_number" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_role">Role *</label>
                        <select id="edit_role" name="role" class="form-control" required>
                            <option value="">Select Role</option>
                            <option value="student">Student</option>
                            <option value="staff">Staff</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_full_name">Full Name *</label>
                    <input type="text" id="edit_full_name" name="full_name" class="form-control" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_department">Department *</label>
                        <select id="edit_department" name="department" class="form-control" required>
                            <option value="">Select Department</option>
                            <option value="Computer Science">Computer Science</option>
                            <option value="Criminal Justice">Criminal Justice</option>
                            <option value="Law Enforcement">Law Enforcement</option>
                            <option value="Management">Management</option>
                            <option value="Psychology">Psychology</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_status">Status *</label>
                        <select id="edit_status" name="status" class="form-control" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_email">Email</label>
                        <input type="email" id="edit_email" name="email" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="edit_phone">Phone</label>
                        <input type="tel" id="edit_phone" name="phone" class="form-control">
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideEditUserModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update User
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Deactivate User Modal -->
<div id="deactivateUserModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-user-times"></i> Deactivate User</h2>
            <button type="button" class="close-btn" onclick="hideDeactivateUserModal()">&times;</button>
        </div>
        <form method="POST" id="deactivateUserForm">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="deactivate_user">
            <input type="hidden" id="deactivate_user_id" name="user_id">
            
            <div class="modal-body">
                <p>Are you sure you want to deactivate this user?</p>
                <p><strong id="deactivate_user_name"></strong></p>
                <p class="text-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    Deactivated users will not be able to borrow books or access the system.
                </p>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideDeactivateUserModal()">Cancel</button>
                <button type="submit" class="btn btn-warning">
                    <i class="fas fa-user-times"></i> Deactivate User
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Activate User Modal -->
<div id="activateUserModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-user-check"></i> Activate User</h2>
            <button type="button" class="close-btn" onclick="hideActivateUserModal()">&times;</button>
        </div>
        <form method="POST" id="activateUserForm">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" value="activate_user">
            <input type="hidden" id="activate_user_id" name="user_id">
            
            <div class="modal-body">
                <p>Are you sure you want to activate this user?</p>
                <p><strong id="activate_user_name"></strong></p>
                <p class="text-success">
                    <i class="fas fa-check-circle"></i>
                    Activated users will be able to borrow books and access the system.
                </p>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideActivateUserModal()">Cancel</button>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-user-check"></i> Activate User
                </button>
            </div>
        </form>
    </div>
</div>

<style>
/* Additional styles for user management */
.role-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 600;
}

.role-student {
    background: #e3f2fd;
    color: #1565c0;
}

.role-staff {
    background: #f3e5f5;
    color: #7b1fa2;
}

.status-active {
    background: #d4edda;
    color: #155724;
}

.status-inactive {
    background: #f8d7da;
    color: #721c24;
}

.stats-info {
    font-size: 0.9rem;
}

.stats-info div {
    margin-bottom: 0.2rem;
}

.fine-amount {
    color: #dc3545;
    font-weight: 600;
}

.search-form .search-row {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr auto;
    gap: 1rem;
    align-items: end;
}

@media (max-width: 1024px) {
    .search-form .search-row {
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }
    
    .search-buttons {
        grid-column: 1 / -1;
        justify-self: start;
    }
}

@media (max-width: 768px) {
    .search-form .search-row {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        flex-direction: column;
        gap: 0.25rem;
    }
    
    .table-container {
        overflow-x: auto;
    }
}
</style>

<script>
// User management JavaScript functions
function showAddUserModal() {
    document.getElementById('addUserModal').style.display = 'flex';
    document.getElementById('add_id_number').focus();
}

function hideAddUserModal() {
    document.getElementById('addUserModal').style.display = 'none';
    document.getElementById('addUserForm').reset();
}

function showEditUserModal(user) {
    document.getElementById('edit_user_id').value = user.user_id;
    document.getElementById('edit_id_number').value = user.id_number;
    document.getElementById('edit_full_name').value = user.full_name;
    document.getElementById('edit_department').value = user.department;
    document.getElementById('edit_role').value = user.role;
    document.getElementById('edit_email').value = user.email || '';
    document.getElementById('edit_phone').value = user.phone || '';
    document.getElementById('edit_status').value = user.status;
    
    document.getElementById('editUserModal').style.display = 'flex';
    document.getElementById('edit_full_name').focus();
}

function hideEditUserModal() {
    document.getElementById('editUserModal').style.display = 'none';
    document.getElementById('editUserForm').reset();
}

function confirmDeactivateUser(userId, userName) {
    document.getElementById('deactivate_user_id').value = userId;
    document.getElementById('deactivate_user_name').textContent = userName;
    document.getElementById('deactivateUserModal').style.display = 'flex';
}

function hideDeactivateUserModal() {
    document.getElementById('deactivateUserModal').style.display = 'none';
}

function confirmActivateUser(userId, userName) {
    document.getElementById('activate_user_id').value = userId;
    document.getElementById('activate_user_name').textContent = userName;
    document.getElementById('activateUserModal').style.display = 'flex';
}

function hideActivateUserModal() {
    document.getElementById('activateUserModal').style.display = 'none';
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

// Auto-generate ID number based on role selection
document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.getElementById('add_role');
    const idNumberInput = document.getElementById('add_id_number');
    
    if (roleSelect && idNumberInput) {
        roleSelect.addEventListener('change', function() {
            const role = this.value;
            if (role === 'student') {
                idNumberInput.placeholder = 'e.g., STU001, STU002, etc.';
            } else if (role === 'staff') {
                idNumberInput.placeholder = 'e.g., STAFF001, STAFF002, etc.';
            } else {
                idNumberInput.placeholder = 'Enter ID number';
            }
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>