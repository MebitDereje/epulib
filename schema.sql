-- Ethiopian Police University Library Management System Database Schema
-- Created: 2025
-- Version: 1.0.0

-- Create database
CREATE DATABASE IF NOT EXISTS epu_library CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE epu_library;

-- Categories table
CREATE TABLE categories (
    category_id INT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Books table
CREATE TABLE books (
    book_id INT PRIMARY KEY AUTO_INCREMENT,
    isbn VARCHAR(13) UNIQUE NOT NULL,
    title VARCHAR(200) NOT NULL,
    author VARCHAR(100) NOT NULL,
    publisher VARCHAR(100) NOT NULL,
    category_id INT NOT NULL,
    publication_year YEAR,
    total_copies INT DEFAULT 1,
    available_copies INT DEFAULT 1,
    status ENUM('available', 'borrowed', 'maintenance') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE RESTRICT,
    INDEX idx_isbn (isbn),
    INDEX idx_title (title),
    INDEX idx_author (author),
    INDEX idx_category (category_id),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Users table (for students and staff)
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    id_number VARCHAR(20) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    department VARCHAR(50) NOT NULL,
    role ENUM('student', 'staff') NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(15),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_id_number (id_number),
    INDEX idx_department (department),
    INDEX idx_role (role),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Admins table (for administrators and librarians)
CREATE TABLE admins (
    admin_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'librarian') NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(15),
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_role (role),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Borrow records table
CREATE TABLE borrow_records (
    borrow_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    borrow_date DATE NOT NULL,
    due_date DATE NOT NULL,
    return_date DATE NULL,
    status ENUM('borrowed', 'returned', 'overdue') DEFAULT 'borrowed',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(book_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_book_id (book_id),
    INDEX idx_borrow_date (borrow_date),
    INDEX idx_due_date (due_date),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Fines table
CREATE TABLE fines (
    fine_id INT PRIMARY KEY AUTO_INCREMENT,
    borrow_id INT NOT NULL,
    user_id INT NOT NULL,
    fine_amount DECIMAL(10,2) NOT NULL,
    payment_status ENUM('unpaid', 'paid', 'waived') DEFAULT 'unpaid',
    payment_date DATE NULL,
    payment_method VARCHAR(50) NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (borrow_id) REFERENCES borrow_records(borrow_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_borrow_id (borrow_id),
    INDEX idx_user_id (user_id),
    INDEX idx_payment_status (payment_status)
) ENGINE=InnoDB;

-- Security logs table
CREATE TABLE security_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    event_type VARCHAR(50) NOT NULL,
    user_id INT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    event_description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event_type (event_type),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- System settings table
CREATE TABLE system_settings (
    setting_id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Triggers for automatic book availability updates
DELIMITER //

CREATE TRIGGER update_book_availability_after_borrow
AFTER INSERT ON borrow_records
FOR EACH ROW
BEGIN
    UPDATE books 
    SET available_copies = available_copies - 1,
        status = CASE 
            WHEN available_copies - 1 <= 0 THEN 'borrowed'
            ELSE 'available'
        END
    WHERE book_id = NEW.book_id;
END//

CREATE TRIGGER update_book_availability_after_return
AFTER UPDATE ON borrow_records
FOR EACH ROW
BEGIN
    IF OLD.return_date IS NULL AND NEW.return_date IS NOT NULL THEN
        UPDATE books 
        SET available_copies = available_copies + 1,
            status = 'available'
        WHERE book_id = NEW.book_id;
    END IF;
END//

-- Trigger to automatically calculate overdue status
CREATE TRIGGER check_overdue_status
BEFORE UPDATE ON borrow_records
FOR EACH ROW
BEGIN
    IF NEW.return_date IS NULL AND NEW.due_date < CURDATE() THEN
        SET NEW.status = 'overdue';
    ELSEIF NEW.return_date IS NOT NULL THEN
        SET NEW.status = 'returned';
    END IF;
END//

-- Trigger to automatically create fine records for overdue books
CREATE TRIGGER create_fine_for_overdue
AFTER UPDATE ON borrow_records
FOR EACH ROW
BEGIN
    DECLARE days_overdue INT;
    DECLARE fine_amount DECIMAL(10,2);
    
    IF NEW.return_date IS NOT NULL AND NEW.return_date > NEW.due_date THEN
        SET days_overdue = DATEDIFF(NEW.return_date, NEW.due_date);
        SET fine_amount = days_overdue * 2.00; -- 2 ETB per day
        
        INSERT INTO fines (borrow_id, user_id, fine_amount, notes)
        VALUES (NEW.borrow_id, NEW.user_id, fine_amount, 
                CONCAT('Late return: ', days_overdue, ' days overdue'));
    END IF;
END//

DELIMITER ;

-- Insert default categories
INSERT INTO categories (category_name, description) VALUES
('Computer Science', 'Books related to computer science, programming, and technology'),
('Law Enforcement', 'Books on police procedures, criminal law, and law enforcement'),
('Criminal Justice', 'Books on criminal justice system, criminology, and forensics'),
('Management', 'Books on management, leadership, and administration'),
('Psychology', 'Books on psychology, behavioral science, and mental health'),
('History', 'Historical books and references'),
('Literature', 'Fiction and non-fiction literature'),
('Science', 'General science books and references'),
('Mathematics', 'Mathematics and statistics books'),
('Research Methods', 'Books on research methodology and academic writing');

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('borrowing_period_days', '14', 'Default borrowing period in days'),
('max_books_per_user', '3', 'Maximum number of books a user can borrow simultaneously'),
('fine_per_day', '2.00', 'Fine amount per day for overdue books (in ETB)'),
('library_name', 'Ethiopian Police University Library', 'Official library name'),
('library_email', 'library@epu.edu.et', 'Library contact email'),
('library_phone', '+251-11-XXX-XXXX', 'Library contact phone'),
('system_version', '1.0.0', 'Current system version'),
('maintenance_mode', '0', 'System maintenance mode (0=off, 1=on)');

-- Insert default admin account
-- Password: admin123 (should be changed after first login)
INSERT INTO admins (username, password_hash, full_name, role, email) VALUES
('admin', '$2y$10$.JQco53HqfcyaCN403uzPukwf2IGGjtvgIJR60ieWS76xTCokFbfO', 'System Administrator', 'admin', 'admin@epu.edu.et');

-- Insert default librarian account  
-- Password: librarian123 (should be changed after first login)
INSERT INTO admins (username, password_hash, full_name, role, email) VALUES
('librarian', '$2y$10$shDkJaC6f2ViP60Fe06WHOmn5p8I4SKB3M.1ImVKhH2OXBn849sXu', 'Head Librarian', 'librarian', 'librarian@epu.edu.et');

-- ============================================================================
-- SAMPLE DATA FOR TESTING AND DEMONSTRATION
-- ============================================================================

-- Sample books data
INSERT INTO books (isbn, title, author, publisher, category_id, publication_year, total_copies, available_copies) VALUES
-- Computer Science Books
('9780134685991', 'Effective Java', 'Joshua Bloch', 'Addison-Wesley', 1, 2018, 3, 3),
('9780135957059', 'The Pragmatic Programmer', 'David Thomas, Andrew Hunt', 'Addison-Wesley', 1, 2019, 2, 2),
('9780132350884', 'Clean Code', 'Robert C. Martin', 'Prentice Hall', 1, 2008, 4, 4),
('9780596517748', 'JavaScript: The Good Parts', 'Douglas Crockford', 'O\'Reilly Media', 1, 2008, 2, 2),
('9780134494166', 'Clean Architecture', 'Robert C. Martin', 'Prentice Hall', 1, 2017, 2, 2),

-- Law Enforcement Books
('9780135159545', 'Police Administration', 'Larry K. Gaines', 'Cengage Learning', 2, 2020, 5, 5),
('9780134548654', 'Criminal Investigation', 'Steven G. Brandl', 'Pearson', 2, 2019, 3, 3),
('9780135188767', 'Police Operations', 'Nathan R. Moran', 'Cengage Learning', 2, 2021, 4, 4),
('9780134685984', 'Constitutional Law for Criminal Justice', 'Jacqueline R. Kanovitz', 'Routledge', 2, 2020, 3, 3),
('9780135957066', 'Ethics in Law Enforcement', 'Joycelyn M. Pollock', 'Cengage Learning', 2, 2019, 2, 2),

-- Criminal Justice Books
('9780134494173', 'Criminal Justice Today', 'Frank J. Schmalleger', 'Pearson', 3, 2021, 6, 6),
('9780135188774', 'Criminology: Theories, Patterns, and Typologies', 'Larry J. Siegel', 'Cengage Learning', 3, 2020, 4, 4),
('9780134548661', 'Introduction to Criminal Justice', 'Larry J. Siegel', 'Cengage Learning', 3, 2019, 5, 5),
('9780135159552', 'Forensic Science: Fundamentals and Investigations', 'Anthony J. Bertino', 'Cengage Learning', 3, 2020, 3, 3),
('9780134685998', 'Victimology: Theories and Applications', 'Ann Wolbert Burgess', 'Jones & Bartlett', 3, 2019, 2, 2),

-- Management Books
('9780135957073', 'Principles of Management', 'Chuck Williams', 'Cengage Learning', 4, 2020, 4, 4),
('9780134494180', 'Leadership: Theory and Practice', 'Peter G. Northouse', 'SAGE Publications', 4, 2021, 3, 3),
('9780135188781', 'Organizational Behavior', 'Stephen P. Robbins', 'Pearson', 4, 2019, 3, 3),
('9780134548678', 'Strategic Management', 'Frank T. Rothaermel', 'McGraw-Hill', 4, 2020, 2, 2),
('9780135159569', 'Human Resource Management', 'Gary Dessler', 'Pearson', 4, 2021, 3, 3),

-- Psychology Books
('9780134685005', 'Psychology: The Science of Mind and Behaviour', 'Michael W. Passer', 'McGraw-Hill', 5, 2020, 4, 4),
('9780135957080', 'Cognitive Psychology', 'Robert J. Sternberg', 'Cengage Learning', 5, 2019, 2, 2),
('9780134494197', 'Social Psychology', 'David G. Myers', 'McGraw-Hill', 5, 2021, 3, 3),
('9780135188798', 'Abnormal Psychology', 'James N. Butcher', 'Pearson', 5, 2020, 2, 2),
('9780134548685', 'Developmental Psychology', 'Kathleen Stassen Berger', 'Worth Publishers', 5, 2019, 3, 3),

-- History Books
('9780135159576', 'A History of Ethiopia', 'Harold G. Marcus', 'University of California Press', 6, 2018, 3, 3),
('9780134685012', 'The Oxford History of Ancient Egypt', 'Ian Shaw', 'Oxford University Press', 6, 2019, 2, 2),
('9780135957097', 'African History: A Very Short Introduction', 'John Parker', 'Oxford University Press', 6, 2020, 4, 4),
('9780134494203', 'World History: Patterns of Interaction', 'Roger B. Beck', 'McDougal Littell', 6, 2021, 5, 5),
('9780135188804', 'The History of Police in Ethiopia', 'Mehari Taddele Maru', 'Academic Publishers', 6, 2020, 2, 2),

-- Literature Books
('9780134548692', 'Things Fall Apart', 'Chinua Achebe', 'Anchor Books', 7, 2017, 4, 4),
('9780135159583', 'The Beautiful Ones Are Not Yet Born', 'Ayi Kwei Armah', 'Heinemann', 7, 2018, 2, 2),
('9780134685029', 'Nervous Conditions', 'Tsitsi Dangarembga', 'Seal Press', 7, 2019, 3, 3),
('9780135957103', 'Purple Hibiscus', 'Chimamanda Ngozi Adichie', 'Anchor Books', 7, 2020, 3, 3),
('9780134494210', 'Half of a Yellow Sun', 'Chimamanda Ngozi Adichie', 'Anchor Books', 7, 2021, 2, 2),

-- Science Books
('9780135188811', 'Campbell Biology', 'Jane B. Reece', 'Pearson', 8, 2020, 5, 5),
('9780134548708', 'Chemistry: The Central Science', 'Theodore E. Brown', 'Pearson', 8, 2019, 4, 4),
('9780135159590', 'Physics: Principles and Problems', 'Paul W. Zitzewitz', 'Glencoe/McGraw-Hill', 8, 2021, 3, 3),
('9780134685036', 'Environmental Science', 'G. Tyler Miller', 'Cengage Learning', 8, 2020, 3, 3),
('9780135957110', 'Astronomy: A Beginner\'s Guide', 'William H. Waller', 'Cambridge University Press', 8, 2019, 2, 2),

-- Mathematics Books
('9780134494227', 'Calculus: Early Transcendentals', 'James Stewart', 'Cengage Learning', 9, 2020, 4, 4),
('9780135188828', 'Linear Algebra and Its Applications', 'David C. Lay', 'Pearson', 9, 2019, 3, 3),
('9780134548715', 'Statistics for Business and Economics', 'Paul Newbold', 'Pearson', 9, 2021, 3, 3),
('9780135159606', 'Discrete Mathematics and Its Applications', 'Kenneth H. Rosen', 'McGraw-Hill', 9, 2020, 2, 2),
('9780134685043', 'Probability and Statistics', 'Morris H. DeGroot', 'Pearson', 9, 2019, 3, 3),

-- Research Methods Books
('9780135957127', 'Research Methods in Criminal Justice', 'Frank E. Hagan', 'Pearson', 10, 2021, 4, 4),
('9780134494234', 'Social Research Methods', 'Alan Bryman', 'Oxford University Press', 10, 2020, 3, 3),
('9780135188835', 'The Craft of Research', 'Wayne C. Booth', 'University of Chicago Press', 10, 2019, 3, 3),
('9780134548722', 'Qualitative Research Methods', 'Sarah J. Tracy', 'Wiley-Blackwell', 10, 2021, 2, 2),
('9780135159613', 'Statistical Methods for Research', 'John A. Ingram', 'Academic Press', 10, 2020, 2, 2);

-- Sample users (students and staff)
INSERT INTO users (id_number, full_name, department, role, email, phone) VALUES
-- Students
('STU001', 'Abebe Kebede', 'Criminal Justice', 'student', 'abebe.kebede@student.epu.edu.et', '+251911123456'),
('STU002', 'Almaz Tadesse', 'Law Enforcement', 'student', 'almaz.tadesse@student.epu.edu.et', '+251911234567'),
('STU003', 'Dawit Haile', 'Computer Science', 'student', 'dawit.haile@student.epu.edu.et', '+251911345678'),
('STU004', 'Hanan Mohammed', 'Psychology', 'student', 'hanan.mohammed@student.epu.edu.et', '+251911456789'),
('STU005', 'Kidist Alemayehu', 'Management', 'student', 'kidist.alemayehu@student.epu.edu.et', '+251911567890'),
('STU006', 'Meron Tesfaye', 'Criminal Justice', 'student', 'meron.tesfaye@student.epu.edu.et', '+251911678901'),
('STU007', 'Robel Girma', 'Law Enforcement', 'student', 'robel.girma@student.epu.edu.et', '+251911789012'),
('STU008', 'Sara Bekele', 'Computer Science', 'student', 'sara.bekele@student.epu.edu.et', '+251911890123'),
('STU009', 'Tewodros Mulugeta', 'Psychology', 'student', 'tewodros.mulugeta@student.epu.edu.et', '+251911901234'),
('STU010', 'Yodit Assefa', 'Management', 'student', 'yodit.assefa@student.epu.edu.et', '+251911012345'),

-- Staff
('STAFF001', 'Dr. Getachew Mekonnen', 'Criminal Justice', 'staff', 'getachew.mekonnen@epu.edu.et', '+251911111111'),
('STAFF002', 'Prof. Tigist Worku', 'Law Enforcement', 'staff', 'tigist.worku@epu.edu.et', '+251911222222'),
('STAFF003', 'Dr. Solomon Desta', 'Computer Science', 'staff', 'solomon.desta@epu.edu.et', '+251911333333'),
('STAFF004', 'Dr. Rahel Negash', 'Psychology', 'staff', 'rahel.negash@epu.edu.et', '+251911444444'),
('STAFF005', 'Prof. Berhanu Teshome', 'Management', 'staff', 'berhanu.teshome@epu.edu.et', '+251911555555');

-- Sample borrow records (some current, some returned)
INSERT INTO borrow_records (user_id, book_id, borrow_date, due_date, return_date, status) VALUES
-- Current borrowings
(1, 1, '2025-01-01', '2025-01-15', NULL, 'borrowed'),
(2, 6, '2025-01-03', '2025-01-17', NULL, 'borrowed'),
(3, 11, '2025-01-05', '2025-01-19', NULL, 'borrowed'),
(4, 16, '2025-01-07', '2025-01-21', NULL, 'borrowed'),
(5, 21, '2025-01-08', '2025-01-22', NULL, 'borrowed'),

-- Overdue borrowings
(6, 2, '2024-12-15', '2024-12-29', NULL, 'overdue'),
(7, 7, '2024-12-20', '2025-01-03', NULL, 'overdue'),

-- Returned books
(1, 26, '2024-12-01', '2024-12-15', '2024-12-14', 'returned'),
(2, 31, '2024-12-05', '2024-12-19', '2024-12-18', 'returned'),
(3, 36, '2024-12-10', '2024-12-24', '2024-12-23', 'returned'),
(8, 3, '2024-11-15', '2024-11-29', '2024-12-02', 'returned'), -- Late return
(9, 8, '2024-11-20', '2024-12-04', '2024-12-06', 'returned'), -- Late return
(10, 13, '2024-11-25', '2024-12-09', '2024-12-08', 'returned'),

-- More historical records
(11, 18, '2024-10-01', '2024-10-15', '2024-10-14', 'returned'),
(12, 23, '2024-10-05', '2024-10-19', '2024-10-20', 'returned'), -- Late return
(13, 28, '2024-10-10', '2024-10-24', '2024-10-23', 'returned'),
(14, 33, '2024-10-15', '2024-10-29', '2024-10-28', 'returned'),
(15, 38, '2024-10-20', '2024-11-03', '2024-11-02', 'returned');

-- Sample fines (automatically created by triggers for late returns)
-- These will be created automatically by the triggers, but we can add some manual ones for testing
INSERT INTO fines (borrow_id, user_id, fine_amount, payment_status, notes) VALUES
(8, 8, 6.00, 'paid', 'Late return: 3 days overdue - Paid in cash'),
(9, 9, 4.00, 'unpaid', 'Late return: 2 days overdue'),
(12, 12, 2.00, 'paid', 'Late return: 1 day overdue - Paid online');

-- Update book availability based on current borrowings
UPDATE books SET available_copies = available_copies - 1, status = 'borrowed' WHERE book_id IN (1, 6, 11, 16, 21, 2, 7);

-- Add some additional librarian accounts for testing
-- Password for all: librarian123
INSERT INTO admins (username, password_hash, full_name, role, email, phone) VALUES
('lib001', '$2y$10$shDkJaC6f2ViP60Fe06WHOmn5p8I4SKB3M.1ImVKhH2OXBn849sXu', 'Meseret Tadesse', 'librarian', 'meseret.tadesse@epu.edu.et', '+251911666666'),
('lib002', '$2y$10$shDkJaC6f2ViP60Fe06WHOmn5p8I4SKB3M.1ImVKhH2OXBn849sXu', 'Fekadu Wolde', 'librarian', 'fekadu.wolde@epu.edu.et', '+251911777777');

-- Insert some security log entries for demonstration
INSERT INTO security_logs (event_type, user_id, ip_address, event_description) VALUES
('login_success', 1, '192.168.1.100', 'Admin successful login'),
('login_success', 2, '192.168.1.101', 'Librarian successful login'),
('login_failed', NULL, '192.168.1.102', 'Failed login attempt for username: wronguser'),
('book_added', 1, '192.168.1.100', 'New book added: Clean Code'),
('user_registered', 1, '192.168.1.100', 'New user registered: STU001'),
('book_borrowed', 3, '192.168.1.103', 'Book borrowed: Effective Java'),
('book_returned', 3, '192.168.1.103', 'Book returned: Clean Architecture'),
('fine_paid', 8, '192.168.1.104', 'Fine payment processed: 6.00 ETB');

-- Update system settings with current values
UPDATE system_settings SET setting_value = '15' WHERE setting_key = 'borrowing_period_days';
UPDATE system_settings SET setting_value = '5' WHERE setting_key = 'max_books_per_user';

-- Create some additional categories for more variety
INSERT INTO categories (category_name, description) VALUES
('Philosophy', 'Philosophy and ethics books'),
('Economics', 'Economics and finance books'),
('Political Science', 'Political science and governance books'),
('Sociology', 'Sociology and social studies books'),
('Languages', 'Language learning and linguistics books');

-- Add a few more books in the new categories
INSERT INTO books (isbn, title, author, publisher, category_id, publication_year, total_copies, available_copies) VALUES
('9780135159620', 'Introduction to Philosophy', 'John Perry', 'Oxford University Press', 11, 2020, 2, 2),
('9780134685050', 'Principles of Economics', 'N. Gregory Mankiw', 'Cengage Learning', 12, 2021, 3, 3),
('9780135957134', 'Comparative Politics', 'J. Tyler Dickovick', 'Oxford University Press', 13, 2020, 2, 2),
('9780134494241', 'Sociology: A Global Perspective', 'Joan Ferrante', 'Cengage Learning', 14, 2019, 3, 3),
('9780135188842', 'Amharic Grammar and Composition', 'Getahun Amare', 'Artistic Printing Press', 15, 2020, 4, 4);

-- Create views for common queries
CREATE VIEW active_borrowings AS
SELECT 
    br.borrow_id,
    br.borrow_date,
    br.due_date,
    br.status,
    u.full_name as borrower_name,
    u.id_number,
    u.department,
    b.title as book_title,
    b.author,
    b.isbn,
    DATEDIFF(CURDATE(), br.due_date) as days_overdue
FROM borrow_records br
JOIN users u ON br.user_id = u.user_id
JOIN books b ON br.book_id = b.book_id
WHERE br.return_date IS NULL;

CREATE VIEW overdue_books AS
SELECT 
    br.borrow_id,
    br.borrow_date,
    br.due_date,
    u.full_name as borrower_name,
    u.id_number,
    u.department,
    u.phone,
    u.email,
    b.title as book_title,
    b.author,
    b.isbn,
    DATEDIFF(CURDATE(), br.due_date) as days_overdue,
    (DATEDIFF(CURDATE(), br.due_date) * 2.00) as calculated_fine
FROM borrow_records br
JOIN users u ON br.user_id = u.user_id
JOIN books b ON br.book_id = b.book_id
WHERE br.return_date IS NULL 
AND br.due_date < CURDATE();

CREATE VIEW library_statistics AS
SELECT 
    (SELECT COUNT(*) FROM books) as total_books,
    (SELECT SUM(total_copies) FROM books) as total_book_copies,
    (SELECT COUNT(*) FROM books WHERE status = 'available') as available_books,
    (SELECT COUNT(*) FROM borrow_records WHERE return_date IS NULL) as currently_borrowed,
    (SELECT COUNT(*) FROM users WHERE status = 'active') as active_users,
    (SELECT COUNT(*) FROM borrow_records WHERE due_date < CURDATE() AND return_date IS NULL) as overdue_books,
    (SELECT COALESCE(SUM(fine_amount), 0) FROM fines WHERE payment_status = 'unpaid') as total_unpaid_fines;

-- Grant appropriate permissions (adjust as needed for your setup)
-- GRANT SELECT, INSERT, UPDATE, DELETE ON epu_library.* TO 'library_user'@'localhost';
-- FLUSH PRIVILEGES;

-- ============================================================================
-- END OF SAMPLE DATA
-- ============================================================================