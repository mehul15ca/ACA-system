ACA System – Coach Salary Module
================================

This module manages:
- Coach salary rates (per session / per hour / per month)
- Automatic salary lines based on Training Sessions
- Monthly salary summary per coach
- Marking sessions as PAID / UNPAID
- Coach view of their own paid/unpaid sessions

It uses your existing `training_sessions`, `coaches`, `batches`, `batch_schedule` tables.

-------------------------------------------------
1) DATABASE – NEW TABLES (RUN THESE SQL QUERIES)
-------------------------------------------------

Run these in phpMyAdmin on your `aca_system` database.

1.1) Coach Salary Rates
-----------------------

Each row defines how a coach is paid, optionally per batch.

    CREATE TABLE coach_salary_rates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        coach_id INT NOT NULL,
        batch_id INT NULL,
        rate_type ENUM('per_session','per_hour','per_month') NOT NULL,
        rate_amount DECIMAL(10,2) NOT NULL,
        currency VARCHAR(10) DEFAULT 'CAD',
        effective_from DATE DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_csr_coach FOREIGN KEY (coach_id) REFERENCES coaches(id),
        CONSTRAINT fk_csr_batch FOREIGN KEY (batch_id) REFERENCES batches(id)
    );

Notes:
- If batch_id IS NULL -> this is a "global" default rate for that coach.
- If batch_id IS NOT NULL -> this rate overrides for that specific batch.
- rate_type:
    - per_session: rate per completed training session (fixed amount)
    - per_hour: rate per hour (duration taken from batch_schedule start/end)
    - per_month: fixed monthly amount (1 line per month)

1.2) Coach Salary Sessions
--------------------------

Each row is a **payable unit** (mostly 1 training session per row).

    CREATE TABLE coach_salary_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        coach_id INT NOT NULL,
        session_id INT DEFAULT NULL,
        batch_id INT NOT NULL,
        session_date DATE NOT NULL,
        hours DECIMAL(6,2) NOT NULL DEFAULT 0,
        rate_type ENUM('per_session','per_hour','per_month') NOT NULL,
        rate_amount DECIMAL(10,2) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        month INT NOT NULL,
        year INT NOT NULL,
        status ENUM('unpaid','paid') DEFAULT 'unpaid',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_css_coach FOREIGN KEY (coach_id) REFERENCES coaches(id),
        CONSTRAINT fk_css_batch FOREIGN KEY (batch_id) REFERENCES batches(id)
    );

Notes:
- For per_session and per_hour:
    - One row per training_sessions.id
    - session_id = that training_sessions.id
- For per_month:
    - One synthetic row per month per rate
    - session_id = NULL
    - session_date = last day of that month
- status:
    - unpaid: waiting to be paid
    - paid: already included in a salary payout

-------------------------------------------------
2) FILES ADDED
-------------------------------------------------

admin/salary-rates.php
    - Admin view of all coach salary rates.
    - Add new rate entries.
    - For each rate:
        - Coach
        - Optional batch
        - rate_type (per_session / per_hour / per_month)
        - rate_amount

admin/salary.php
    - Monthly salary summary.
    - Filters:
        - Month / Year
    - For each coach:
        - Total sessions (rows from coach_salary_sessions)
        - Total hours
        - Total amount
        - Unpaid amount
        - Link to view details
        - Button: "Mark all unpaid as PAID" for that coach & month.

admin/salary-details.php
    - Details for a single coach and month/year.
    - Shows each salary line:
        - Date
        - Batch
        - Rate Type
        - Hours
        - Rate
        - Amount
        - Status (unpaid/paid)

