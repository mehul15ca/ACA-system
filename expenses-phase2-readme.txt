ACA System – Expense Tracking Dashboard (Phase 2)
=================================================

This module upgrades your existing Expenses system with:

1) Fixed categories:
   - Salary, Rent, Equipment, Merchandise, Marketing, Misc

2) Tax support:
   - tax_amount column on each expense
   - total_amount (amount + tax)

3) Optional receipts:
   - Optional upload field for a receipt
   - Stores local filename for now, but ready for Google Drive integration later
   - Also creates an entry in `documents` table (owner_type = 'system')

4) Recurring expenses:
   - Define recurring expenses (e.g. monthly rent, subscriptions)
   - Daily cron script creates instances in `expenses` table

5) Expense & Income dashboard:
   - Monthly and yearly summaries
   - Category-wise breakdown
   - Profit vs Expense comparison using:
        * Income from fees_payments
        * Income from store_orders
   - Charts using Chart.js (via CDN)

6) Export:
   - Printable monthly/yearly reports (for PDF via browser)
   - CSV export for expenses


---------------------------------
1) DATABASE – ALTER & NEW TABLES
---------------------------------

Run these SQL statements in phpMyAdmin on `aca_system`:

1.1) Ensure fixed categories (for reference)
-------------------------------------------
(If your expenses table already uses a TEXT/VARCHAR 'category' column,
continue to use it. The below is just a reference list.)

Categories to use in the expense forms:

   Salary
   Rent
   Equipment
   Merchandise
   Marketing
   Misc


1.2) Extend existing `expenses` table
-------------------------------------

NOTE: If your `expenses` table uses different column names, adjust these.
Assuming your table is named `expenses` and already has:

    id (INT, PK)
    expense_date (DATE or DATETIME)
    category (VARCHAR)
    amount (DECIMAL)
    description (TEXT/VARCHAR)
    status (e.g. 'paid','pending')

Run:

    ALTER TABLE expenses
        ADD COLUMN tax_amount DECIMAL(10,2) DEFAULT 0 AFTER amount,
        ADD COLUMN total_amount DECIMAL(10,2) DEFAULT 0 AFTER tax_amount,
        ADD COLUMN receipt_file VARCHAR(255) DEFAULT NULL AFTER total_amount,
        ADD COLUMN is_recurring_instance TINYINT(1) DEFAULT 0 AFTER receipt_file,
        ADD COLUMN recurring_id INT DEFAULT NULL AFTER is_recurring_instance;


1.3) Recurring expenses definition table
----------------------------------------

    CREATE TABLE recurring_expenses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(150) NOT NULL,
        category VARCHAR(50) NOT NULL,
        base_amount DECIMAL(10,2) NOT NULL,
        tax_amount DECIMAL(10,2) DEFAULT 0,
        total_amount DECIMAL(10,2) DEFAULT 0,
        frequency ENUM('monthly') DEFAULT 'monthly',
        day_of_month TINYINT NOT NULL, -- e.g. 1 = 1st of every month
        next_run_date DATE NOT NULL,
        status ENUM('active','paused') DEFAULT 'active',
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );


---------------------------------
2) FILES ADDED
---------------------------------

admin/expenses-dashboard.php
    - Main analytics page for expenses & income.
    - Shows:
        * Total expenses this month / this year
        * Total income (fees + store) this month / this year
        * Net result (profit/loss) for selected period
        * Category-wise breakdown
        * Charts (Chart.js) for:
            - Monthly expenses
            - Income vs Expenses
            - Category-wise pie

admin/recurring-expenses.php
    - List + manage recurring expenses (admin + superadmin).
    - Add / edit / pause recurring templates.

admin/recurring-expenses-edit.php
    - Add/Edit form for a single recurring expense.

cron/recurring-expenses-cron.php
    - To be run daily.
    - For each active recurring_expenses where next_run_date <= today:
        * Creates a new row in `expenses` table
        * Marks is_recurring_instance = 1, recurring_id pointing to template
        * Sets next_run_date to next month (same day_of_month, with safe handling)

admin/expenses-export.php
    - Provides:
        * Printable monthly/yearly expense report (for PDF export via browser)
        * CSV export for expenses within a date range


---------------------------------
3) HOW TO INSTALL
---------------------------------

1) Run the SQL in Section 1.

2) Extract this ZIP into your ACA-System root:

    C:\xampp\htdocs\ACA-System\

   Ensure files go to:
    - admin/expenses-dashboard.php
    - admin/recurring-expenses.php
    - admin/recurring-expenses-edit.php
    - admin/expenses-export.php
    - cron/recurring-expenses-cron.php

3) Add links in admin sidebar (admin/includes/sidebar.php), under
   a suitable section (e.g., Finance):

    <li class="sidebar-section">Finance</li>
    <li><a href="fees.php">💳 Fees &amp; Invoices</a></li>
    <li><a href="expenses.php">💸 Expenses (basic)</a></li>
    <li><a href="expenses-dashboard.php">📊 Expense Dashboard</a></li>
    <li><a href="recurring-expenses.php">♻ Recurring Expenses</a></li>

4) Test recurring cron locally:

   Visit this in your browser:

       http://localhost/ACA-System/cron/recurring-expenses-cron.php

   On hosting (GoDaddy), configure a daily cron:
       php /home/USER/public_html/ACA-System/cron/recurring-expenses-cron.php

   or use a HTTP-based cron call.


---------------------------------
4) ASSUMPTIONS ABOUT EXISTING TABLES
---------------------------------

fees_payments:
    - Table used earlier in your fees module.
    - Assumed essential columns:
        id INT PK
        amount DECIMAL(10,2)
        currency VARCHAR(10)
        paid_on DATETIME

store_orders:
    - From the merchandise store module.
    - Assumed essential columns:
        id INT PK
        total_amount DECIMAL(10,2)
        currency VARCHAR(10)
        status VARCHAR(20)   -- e.g. 'paid','delivered','completed'
        created_at DATETIME

If your column names differ, adjust the SELECTs in expenses-dashboard.php
accordingly.


---------------------------------
5) EXPORT / PDF
---------------------------------

The "PDF export" is implemented as a printable HTML report:
you can press CTRL+P (or browser menu → Print → Save as PDF)
to generate a PDF file.

CSV export is real CSV with appropriate headers for import into Excel.

---------------------------------
6) PERMISSIONS
---------------------------------

All new pages are restricted to:

    role IN ('admin','superadmin')

Students and coaches have no access to these finance dashboards.


