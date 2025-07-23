# Cafe Management System

## Features
- Table-wise billing
- Regular customer management
- Menu management
- Inventory purchase logging
- Dashboard with sales and credit overview
- Sales and inventory reports (CSV/PDF export)
- User authentication (admin/staff)
- Daily attendance tracking for staff and admin
- Attendance report with employee/date filters
- Admin panel for user and system management
- Create staff and admin users

## Tech Stack
- PHP
- MySQL
- HTML, CSS, Bootstrap, JavaScript

## Setup Instructions
1. Import the provided SQL file into your MySQL server (includes all tables: users, customers, menu, bills, attendance, etc.).
2. Configure database credentials in `config.php`.
3. Place all files in your web server directory (e.g., `htdocs` for XAMPP).
4. Access the system via your browser at `/possystem/index.php`.

---

The following files and folders will be created:
- `config.php` (database connection)
- `db/` (SQL schema)
- `public/` (entry points: index, dashboard, login, etc.)
- `admin/` (menu, inventory, customer, staff, admin, attendance management)
- `billing/` (table-wise billing)
- `reports/` (sales, inventory, attendance reports)
- `assets/` (CSS, JS, Bootstrap)

---

This system is designed for a cafe environment with table-wise billing, customer credit, daily attendance, and inventory tracking. For customization, edit the relevant PHP files or database schema as needed.