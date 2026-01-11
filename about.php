<?php
/**
 * About Page - Ethiopian Police University Library Management System
 * Information about the library, system, and services for librarians
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

$page_title = 'About the Library System';

// Get system information from database
try {
    $system_info_sql = "SELECT setting_key, setting_value FROM system_settings 
                        WHERE setting_key IN ('library_name', 'library_email', 'library_phone', 'system_version')";
    $system_info_result = execute_query($system_info_sql);
    $system_settings = [];
    while ($row = $system_info_result->fetch()) {
        $system_settings[$row['setting_key']] = $row['setting_value'];
    }
    
    // Get library statistics
    $stats_sql = "SELECT 
                    (SELECT COUNT(*) FROM books) as total_books,
                    (SELECT SUM(total_copies) FROM books) as total_copies,
                    (SELECT COUNT(*) FROM users WHERE status = 'active') as active_users,
                    (SELECT COUNT(*) FROM categories) as total_categories,
                    (SELECT COUNT(*) FROM borrow_records WHERE return_date IS NULL) as active_borrowings,
                    (SELECT COUNT(*) FROM admins WHERE role = 'librarian' AND status = 'active') as active_librarians";
    $stats = execute_query($stats_sql)->fetch();
    
} catch (Exception $e) {
    error_log("Error fetching system information: " . $e->getMessage());
    $system_settings = [
        'library_name' => 'Ethiopian Police University Library',
        'library_email' => 'library@epu.edu.et',
        'library_phone' => '+251-11-XXX-XXXX',
        'system_version' => '1.0.0'
    ];
    $stats = [
        'total_books' => 0,
        'total_copies' => 0,
        'active_users' => 0,
        'total_categories' => 0,
        'active_borrowings' => 0,
        'active_librarians' => 0
    ];
}

include '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-info-circle"></i> About the Library System</h1>
        <div class="header-actions">
            <span class="role-badge">Librarian View</span>
        </div>
    </div>

    <!-- University Information -->
    <div class="about-section">
        <div class="section-card">
            <div class="section-header">
                <h2><i class="fas fa-university"></i> Ethiopian Police University Library</h2>
            </div>
            <div class="section-content">
                <div class="university-info">
                    <div class="info-grid">
                        <div class="info-card">
                            <div class="info-icon">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                            <div class="info-content">
                                <h3>Our Mission</h3>
                                <p>To provide comprehensive library services and resources that support the academic, research, and professional development needs of the Ethiopian Police University community.</p>
                            </div>
                        </div>
                        
                        <div class="info-card">
                            <div class="info-icon">
                                <i class="fas fa-eye"></i>
                            </div>
                            <div class="info-content">
                                <h3>Our Vision</h3>
                                <p>To be a leading academic library that empowers law enforcement education through innovative information services and cutting-edge technology.</p>
                            </div>
                        </div>
                        
                        <div class="info-card">
                            <div class="info-icon">
                                <i class="fas fa-heart"></i>
                            </div>
                            <div class="info-content">
                                <h3>Our Values</h3>
                                <ul>
                                    <li>Excellence in service delivery</li>
                                    <li>Integrity and professionalism</li>
                                    <li>Innovation and continuous improvement</li>
                                    <li>Accessibility and inclusivity</li>
                                    <li>Collaboration and teamwork</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Library Statistics -->
    <div class="about-section">
        <div class="section-card">
            <div class="section-header">
                <h2><i class="fas fa-chart-bar"></i> Library at a Glance</h2>
            </div>
            <div class="section-content">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['total_books']); ?></h3>
                            <p>Book Titles</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-books"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['total_copies']); ?></h3>
                            <p>Total Copies</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['active_users']); ?></h3>
                            <p>Active Users</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-tags"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['total_categories']); ?></h3>
                            <p>Categories</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-hand-holding"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['active_borrowings']); ?></h3>
                            <p>Active Borrowings</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['active_librarians']); ?></h3>
                            <p>Active Librarians</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- System Information -->
    <div class="about-section">
        <div class="section-card">
            <div class="section-header">
                <h2><i class="fas fa-desktop"></i> Library Management System</h2>
            </div>
            <div class="section-content">
                <div class="system-info">
                    <div class="system-overview">
                        <h3>System Overview</h3>
                        <p>The Ethiopian Police University Library Management System is a comprehensive digital solution designed to streamline library operations, enhance user experience, and provide powerful analytics for informed decision-making.</p>
                        
                        <div class="system-features">
                            <h4>Key Features</h4>
                            <div class="features-grid">
                                <div class="feature-item">
                                    <i class="fas fa-book-open"></i>
                                    <span>Comprehensive Book Management</span>
                                </div>
                                <div class="feature-item">
                                    <i class="fas fa-users-cog"></i>
                                    <span>User Management & Authentication</span>
                                </div>
                                <div class="feature-item">
                                    <i class="fas fa-exchange-alt"></i>
                                    <span>Borrowing & Returns Processing</span>
                                </div>
                                <div class="feature-item">
                                    <i class="fas fa-money-bill-wave"></i>
                                    <span>Automated Fine Management</span>
                                </div>
                                <div class="feature-item">
                                    <i class="fas fa-chart-line"></i>
                                    <span>Advanced Reporting & Analytics</span>
                                </div>
                                <div class="feature-item">
                                    <i class="fas fa-mobile-alt"></i>
                                    <span>Mobile-Responsive Design</span>
                                </div>
                                <div class="feature-item">
                                    <i class="fas fa-shield-alt"></i>
                                    <span>Role-Based Security</span>
                                </div>
                                <div class="feature-item">
                                    <i class="fas fa-cloud"></i>
                                    <span>Real-Time Data Synchronization</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="system-details">
                        <h4>System Information</h4>
                        <table class="info-table">
                            <tr>
                                <td><strong>System Version:</strong></td>
                                <td><?php echo htmlspecialchars($system_settings['system_version']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Library Name:</strong></td>
                                <td><?php echo htmlspecialchars($system_settings['library_name']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Contact Email:</strong></td>
                                <td><?php echo htmlspecialchars($system_settings['library_email']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Contact Phone:</strong></td>
                                <td><?php echo htmlspecialchars($system_settings['library_phone']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Last Updated:</strong></td>
                                <td><?php echo date('F j, Y'); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Services & Resources -->
    <div class="about-section">
        <div class="section-card">
            <div class="section-header">
                <h2><i class="fas fa-concierge-bell"></i> Library Services</h2>
            </div>
            <div class="section-content">
                <div class="services-grid">
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="fas fa-book-reader"></i>
                        </div>
                        <div class="service-content">
                            <h3>Book Lending Services</h3>
                            <p>Comprehensive book borrowing and return services with automated tracking and notifications.</p>
                            <ul>
                                <li>14-day standard borrowing period</li>
                                <li>Renewal options available</li>
                                <li>Overdue notifications</li>
                                <li>Hold and reservation system</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="fas fa-search"></i>
                        </div>
                        <div class="service-content">
                            <h3>Research Support</h3>
                            <p>Specialized resources and assistance for academic and professional research.</p>
                            <ul>
                                <li>Criminal justice databases</li>
                                <li>Law enforcement journals</li>
                                <li>Research methodology guides</li>
                                <li>Citation assistance</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <div class="service-content">
                            <h3>Academic Support</h3>
                            <p>Resources and services to support student and faculty academic success.</p>
                            <ul>
                                <li>Course reserves</li>
                                <li>Study materials</li>
                                <li>Reference assistance</li>
                                <li>Information literacy training</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="fas fa-laptop"></i>
                        </div>
                        <div class="service-content">
                            <h3>Digital Services</h3>
                            <p>Modern digital tools and resources for enhanced learning and research.</p>
                            <ul>
                                <li>Online catalog access</li>
                                <li>Digital resource management</li>
                                <li>Remote access capabilities</li>
                                <li>Mobile-friendly interface</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Collection Information -->
    <div class="about-section">
        <div class="section-card">
            <div class="section-header">
                <h2><i class="fas fa-archive"></i> Our Collection</h2>
            </div>
            <div class="section-content">
                <div class="collection-info">
                    <p>The Ethiopian Police University Library maintains a comprehensive collection of resources specifically curated to support law enforcement education, criminal justice studies, and related disciplines.</p>
                    
                    <div class="collection-categories">
                        <h4>Major Collection Areas</h4>
                        <div class="categories-grid">
                            <div class="category-item">
                                <i class="fas fa-balance-scale"></i>
                                <span>Law Enforcement</span>
                            </div>
                            <div class="category-item">
                                <i class="fas fa-gavel"></i>
                                <span>Criminal Justice</span>
                            </div>
                            <div class="category-item">
                                <i class="fas fa-laptop-code"></i>
                                <span>Computer Science</span>
                            </div>
                            <div class="category-item">
                                <i class="fas fa-brain"></i>
                                <span>Psychology</span>
                            </div>
                            <div class="category-item">
                                <i class="fas fa-chart-line"></i>
                                <span>Management</span>
                            </div>
                            <div class="category-item">
                                <i class="fas fa-microscope"></i>
                                <span>Research Methods</span>
                            </div>
                            <div class="category-item">
                                <i class="fas fa-book"></i>
                                <span>Literature</span>
                            </div>
                            <div class="category-item">
                                <i class="fas fa-atom"></i>
                                <span>Science</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Contact Information -->
    <div class="about-section">
        <div class="section-card">
            <div class="section-header">
                <h2><i class="fas fa-phone"></i> Contact Information</h2>
            </div>
            <div class="section-content">
                <div class="contact-info">
                    <div class="contact-grid">
                        <div class="contact-item">
                            <div class="contact-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div class="contact-details">
                                <h4>Address</h4>
                                <p>Ethiopian Police University<br>
                                Library Building<br>
                                Addis Ababa, Ethiopia</p>
                            </div>
                        </div>
                        
                        <div class="contact-item">
                            <div class="contact-icon">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div class="contact-details">
                                <h4>Phone</h4>
                                <p><?php echo htmlspecialchars($system_settings['library_phone']); ?></p>
                            </div>
                        </div>
                        
                        <div class="contact-item">
                            <div class="contact-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="contact-details">
                                <h4>Email</h4>
                                <p><?php echo htmlspecialchars($system_settings['library_email']); ?></p>
                            </div>
                        </div>
                        
                        <div class="contact-item">
                            <div class="contact-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="contact-details">
                                <h4>Operating Hours</h4>
                                <p>Monday - Friday: 8:00 AM - 6:00 PM<br>
                                Saturday: 9:00 AM - 4:00 PM<br>
                                Sunday: Closed</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="about-section">
        <div class="section-card">
            <div class="section-header">
                <h2><i class="fas fa-rocket"></i> Quick Actions</h2>
            </div>
            <div class="section-content">
                <div class="quick-actions">
                    <a href="books.php" class="action-btn">
                        <i class="fas fa-book"></i>
                        <span>Manage Books</span>
                    </a>
                    <a href="borrowing.php" class="action-btn">
                        <i class="fas fa-hand-holding"></i>
                        <span>Process Borrowing</span>
                    </a>
                    <a href="returns.php" class="action-btn">
                        <i class="fas fa-undo"></i>
                        <span>Handle Returns</span>
                    </a>
                    <a href="reports.php" class="action-btn">
                        <i class="fas fa-chart-bar"></i>
                        <span>View Reports</span>
                    </a>
                    <a href="profile.php" class="action-btn">
                        <i class="fas fa-user"></i>
                        <span>My Profile</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* About Page Specific Styles */
