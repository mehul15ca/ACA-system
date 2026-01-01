ACA System – Top 5 Players Highlight Module
===========================================

This module adds:

1) Admin management pages to manually select up to 5 top players
   from active students, with:
   - Rank (1–5)
   - Student
   - Short highlight/description

2) A public-facing widget to display the Top 5 Players on your
   academy homepage (or any page you include it on), showing:
   - Player photo (from students.profile_photo_drive_id placeholder)
   - Full name
   - Batch name
   - Short description (e.g. "Player of the Month")
   - Link to detailed profile (view-student.php?id=...)

---------------------------------
1) DATABASE – NEW TABLE
---------------------------------

Run this SQL in phpMyAdmin on your `aca_system` database:

    CREATE TABLE top_players (
        id INT AUTO_INCREMENT PRIMARY KEY,
        rank_position TINYINT NOT NULL,
        student_id INT NOT NULL,
        highlight_text VARCHAR(255),
        active TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_topplayers_student FOREIGN KEY (student_id)
            REFERENCES students(id)
    );

Notes:
- rank_position: 1 = top spot, 2 = second, ... 5 = fifth.
- You can keep less than 5 if you like (leave some slots empty).

---------------------------------
2) FILES ADDED
---------------------------------

admin/top-players.php
    - Lists current Top Players (if any).
    - Shows:
        - Rank
        - Student name
        - Batch
        - Highlight text
    - Button to "Edit Top Players" that goes to top-players-edit.php.
    - Access: admin + superadmin.

admin/top-players-edit.php
    - Simple form with 5 rows (Rank 1–5).
    - Each row:
        - Dropdown of active students (first_name + last_name + batch)
        - Short text input for highlight/description.
    - On Save:
        - Clears existing top_players rows.
        - Inserts up to 5 new rows for ranks that have a student selected.

public-top-players.php
    - Public-facing widget.
    - Can be included in your public homepage, for example:
          <?php include "public-top-players.php"; ?>
    - Does NOT require login.
    - Reads top_players + students + batches and displays cards:
        - Player photo (uses students.profile_photo_drive_id as placeholder:
          if non-empty, treat it as Google Drive file ID and build view URL:
            https://drive.google.com/uc?export=view&id=FILE_ID
          else show a simple coloured placeholder circle with initials.)
        - Full name
        - Batch name
        - Highlight text
        - "View Profile" link that points to:
            admin/view-student.php?id=STUDENT_ID
          (You can later change this to a public profile URL if needed.)

---------------------------------
3) STYLING / THEME
---------------------------------

The widget uses a dark theme to match the existing ACA admin + portal style:
- Dark background
- Soft card borders
- Rounded corners
- Simple responsive layout (2 columns on larger screens, 1 column on mobile)

You can adjust the CSS inside public-top-players.php if you want to match
your eventual public site theme exactly.

---------------------------------
4) HOW TO USE
---------------------------------

1) Create the table:

   - In phpMyAdmin, select the `aca_system` database.
   - Open the SQL tab.
   - Paste and run the CREATE TABLE statement from section 1.

2) Copy the files:

   - Extract this ZIP into your ACA-System root:
        C:\xampp\htdocs\ACA-System\
   - Make sure these files end up at:
        admin/top-players.php
        admin/top-players-edit.php
        public-top-players.php

3) Add a link in the admin sidebar:

   In admin/includes/sidebar.php, under a suitable section
   (e.g., "Academy"), add:

       <li><a href="top-players.php">⭐ Top Players</a></li>

4) Set your Top 5:

   - Log in as admin or superadmin.
   - Go to: /admin/top-players.php
   - Click "Edit Top Players".
   - For each rank (1–5):
       - Select a student (optional; leave blank if not needed)
       - Enter a short highlight (e.g. "Player of the Month – December")
   - Save.

5) Show on homepage:

   - In your public homepage PHP (e.g., index.php of academy site),
     include the widget:

       <?php include "public-top-players.php"; ?>

   - The widget will automatically load the top_players list and display
     up to 5 player cards.

---------------------------------
5) FUTURE IMPROVEMENTS (OPTIONAL)
---------------------------------

Later we can:
- Add "effective_from" and "effective_to" dates to top_players to
  maintain historical records.
- Auto-generate candidates based on evaluations, matches, or stats,
  and let admin confirm.
- Create public-friendly profile pages instead of admin URLs.

For now, this module gives you a clean, manual, admin-controlled
Top 5 highlight that is easy to manage and looks professional.
