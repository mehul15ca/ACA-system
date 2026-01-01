ACA System – Suggestions / Feedback Module
==========================================

Database update (IMPORTANT)
---------------------------
Run this SQL in phpMyAdmin on your aca_system database:

ALTER TABLE suggestions
    ADD COLUMN drive_file_id VARCHAR(255) NULL AFTER status;

This allows each suggestion to store a Google Drive file ID for attachments
(screenshots, documents, etc.).

Files in this module
--------------------
admin/suggestions.php
    - Admin/Superadmin list of all suggestions
    - Filters:
        - Student (name or admission no.)
        - Status (open/closed)
        - Date range
    - Shows:
        - Date
        - Student
        - Status
        - Short message
        - Attachment link (if provided)
    - Admin only (no coaches/students), matching Q3: Admin only view

admin/suggestion-view.php
    - Detailed view of one suggestion
    - Shows full text + created date + status
    - If drive_file_id is set, shows a "Open in Google Drive" link
    - Button to toggle status:
        - open → closed
        - closed → open

admin/suggestion-edit.php
    - Admin can:
        - Change student
        - Change date
        - Edit message
        - Change status (open/closed)
        - Add / edit Google Drive file ID

admin/suggestion-add.php
    - Accessible to Admin, Superadmin, Coach
    - Used when staff want to log a suggestion/feedback on behalf of a student
    - Fields:
        - Student
        - Date
        - Message
        - Status
        - Optional Google Drive file ID

student/suggestion-submit.php
    - Student-facing suggestion form
    - Role: student only
    - Allows:
        - Date (defaults to today)
        - Suggestion text
        - Optional Google Drive file ID
    - Status is always created as "open"
    - Student does NOT see a list of old suggestions (admin-only view),
      which matches Q3: suggestions list is for Admin only.

Navigation / Sidebar
--------------------
1) Admin sidebar (admin/includes/sidebar.php)
   Under a suitable section (e.g., System or Academy), add:

       <li><a href="suggestions.php">💬 Suggestions</a></li>

2) Student navigation
   Where you link to My Attendance / My Fees / My Evaluations / My Injuries, also link:

       /student/suggestion-submit.php

Google Drive usage
------------------
This module does NOT integrate with the Drive API directly.
Instead, it stores a "Drive File ID" so you can:

- Create the file/folder manually in Google Drive
- Paste its file ID into the suggestion
- Open it from admin pages via:
  https://drive.google.com/file/d/FILE_ID/view

This matches:
- Q5: "Yes → store in Google Drive" (via file ID), keeping implementation simple for now.