.about-section {
    margin-bottom: 2rem;
}

.section-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    overflow: hidden;
}

.section-header {
    background: #f8f9fa;
    padding: 1.5rem;
    border-bottom: 1px solid #e9ecef;
}

.section-header h2 {
    margin: 0;
    color: #1e3c72;
    font-size: 1.5rem;
}

.section-content {
    padding: 2rem;
}

/* University Information */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
}

.info-card {
    display: flex;
    gap: 1rem;
    padding: 1.5rem;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 4px solid #007bff;
}

.info-icon {
    font-size: 2rem;
    color: #007bff;
    flex-shrink: 0;
}

.info-content h3 {
    margin: 0 0 1rem 0;
    color: #1e3c72;
}

.info-content ul {
    margin: 0;
    padding-left: 1.5rem;
}

.info-content li {
    margin-bottom: 0.5rem;
}

/* Statistics */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
}

.stat-card {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 8px;
    text-align: center;
    border-left: 4px solid #28a745;
}

.stat-icon {
    font-size: 2.5rem;
    color: #28a745;
    margin-bottom: 1rem;
}

.stat-info h3 {
    margin: 0 0 0.5rem 0;
    font-size: 2rem;
    color: #1e3c72;
}

.stat-info p {
    margin: 0;
    color: #6c757d;
    font-weight: 600;
}

