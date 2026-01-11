<?php
/**
 * Librarian Profile Management - Ethiopian Police University Library Management System
 * Allows librarians to view and update their profile information
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

$page_title = 'My Profile';
$success_message = '';
$error_message = '';

// Get current librarian information
$librarian_id = $_SESSION['user_id'];
$librarian_info = [];

try {
    $sql = "SELECT * FROM admins WHERE admin_id = ? AND role = 'librarian'";
    $result = execute_query($sql, [$librarian_id]);
    $librarian_info = $result->fetch();
    
    if (!$librarian_info) {
        $error_message = 'Profile information not found.';
    }
} catch (Exception $e) {
    $error_message = 'Error loading profile information.';
    error_log("Profile load error: " . $e->getMessage());
}

// Get librarian statistics
$librarian_stats = [];
try {
    $stats_queries = [
        'books_managed' => "SELECT COUNT(*) as count FROM books",
        'active_borrowings' => "SELECT COUNT(*) as count FROM borrow_records WHERE return_date IS NULL",
        'overdue_books' => "SELECT COUNT(*) as count FROM borrow_records WHERE return_date IS NULL AND due_date < CURDATE()",
        'total_users' => "SELECT COUNT(*) as count FROM users WHERE status = 'active'",
        'recent_activities' => "SELECT COUNT(*) as count FROM security_logs WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
    ];
    
    foreach ($stats_queries as $key => $query) {
        $params = ($key === 'recent_activities') ? [$librarian_id] : [];
        $result = execute_query($query, $params)->fetch();
        $librarian_stats[$key] = $result['count'] ?? 0;
    }
} catch (Exception $e) {
    // Continue with empty stats if there's an error
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
            case 'update_profile':
                updateProfile();
                break;
            case 'change_password':
                changePassword();
                break;
            default:
                $error_message = 'Invalid action specified.';
        }
    }
}

/**
 * Update profile information
 */
function updateProfile() {
    global $success_message, $error_message, $librarian_id, $librarian_info;
    
    $full_name = sanitize_input($_POST['full_name'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $phone = sanitize_input($_POST['phone'] ?? '');
    
    if (empty($full_name)) {
        $error_message = 'Full name is required.';
        return;
    }
    
    // Validate email format if provided
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
        return;
    }
    
    // Validate phone format if provided
    if (!empty($phone) && !preg_match('/^[\+]?[0-9\-\s\(\)]+$/', $phone)) {
        $error_message = 'Please enter a valid phone number.';
        return;
    }
    
    try {
        $sql = "UPDATE admins SET full_name = ?, email = ?, phone = ? WHERE admin_id = ?";
        execute_query($sql, [$full_name, $email, $phone, $librarian_id]);
        
        // Update session data
        $_SESSION['full_name'] = $full_name;
        
        // Reload librarian info
        $info_sql = "SELECT * FROM admins WHERE admin_id = ?";
        $info_result = execute_query($info_sql, [$librarian_id]);
        $librarian_info = $info_result->fetch();
        
        $success_message = 'Profile updated successfully!';
        log_security_event("Profile updated", $librarian_id);
    } catch (Exception $e) {
        $error_message = 'Error updating profile. Please try again.';
        error_log("Profile update error: " . $e->getMessage());
    }
}

/**
 * Change password
 */
function changePassword() {
    global $success_message, $error_message, $librarian_id;
    
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = 'All password fields are required.';
        return;
    }
    
    if ($new_password !== $confirm_password) {
        $error_message = 'New passwords do not match.';
        return;
    }
    
    if (strlen($new_password) < 8) {
        $error_message = 'New password must be at least 8 characters long.';
        return;
    }
    
    try {
        // Verify current password
        $sql = "SELECT password_hash FROM admins WHERE admin_id = ?";
        $result = execute_query($sql, [$librarian_id]);
        $user = $result->fetch();
        
        if (!$user || !password_verify($current_password, $user['password_hash'])) {
            $error_message = 'Current password is incorrect.';
            return;
        }
        
        // Update password
        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $update_sql = "UPDATE admins SET password_hash = ? WHERE admin_id = ?";
        execute_query($update_sql, [$new_password_hash, $librarian_id]);
        
        $success_message = 'Password changed successfully!';
        log_security_event("Password changed", $librarian_id);
    } catch (Exception $e) {
        $error_message = 'Error changing password. Please try again.';
        error_log("Password change error: " . $e->getMessage());
    }
}

