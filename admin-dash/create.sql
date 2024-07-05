CREATE TABLE admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'ultimate_admin') NOT NULL,
    can_send_emails BOOLEAN DEFAULT FALSE,
    can_view_stripe BOOLEAN DEFAULT FALSE,
    can_view_db BOOLEAN DEFAULT FALSE
);