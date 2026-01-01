ACA System – Expenses Module
============================

This module lets Admin track academy expenses and compare them with fee income.

It includes:
- Add / edit / view expenses
- Filter by date range, category, payment method
- Summary totals:
    - Total expenses in range
    - Total fee income in range (from fees_payments)
    - Net result = income - expense

---------------------------------
1) DATABASE – NEW TABLE
---------------------------------

Run this SQL in phpMyAdmin on your `aca_system` database:

    CREATE TABLE expenses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        expense_date DATE NOT NULL,
        category VARCHAR(100) NOT NULL,
        subcategory VARCHAR(100) DEFAULT NULL,
        amount DECIMAL(10,2) NOT NULL,
        currency VARCHAR(10) DEFAULT 'CAD',
        payment_method VARCHAR(50) DEFAULT NULL,
        vendor VARCHAR(150) DEFAULT NULL,
        notes TEXT,
        receipt_drive_id VARCHAR(255),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

This will create a simple expenses table.

---------------------------------
2) FILES ADDED
---------------------------------

admin/expenses.php
    - List of all expenses with filters.
    - Filters:
        - Date from / to
        - Category
        - Payment method
    - Summary cards at the top:
        - Total Expenses in selected period
        - Total Fee Income in selected period (from fees_payments)
        - Net: Income - Expense
    - Table columns:
        - Date
        - Category / Subcategory
        - Amount
        - Payment Method
        - Vendor
        - Notes (short)
        - Actions (View / Edit)
    - "Add Expense" button.

admin/expense-add.php
    - Form to add a new expense.
    - Fields:
        - Date (default today)
        - Category (Rent, Utilities, Salary, Equipment, Events, Travel, Other)
        - Subcategory (optional free text)
        - Amount
        - Currency (default CAD)
        - Payment Method (Cash, Bank Transfer, Card, e-Transfer, Other)
        - Vendor (optional)
        - Notes
        - Google Drive Receipt File ID (optional)

admin/expense-edit.php
    - Same as Add but pre-filled.
    - Allows admin to correct existing expenses.

admin/expense-view.php
    - Read-only view of one expense.
    - Shows all fields.
    - If Receipt Drive ID present, shows a link:
        https://drive.google.com/file/d/FILE_ID/view

---------------------------------
3) NAVIGATION
---------------------------------

In admin/includes/sidebar.php, under a suitable section (e.g. Finance), add:

    <li><a href="expenses.php">💸 Expenses</a></li>

---------------------------------
4) HOW SUMMARY INCOME IS CALCULATED
---------------------------------

This module assumes you already have:

    fees_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        currency VARCHAR(10) DEFAULT 'CAD',
        paid_on DATETIME NOT NULL,
        method VARCHAR(50),
        reference VARCHAR(100),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (invoice_id) REFERENCES fees_invoices(id)
    );

For the selected date range:
- Total Income = SUM(amount) where DATE(paid_on) between from and to.
- Only currency 'CAD' is considered.

If no date range is selected, it uses:
- From = 1st day of current month
- To   = Today

---------------------------------
5) ROLES
---------------------------------

Only admin and superadmin can access:

- admin/expenses.php
- admin/expense-add.php
- admin/expense-edit.php
- admin/expense-view.php

Coaches and students cannot access these pages.
