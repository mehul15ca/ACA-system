ACA System – Player Evaluation Module
=====================================

Database update (IMPORTANT)
---------------------------
Run this SQL in phpMyAdmin on your aca_system database:

ALTER TABLE player_evaluation
    ADD COLUMN fitness_rating TINYINT NULL AFTER fielding_rating,
    ADD COLUMN discipline_rating TINYINT NULL AFTER fitness_rating,
    ADD COLUMN attitude_rating TINYINT NULL AFTER discipline_rating,
    ADD COLUMN technique_rating TINYINT NULL AFTER attitude_rating,
    ADD COLUMN overall_score DECIMAL(4,2) NULL AFTER technique_rating;

Files in this module
--------------------
admin/evaluations.php
    - List of all evaluations (filters by student, coach, date, status)
    - Coaches only see their own evaluations
    - Admin/Superadmin see all
    - "New Evaluation" button

admin/evaluation-add.php
    - Create new evaluation
    - Categories: Batting, Bowling, Fielding, Fitness, Discipline, Attitude, Technique (1–10)
    - Notes (free text)
    - Status: draft/final
    - Overall score auto-calculated as average of numeric fields

admin/evaluation-view.php
    - View full evaluation
    - Print/PDF friendly mode via ?print=1
    - Students can also view their own evaluations

admin/evaluation-edit.php
    - Edit existing evaluation
    - Recalculates overall score

student/my-evaluations.php
    - Student can see all their evaluations
    - Summary table
    - Progress graph (Chart.js) per category over time

Sidebar link
------------
In admin/includes/sidebar.php, under Coach or Academy section, add:

<li><a href="evaluations.php">⭐ Evaluations</a></li>

In student navigation (where you link My Fees / My Attendance), add link:

/student/my-evaluations.php
