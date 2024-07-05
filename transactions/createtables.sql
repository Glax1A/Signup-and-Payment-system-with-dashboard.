CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    stripe_customer_id VARCHAR(100),
    stripe_payment_intent_id VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);



ALTER TABLE users
ADD COLUMN description TEXT AFTER stripe_payment_intent_id;


ALTER TABLE users ADD COLUMN refund_status ENUM('not_refunded', 'refunded') DEFAULT 'not_refunded';

ALTER TABLE users ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;