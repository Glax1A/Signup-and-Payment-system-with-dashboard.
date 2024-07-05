CREATE TABLE donations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stripe_payment_intent_id VARCHAR(255) NOT NULL,
    stripe_customer_id VARCHAR(255) NOT NULL,
    amount INT NOT NULL,
    comment TEXT,
    refund_status VARCHAR(50) DEFAULT 'none',
    payment_made TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE donations 
MODIFY COLUMN refund_status ENUM('refunded', 'not_refunded') DEFAULT 'not_refunded';