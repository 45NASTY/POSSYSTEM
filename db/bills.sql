-- Rent bills table
CREATE TABLE IF NOT EXISTS rent_bills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    amount DECIMAL(10,2) NOT NULL,
    month INT NOT NULL,
    year INT NOT NULL,
    paid_at DATETIME NOT NULL
);

-- Electricity bills table
CREATE TABLE IF NOT EXISTS electricity_bills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    amount DECIMAL(10,2) NOT NULL,
    month INT NOT NULL,
    year INT NOT NULL,
    paid_at DATETIME NOT NULL
);

-- Other bills table
CREATE TABLE IF NOT EXISTS other_bills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    month INT NOT NULL,
    year INT NOT NULL,
    paid_at DATETIME NOT NULL
);
