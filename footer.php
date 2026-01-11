    </main>
    
    <footer class="main-footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Ethiopian Police University</h3>
                    <p>Library Management System</p>
                    <p>Empowering education through efficient library services</p>
                </div>
                
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="../about.php">About Library</a></li>
                        <li><a href="../contact.php">Contact Us</a></li>
                        <li><a href="../help.php">Help & Support</a></li>
                        <li><a href="../policies.php">Library Policies</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>Contact Information</h4>
                    <p><i class="fas fa-map-marker-alt"></i> Ethiopian Police University Campus</p>
                    <p><i class="fas fa-phone"></i> +251-941454421</p>
                    <p><i class="fas fa-envelope"></i> Mebit@epu.edu.et</p>
                </div>
                
                <div class="footer-section">
                    <h4>Library Hours</h4>
                    <p><strong>Monday - Friday:</strong> 8:00 AM - 8:00 PM</p>
                    <p><strong>Saturday:</strong> 9:00 AM - 5:00 PM</p>
                    <p><strong>Sunday:</strong> Closed</p>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Ethiopian Police University Library Management System. All rights reserved.</p>
                <p>Version <?php echo APP_VERSION; ?> | Developed for Academic Excellence</p>
            </div>
        </div>
    </footer>
    
    <!-- JavaScript files -->
    <script src="<?php echo isset($js_path) ? $js_path : '../assets/js/'; ?>main.js"></script>
    <?php if (isset($additional_js)): ?>
        <?php foreach ($additional_js as $js_file): ?>
            <script src="<?php echo isset($js_path) ? $js_path : '../assets/js/'; ?><?php echo $js_file; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>