include '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-user"></i> My Profile</h1>
        <p>Manage your profile information and account settings</p>
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

    <div class="profile-layout">
        <!-- Profile Information Card -->
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($librarian_info['full_name'] ?? 'Unknown'); ?></h2>
                    <p class="profile-role">
                        <i class="fas fa-id-badge"></i>
                        <?php echo ucfirst($librarian_info['role'] ?? 'librarian'); ?>
                    </p>
                    <p class="profile-username">
                        <i class="fas fa-at"></i>
                        <?php echo htmlspecialchars($librarian_info['username'] ?? ''); ?>
                    </p>
                    <p class="profile-member-since">
                        <i class="fas fa-calendar-alt"></i>
                        Member since <?php echo date('F Y', strtotime($librarian_info['created_at'] ?? 'now')); ?>
                    </p>
                    <?php if (!empty($librarian_info['last_login'])): ?>
                        <p class="profile-last-login">
                            <i class="fas fa-clock"></i>
                            Last login: <?php echo date('M j, Y g:i A', strtotime($librarian_info['last_login'])); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Profile Statistics -->
            <div class="profile-stats">
                <h3><i class="fas fa-chart-bar"></i> My Statistics</h3>
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="stat-content">
                            <h4><?php echo number_format($librarian_stats['books_managed'] ?? 0); ?></h4>
                            <p>Books in Library</p>
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="fas fa-book-reader"></i>
                        </div>
                        <div class="stat-content">
                            <h4><?php echo number_format($librarian_stats['active_borrowings'] ?? 0); ?></h4>
                            <p>Active Borrowings</p>
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-content">
                            <h4><?php echo number_format($librarian_stats['overdue_books'] ?? 0); ?></h4>
                            <p>Overdue Books</p>
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <h4><?php echo number_format($librarian_stats['total_users'] ?? 0); ?></h4>
                            <p>Active Users</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profile Settings -->
        <div class="profile-settings">
            <!-- Profile Information Form -->
            <div class="settings-section">
                <h3><i class="fas fa-user-edit"></i> Profile Information</h3>
                <form method="POST" class="profile-form">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="form-group">
                        <label for="full_name">Full Name *</label>
                        <input type="text" id="full_name" name="full_name" class="form-control" 
                               value="<?php echo htmlspecialchars($librarian_info['full_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" class="form-control" 
                               value="<?php echo htmlspecialchars($librarian_info['username'] ?? ''); ?>" 
                               disabled readonly>
                        <small class="form-text">Username cannot be changed</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               value="<?php echo htmlspecialchars($librarian_info['email'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" class="form-control" 
                               value="<?php echo htmlspecialchars($librarian_info['phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </div>
                </form>
            </div>

            <!-- Change Password Form -->
            <div class="settings-section">
                <h3><i class="fas fa-lock"></i> Change Password</h3>
                <form method="POST" class="password-form">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-group">
                        <label for="current_password">Current Password *</label>
                        <input type="password" id="current_password" name="current_password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">New Password *</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" 
                               minlength="8" required>
                        <small class="form-text">Password must be at least 8 characters long</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password *</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                               minlength="8" required>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </div>
                </form>
            </div>

            <!-- Account Information -->
            <div class="settings-section">
                <h3><i class="fas fa-info-circle"></i> Account Information</h3>
                <div class="account-info">
                    <div class="info-item">
                        <label>Account Status:</label>
                        <span class="status-badge status-<?php echo $librarian_info['status'] ?? 'inactive'; ?>">
                            <?php echo ucfirst($librarian_info['status'] ?? 'inactive'); ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <label>Account Created:</label>
                        <span><?php echo date('F j, Y g:i A', strtotime($librarian_info['created_at'] ?? 'now')); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Last Updated:</label>
                        <span><?php echo date('F j, Y g:i A', strtotime($librarian_info['updated_at'] ?? 'now')); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Recent Activities (30 days):</label>
                        <span><?php echo number_format($librarian_stats['recent_activities'] ?? 0); ?> events</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Profile page specific styles */
.profile-layout {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 2rem;
    margin-top: 2rem;
}

.profile-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    overflow: hidden;
}

.profile-header {
    background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
    color: white;
    padding: 2rem;
    text-align: center;
}

.profile-avatar {
    margin-bottom: 1rem;
}

.profile-avatar i {
    font-size: 4rem;
    opacity: 0.9;
}

.profile-info h2 {
    margin-bottom: 0.5rem;
    font-size: 1.5rem;
}

