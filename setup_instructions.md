# Ethiopian Police University Library Management System - Setup Instructions

## Prerequisites

Before setting up the system, ensure you have the following installed:

- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **PHP**: Version 7.4 or higher (8.0+ recommended)
- **MySQL**: Version 8.0+ or MariaDB 10.4+
- **XAMPP/WAMP/LAMP**: For local development (optional but recommended)

## Required PHP Extensions

Ensure the following PHP extensions are enabled:
- `pdo_mysql`
- `mysqli`
- `session`
- `json`
- `mbstring`
- `openssl`

## Installation Steps

### 1. Download and Extract Files

1. Download the library management system files
2. Extract to your web server directory:
   - **XAMPP**: `C:\xampp\htdocs\library-system\`
   - **WAMP**: `C:\wamp64\www\library-system\`
   - **Linux**: `/var/www/html/library-system/`

### 2. Database Setup

#### Option A: Using phpMyAdmin (Recommended for beginners)

1. Open phpMyAdmin in your browser (usually `http://localhost/phpmyadmin`)
2. Click "New" to create a new database
3. Enter database name: `epu_library`
4. Select collation: `utf8mb4_unicode_ci`
5. Click "Create"
6. Select the newly created database
7. Click "Import" tab
8. Choose file: `database/schema.sql`
9. Click "Go" to execute
10. After schema import, import sample data:
    - Click "Import" tab again
    - Choose file: `database/sample_data.sql`
    - Click "Go" to execute

#### Option B: Using MySQL Command Line

```bash
# Login to MySQL
mysql -u root -p

# Create database
CREATE DATABASE epu_library CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# Exit MySQL
exit

# Import schema
mysql -u root -p epu_library < database/schema.sql

# Import sample data
mysql -u root -p epu_library < database/sample_data.sql
```

### 3. Configuration

1. Open `includes/config.php`
2. Update database connection settings if needed:
   ```php
   define('DB_HOST', 'localhost');     // Your MySQL host
   define('DB_NAME', 'epu_library');   // Database name
   define('DB_USER', 'root');          // MySQL username
   define('DB_PASS', '');              // MySQL password (empty for XAMPP default)
   ```

3. For production environments, update security settings:
   ```php
   ini_set('session.cookie_secure', 1);  // Set to 1 for HTTPS
   ```

### 4. File Permissions

Set appropriate file permissions (Linux/Mac):
```bash
# Make directories writable
chmod 755 -R library-system/
chmod 777 library-system/logs/
chmod 644 library-system/includes/config.php
```

For Windows with XAMPP, no additional permissions are typically needed.

### 5. Web Server Configuration

#### Apache (.htaccess)

Create `.htaccess` file in the root directory:
```apache
RewriteEngine On

# Redirect to HTTPS (uncomment for production)
# RewriteCond %{HTTPS} off
# RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"

# Hide sensitive files
<Files "config.php">
    Order allow,deny
    Deny from all
</Files>

<Files "*.log">
    Order allow,deny
    Deny from all
</Files>

# Custom error pages
ErrorDocument 404 /library-system/404.php
ErrorDocument 500 /library-system/500.php
```

#### Nginx

Add to your Nginx configuration:
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/html/library-system;
    index index.php;

    # Security headers
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options DENY;
    add_header X-XSS-Protection "1; mode=block";

    # PHP processing
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Deny access to sensitive files
    location ~ /(config\.php|\.log)$ {
        deny all;
    }
}
```

## Default Login Credentials

### Administrator Account
- **Username**: `admin`
- **Password**: `admin123`
- **Role**: Administrator (full system access)

### Librarian Account
- **Username**: `librarian`
- **Password**: `librarian123`
- **Role**: Librarian (book and borrowing management)

### Sample Student Account
- **Username**: `STU001`
- **Password**: `STU001`
- **Role**: Student (book browsing and borrowing)

### Sample Staff Account
- **Username**: `STAFF001`
- **Password**: `STAFF001`
- **Role**: Staff (book browsing and borrowing)

## Testing the Installation

1. Open your web browser
2. Navigate to: `http://localhost/library-system/` (adjust path as needed)
3. You should see the login page
4. Try logging in with the default admin credentials
5. Verify that the dashboard loads correctly

## Post-Installation Security

### 1. Change Default Passwords

**IMPORTANT**: Change all default passwords immediately after installation:

1. Login as admin
2. Go to Settings > User Management
3. Update passwords for all default accounts
4. Create new admin accounts with strong passwords
5. Disable or delete default accounts if not needed

### 2. Database Security

1. Create a dedicated MySQL user for the application:
   ```sql
   CREATE USER 'library_user'@'localhost' IDENTIFIED BY 'strong_password_here';
   GRANT SELECT, INSERT, UPDATE, DELETE ON epu_library.* TO 'library_user'@'localhost';
   FLUSH PRIVILEGES;
   ```

2. Update `includes/config.php` with the new credentials
3. Remove or secure the MySQL root account

### 3. File Security

1. Remove or secure database files:
   ```bash
   # Move SQL files outside web directory or delete them
   rm database/schema.sql
   rm database/sample_data.sql
   ```

2. Set restrictive permissions on config file:
   ```bash
   chmod 600 includes/config.php
   ```

### 4. SSL/HTTPS Setup

For production environments:
1. Obtain SSL certificate
2. Configure web server for HTTPS
3. Update `includes/config.php`:
   ```php
   ini_set('session.cookie_secure', 1);
   ```
4. Force HTTPS redirects in `.htaccess`

## Troubleshooting

### Common Issues

#### 1. Database Connection Error
- Verify MySQL is running
- Check database credentials in `config.php`
- Ensure database exists and is accessible

#### 2. Permission Denied Errors
- Check file permissions
- Ensure web server can write to `logs/` directory
- Verify PHP has necessary extensions enabled

#### 3. Session Issues
- Check PHP session configuration
- Ensure `session.save_path` is writable
- Verify session cookies are enabled

#### 4. Login Problems
- Verify database contains admin accounts
- Check password hashing (should use PHP's `password_hash()`)
- Review security logs in `logs/security.log`

### Log Files

Monitor these log files for issues:
- `logs/security.log` - Security events and login attempts
- Web server error logs (Apache: `error.log`, Nginx: `error.log`)
- PHP error logs

### Getting Help

If you encounter issues:
1. Check the troubleshooting section above
2. Review log files for error messages
3. Verify all prerequisites are met
4. Ensure database schema was imported correctly

## Maintenance

### Regular Tasks

1. **Backup Database**: Regular backups of the `epu_library` database
2. **Update Passwords**: Periodic password updates for all accounts
3. **Monitor Logs**: Regular review of security and error logs
4. **Update Software**: Keep PHP, MySQL, and web server updated

### Database Maintenance

```sql
-- Optimize tables monthly
OPTIMIZE TABLE books, users, borrow_records, fines;

-- Clean old security logs (older than 6 months)
DELETE FROM security_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH);

-- Update statistics
ANALYZE TABLE books, users, borrow_records;
```

## System Requirements Summary

- **Minimum PHP Version**: 7.4
- **Recommended PHP Version**: 8.0+
- **MySQL Version**: 8.0+ or MariaDB 10.4+
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **Disk Space**: 100MB minimum (more for logs and future data)
- **Memory**: 512MB RAM minimum for PHP

## Support

For technical support or questions about the Ethiopian Police University Library Management System, please contact the system administrator or refer to the documentation provided with the system.