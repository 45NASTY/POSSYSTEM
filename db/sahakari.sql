-- Sahakari main table
CREATE TABLE IF NOT EXISTS sahakari (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL
);

-- Sahakari investments
CREATE TABLE IF NOT EXISTS sahakari_investments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sahakari_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (sahakari_id) REFERENCES sahakari(id) ON DELETE CASCADE
);

-- Sahakari withdrawals
CREATE TABLE IF NOT EXISTS sahakari_withdrawals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sahakari_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (sahakari_id) REFERENCES sahakari(id) ON DELETE CASCADE
);
