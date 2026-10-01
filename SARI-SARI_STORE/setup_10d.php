<?php
require_once 'Model/database.php';

$queries = [
    "ALTER TABLE finance_approvals MODIFY COLUMN document_type ENUM('Stock Purchase', 'Payroll', 'Budget', 'Expense') NOT NULL;",
    "CREATE TABLE IF NOT EXISTS expense_categories (
        category_id INT PRIMARY KEY AUTO_INCREMENT,
        category_name VARCHAR(100) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );",
    "CREATE TABLE IF NOT EXISTS budgets (
        budget_id INT PRIMARY KEY AUTO_INCREMENT,
        reference_no VARCHAR(50) NOT NULL UNIQUE,
        department_id INT NOT NULL,
        title VARCHAR(150) NOT NULL,
        allocated_budget DECIMAL(12,2) NOT NULL,
        status ENUM('Draft', 'For Approval', 'Approved', 'Cancelled') NOT NULL DEFAULT 'Draft',
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE CASCADE
    );",
    "CREATE TABLE IF NOT EXISTS expenses (
        expense_id INT PRIMARY KEY AUTO_INCREMENT,
        reference_no VARCHAR(50) NOT NULL UNIQUE,
        budget_id INT NOT NULL,
        category_id INT NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        expense_date DATE NOT NULL,
        description TEXT,
        payee VARCHAR(150) NOT NULL,
        receipt_no VARCHAR(100),
        requested_by INT NOT NULL,
        status ENUM('Draft', 'For Approval', 'Approved', 'Paid', 'Rejected', 'Cancelled') NOT NULL DEFAULT 'Draft',
        payment_date DATE DEFAULT NULL,
        payment_method VARCHAR(50) DEFAULT NULL,
        payment_reference VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (budget_id) REFERENCES budgets(budget_id) ON DELETE CASCADE,
        FOREIGN KEY (category_id) REFERENCES expense_categories(category_id) ON DELETE CASCADE,
        FOREIGN KEY (requested_by) REFERENCES users(user_id) ON DELETE CASCADE
    );",
    "INSERT IGNORE INTO expense_categories (category_name, description) VALUES
    ('Utilities', 'Electricity, water, internet, etc.'),
    ('Office Expenses', 'Stationery, printer ink, etc.'),
    ('Transportation', 'Fuel, fare, etc.'),
    ('Maintenance', 'Repairs, cleaning, etc.'),
    ('Rent', 'Lease payments'),
    ('Operational Supplies', 'Miscellaneous store supplies'),
    ('Other', 'Uncategorized expenses');"
];

foreach ($queries as $query) {
    if (!$conn->query($query)) {
        echo "Error: " . $conn->error . "\n";
    } else {
        echo "Success\n";
    }
}
echo "Done.";