/* System Information */
.system-info {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
}

.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.feature-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem;
    background: #e9ecef;
    border-radius: 6px;
}

.feature-item i {
    color: #007bff;
    width: 20px;
}

.info-table {
    width: 100%;
    border-collapse: collapse;
}

.info-table td {
    padding: 0.75rem;
    border-bottom: 1px solid #e9ecef;
}

.info-table td:first-child {
    width: 40%;
    background: #f8f9fa;
}

/* Services */
.services-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
}

.service-card {
    background: #f8f9fa;
    padding: 2rem;
    border-radius: 8px;
    border-left: 4px solid #17a2b8;
}

.service-icon {
    font-size: 2.5rem;
    color: #17a2b8;
    margin-bottom: 1rem;
}

.service-content h3 {
    margin: 0 0 1rem 0;
    color: #1e3c72;
}

.service-content ul {
    margin: 1rem 0 0 0;
    padding-left: 1.5rem;
}

.service-content li {
    margin-bottom: 0.5rem;
}

/* Collection Categories */
.categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.category-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem;
    background: #e9ecef;
    border-radius: 6px;
    font-weight: 600;
}

.category-item i {
    color: #ffc107;
    font-size: 1.2rem;
}

/* Contact Information */
.contact-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 2rem;
}

.contact-item {
    display: flex;
    gap: 1rem;
    padding: 1.5rem;
    background: #f8f9fa;
    border-radius: 8px;
}

.contact-icon {
    font-size: 2rem;
    color: #dc3545;
    flex-shrink: 0;
}

.contact-details h4 {
    margin: 0 0 0.5rem 0;
    color: #1e3c72;
}

.contact-details p {
    margin: 0;
    color: #6c757d;
}

/* Quick Actions */
.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
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

/* Responsive Design */
@media (max-width: 768px) {
    .system-info {
        grid-template-columns: 1fr;
    }
    
    .info-grid,
    .stats-grid,
    .services-grid,
    .contact-grid {
        grid-template-columns: 1fr;
    }
    
    .features-grid,
    .categories-grid {
        grid-template-columns: 1fr;
    }
    
    .section-content {
        padding: 1rem;
    }
}
</style>

<?php include '../includes/footer.php'; ?>