admin/salary-generate.php
    - Generates salary lines for a given month & year.
    - Logic:
        - For each coach:
            - Per Session / Per Hour:
                - Read training_sessions WHERE session_date in that month AND coach_id matches.
                - If coach_salary_sessions does NOT already have a record for that session_id:
                    - Find salary rate:
                        1) batch-specific rate (coach_id + batch_id)
                        2) fallback to global coach rate (coach_id + batch_id IS NULL)
                    - If no rate found -> skip.
                    - If per_session -> amount = rate_amount, hours=0.
                    - If per_hour -> hours = sum of (end_time - start_time) hours from batch_schedule
                                      for that batch, coach, and weekday.
                                  amount = hours * rate_amount.
                    - Insert into coach_salary_sessions.
            - Per Month:
                - For each coach_salary_rates with rate_type='per_month':
                    - If NOT already a coach_salary_sessions row for that coach + month + year + rate_type='per_month':
                        - Insert a single row:
                            - session_id = NULL
                            - batch_id = IFNULL(batch_id, 0) (0 used when global)
                            - hours = 0
                            - amount = rate_amount
                            - session_date = LAST_DAY of month
                            - status = 'unpaid'.

coach/salary-sessions.php
    - Coach view of their own salary lines.
    - Filter by month/year.
    - Shows:
        - Date
        - Batch
        - Rate type
        - Hours
        - Amount
        - Status (Paid / Unpaid)
    - This is what you can link from coach dashboard, and it will show:
        - Completed sessions and whether they are paid or unpaid.

-------------------------------------------------
3) HOW TO USE THE MODULE
-------------------------------------------------

Step 1: Setup salary rates
--------------------------

Go to:
    /admin/salary-rates.php

For each coach, add one or more rates:

Examples:
- Coach A - Global rate:
    - rate_type = per_session
    - rate_amount = 40.00

- Coach A - U14 batch specific rate:
    - coach_id = Coach A
    - batch_id = U14 batch id
    - rate_type = per_session
    - rate_amount = 50.00

- Coach B - per hour:
    - rate_type = per_hour
    - rate_amount = 25.00

- Coach C - monthly:
    - rate_type = per_month
    - rate_amount = 1200.00

Rule:
- Batch-specific rate overrides global coach rate.

Step 2: Generate salary lines for a month
-----------------------------------------

Go to:
    /admin/salary.php

At the top, there's a button:
    "Generate / Refresh Salary Lines for this Month"

This will:
- Read all training_sessions in that month.
- Create salary lines in coach_salary_sessions
  for sessions not already processed.
- Create monthly fixed lines for rate_type = 'per_month'
  if not already present.

You can run this multiple times safely (it will not duplicate existing session_ids).

Step 3: Review and pay
----------------------

Still on:
    /admin/salary.php

- For each coach, you will see:
    - Total Amount
    - Unpaid Amount
    - Total Sessions
    - Total Hours
    - "View details"
    - "Mark all unpaid as PAID" (for that coach & month)

When you click "Mark all unpaid as PAID", it will:
- UPDATE coach_salary_sessions
- SET status = 'paid'
- WHERE coach_id = ? AND month = ? AND year = ? AND status='unpaid'.

This matches your requirement:
- "New schedules go into unpaid."
- "As soon as admin approves the pay, all unpaid schedules under that salary get updated to paid."

-------------------------------------------------
4) COACH VIEW – Paid/Unpaid
-------------------------------------------------

Go to:
    /coach/salary-sessions.php

- The coach can see:
    - All sessions for a given month (training + monthly fixed)
    - Paid vs Unpaid
- This page can later be linked directly from your coach dashboard.

-------------------------------------------------
5) NOTES / ASSUMPTIONS
-------------------------------------------------

1) Training Sessions as the source of truth
   - We assume that a training session only exists in training_sessions
     if it actually happened (not cancelled).
   - Therefore, salary is only generated for real sessions.

2) Hours calculation (per_hour)
   - Uses batch_schedule for that batch, coach, and weekday of session_date.
   - If multiple schedules match the same day, it sums their durations.
   - If no schedule is found, the hours = 0 and the line is skipped.

3) Monthly salary (per_month)
   - Creates one line per month per rate.
   - You can see it as a fixed monthly salary entry.

4) PDF Salary Slip
   - A separate file (salary-slip.php) can be added later to render a
     branded slip for each coach and month.
   - For now, the main focus is on correct data and paid/unpaid logic.