.profile-info p {
    margin-bottom: 0.5rem;
    opacity: 0.9;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.profile-stats {
    padding: 2rem;
}

.profile-stats h3 {
    color: #333;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 8px;
    transition: transform 0.3s ease;
}

.stat-item:hover {
    transform: translateY(-2px);
}

.stat-icon {
    font-size: 1.5rem;
    color: #007bff;
}

.stat-content h4 {
    font-size: 1.2rem;
    font-weight: 700;
    color: #333;
    margin-bottom: 0.25rem;
}

.stat-content p {
    font-size: 0.8rem;
    color: #6c757d;
    margin: 0;
}

.profile-settings {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.settings-section {
    background: white;
    border-radius: 10px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    padding: 2rem;
}

.settings-section h3 {
    color: #333;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #e9ecef;
}

.profile-form,
.password-form {
    max-width: 500px;
}

.form-actions {
    margin-top: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid #e9ecef;
}

.account-info {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background: #f8f9fa;
    border-radius: 5px;
}

.info-item label {
    font-weight: 600;
    color: #495057;
}

.info-item span {
    color: #6c757d;
}

.status-active {
    background: #d4edda;
    color: #155724;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 600;
}

.status-inactive {
    background: #f8d7da;
    color: #721c24;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 600;
}

/* Responsive design */
@media (max-width: 1024px) {
    .profile-layout {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .profile-header {
        padding: 1.5rem;
    }
    
    .profile-avatar i {
        font-size: 3rem;
    }
    
    .profile-info h2 {
        font-size: 1.3rem;
    }
    
    .profile-stats {
        padding: 1.5rem;
    }
    
    .settings-section {
        padding: 1.5rem;
    }
    
    .stat-item {
        flex-direction: column;
        text-align: center;
        gap: 0.5rem;
    }
    
    .info-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
}

@media (max-width: 480px) {
    .profile-header {
        padding: 1rem;
    }
    
    .profile-stats,
    .settings-section {
        padding: 1rem;
    }
    
    .profile-info p {
        font-size: 0.9rem;
    }
}
</style>

<script>
// Profile page JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Password confirmation validation
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    
    function validatePasswordMatch() {
        if (newPasswordInput.value !== confirmPasswordInput.value) {
            confirmPasswordInput.setCustomValidity('Passwords do not match');
        } else {
            confirmPasswordInput.setCustomValidity('');
        }
    }
    
    if (newPasswordInput && confirmPasswordInput) {
        newPasswordInput.addEventListener('input', validatePasswordMatch);
        confirmPasswordInput.addEventListener('input', validatePasswordMatch);
    }
    
    // Password strength indicator
    if (newPasswordInput) {
        newPasswordInput.addEventListener('input', function() {
            const password = this.value;
            const strength = calculatePasswordStrength(password);
            updatePasswordStrengthIndicator(strength);
        });
    }
    
    // Form submission confirmation for password change
    const passwordForm = document.querySelector('.password-form');
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
            if (!confirm('Are you sure you want to change your password?')) {
                e.preventDefault();
            }
        });
    }
    
    // Auto-save indication for profile form
    const profileForm = document.querySelector('.profile-form');
    if (profileForm) {
        const inputs = profileForm.querySelectorAll('input[type="text"], input[type="email"], input[type="tel"]');
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                const submitBtn = profileForm.querySelector('button[type="submit"]');
                if (submitBtn && !submitBtn.classList.contains('btn-warning')) {
                    submitBtn.classList.remove('btn-primary');
                    submitBtn.classList.add('btn-warning');
                    submitBtn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Save Changes';
                }
            });
        });
    }
});

function calculatePasswordStrength(password) {
    let strength = 0;
    
    if (password.length >= 8) strength += 1;
    if (password.length >= 12) strength += 1;
    if (/[a-z]/.test(password)) strength += 1;
    if (/[A-Z]/.test(password)) strength += 1;
    if (/[0-9]/.test(password)) strength += 1;
    if (/[^A-Za-z0-9]/.test(password)) strength += 1;
    
    return strength;
}

function updatePasswordStrengthIndicator(strength) {
    // This could be enhanced with a visual strength indicator
    const strengthTexts = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong', 'Very Strong'];
    const strengthColors = ['#dc3545', '#fd7e14', '#ffc107', '#20c997', '#28a745', '#007bff'];
    
    // Implementation would depend on having a strength indicator element
    console.log('Password strength:', strengthTexts[strength] || 'Very Weak');
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl+S saves profile (prevent default browser save)
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        const profileForm = document.querySelector('.profile-form');
        if (profileForm) {
            profileForm.submit();
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>