ACA System – Injury Reports Module
==================================

Database
--------
You already created the injury_reports table earlier:

    injury_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        coach_id INT,
        incident_date DATE NOT NULL,
        reported_at DATETIME NOT NULL,
        severity ENUM('minor','moderate','serious','critical') DEFAULT 'minor',
        injury_area VARCHAR(100),
        notes TEXT,
        action_taken TEXT,
        status ENUM('open','pending','closed') DEFAULT 'open',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id),
        FOREIGN KEY (coach_id) REFERENCES coaches(id)
    );

No additional schema changes are required for this module.

Files
-----
admin/injuries.php
    - List of all injury reports
    - Filters: student, severity, status, incident date range
    - Coach role: only sees their own reports
    - Admin / Superadmin: see all
    - "New Injury Report" button

admin/injury-add.php
    - Create a new injury report
    - Fields:
        - Student
        - Coach (reporting)
        - Incident date
        - Reported at
        - Severity (minor/moderate/serious/critical)
        - Injury area
        - Notes (what happened)
        - Action taken (treatment / rest plan / referral)
        - Status (open/pending/closed)

admin/injury-view.php
    - Detailed view of a single report
    - Print/PDF mode via ?print=1 for sharing with parents or for records
    - Students can also view their own injuries through this endpoint (access controlled)

admin/injury-edit.php
    - Edit any field (including status and action taken)
    - Coach can only edit their own reports

student/my-injuries.php
    - Student-facing injury history page
    - Shows list of all injuries for that student with:
        - Date
        - Coach
        - Area
        - Severity (color-coded pill)
        - Status (open/pending/closed)
        - Short notes

Sidebar / Navigation
--------------------
1) Admin / Coach sidebar (admin/includes/sidebar.php)
   Under a suitable section (e.g. Academy or Coach), add:

       <li><a href="injuries.php">🚑 Injury Reports</a></li>

2) Student navigation
   Where you link to My Attendance / My Fees / My Evaluations, also link:

       /student/my-injuries.php

Print / PDF
-----------
The print mode is designed to be used with the browser's "Print" → "Save as PDF".
Open:

    admin/injury-view.php?id=INJURY_ID&print=1

and then save as a PDF for parents, doctors, or your own records.
