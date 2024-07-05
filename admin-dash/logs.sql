CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    timestamp DATETIME,
    action VARCHAR(255),
    details TEXT
);

-- Will continue logging aspect later