-- Sample data for Cafe Management System

-- Users
INSERT INTO users (username, password, role) VALUES
('admin', '$2y$10$eImG6h7h1uQJQ6rQJQ6rQeImG6h7h1uQJQ6rQJQ6rQJQ6rQJQ6rQ6', 'admin'),
('staff1', '$2y$10$eImG6h7h1uQJQ6rQJQ6rQeImG6h7h1uQJQ6rQJQ6rQJQ6rQJQ6rQ6', 'staff');
-- password: admin

-- Tables
INSERT INTO tables (table_number, status) VALUES
('T1', 'available'),
('T2', 'occupied'),
('T3', 'available');

-- Customers
INSERT INTO customers (name, phone, email, credit_limit, pending_credit, created_at) VALUES
('Aashutosh Regmi', '9861838468', 'work.aashutoshregmi@gmail.com', 10000.00, 5047.00, '2025-07-01 10:00:00'),
('John Doe', '9800000001', 'john.doe@example.com', 5000.00, 1200.00, '2025-07-02 11:00:00'),
('Jane Smith', '9800000002', 'jane.smith@example.com', 7500.00, 0.00, '2025-07-03 12:00:00');

-- Menu Categories
INSERT INTO menu_categories (name) VALUES
('Beverages'),
('Snacks'),
('Main Course');

-- Menu Items
INSERT INTO menu_items (name, category_id, price) VALUES
('Coffee', 1, 120.00),
('Tea', 1, 100.00),
('Sandwich', 2, 200.00),
('Burger', 2, 250.00),
('Pizza', 3, 500.00);

-- Bills
INSERT INTO bills (table_id, customer_id, total_amount, status, payment_type, created_at, closed_at) VALUES
(1, 1, 5047.00, 'closed', 'credit', '2025-07-01 10:30:00', '2025-07-01 11:00:00'),
(2, 2, 1200.00, 'closed', 'offline', '2025-07-02 12:00:00', '2025-07-02 12:30:00'),
(3, 3, 800.00, 'open', NULL, '2025-07-03 13:00:00', NULL);

-- Bill Items
INSERT INTO bill_items (bill_id, menu_item_id, quantity, price) VALUES
(1, 1, 10, 120.00),
(1, 3, 5, 200.00),
(2, 2, 12, 100.00),
(2, 4, 2, 250.00),
(3, 5, 1, 500.00);

-- Inventory Purchases
INSERT INTO inventory_purchases (item_name, quantity, total_price, payment_type, purchased_at) VALUES
('Coffee Beans', 10, 1200.00, 'cash', '2025-07-01'),
('Bread', 20, 400.00, 'online', '2025-07-02');

-- Credit Transactions
INSERT INTO credit_transactions (customer_id, bill_id, amount, transaction_date) VALUES
(1, 1, 5047.00, '2025-07-01 11:05:00'),
(2, 2, 1200.00, '2025-07-02 12:35:00');

-- Restaurant Details
INSERT INTO restaurant_details (name, address, phone, pan_vat) VALUES
('Cafe POS', 'Kathmandu, Nepal', '9800000000', '123456789');

-- Attendance
INSERT INTO attendance (user_id, date) VALUES
(1, '2025-07-01'),
(2, '2025-07-01'),
(1, '2025-07-02'),
(2, '2025-07-02'),
(1, '2025-07-03');






