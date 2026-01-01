ACA System – Training Sessions Module
=====================================

Database
--------
You already have the training_sessions table:

    training_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_date DATE NOT NULL,
        batch_id INT NOT NULL,
        coach_id INT,
        ground_id INT,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (batch_id) REFERENCES batches(id),
        FOREIGN KEY (coach_id) REFERENCES coaches(id),
        FOREIGN KEY (ground_id) REFERENCES grounds(id)
    );

No schema changes are required for this module.

Files
-----
admin/sessions.php
    - List of all training sessions.
    - Roles: admin, superadmin, coach.
    - Filters:
        - Batch
        - Coach (for admin/superadmin)
        - Date range.
    - Coach role automatically restricted to sessions where ts.coach_id = their coach_id.
    - Shows:
        - Date
        - Batch
        - Coach
        - Ground
        - Short notes
        - View / Edit links.
    - "Add Session" button.

admin/session-add.php
    - Roles: admin, superadmin, coach.
    - Creates a new training session.
    - Fields:
        - Date (default today)
        - Batch
        - Coach (auto-selected for coach role)
        - Ground (optional)
        - Session notes (free text describing drills, focus, etc.)

admin/session-view.php
    - Detailed view of a single training session.
    - Shows:
        - Date
        - Batch
        - Coach
        - Ground
        - Created at
        - Full notes.
    - Coach role can only view their own sessions.

admin/session-edit.php
    - Edit an existing session.
    - Roles: admin, superadmin, coach.
    - Coach role can only edit their own sessions.
    - Can change:
        - Date
        - Batch
        - Coach
        - Ground
        - Notes.

student/my-sessions.php
    - Student-facing list of recent sessions for THEIR batch.
    - Resolves student via users.student_id -> students.id -> students.batch_id.
    - Shows last 50 sessions for that batch, ordered newest first.
    - Columns:
        - Date
        - Coach
        - Ground
        - Short summary (notes).

Navigation
----------
1) Admin / Coach sidebar (admin/includes/sidebar.php)
   Under the Academy or Coach section, add:

       <li><a href="sessions.php">📘 Training Sessions</a></li>

2) Student navigation
   Where you link to My Attendance / My Fees / My Evaluations / My Injuries / Suggestions, also link:

       /student/my-sessions.php

Usage
-----
- Coaches should record at least one training session per batch per day with a clear summary of what was covered.
- Students and parents can then review the sessions from the student portal to understand the training progress.
