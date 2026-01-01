ACA System – Student Self-Registration & Waiver Module
======================================================

This package adds:

1) Admin side:
   - admin/student-invite.php
     * Superadmin + Admin can:
       - Generate a Student Registration link
       - Select Batch
       - Enter student email
       - System stores a registration token

2) Public / Student side:
   - student-register.php
     * Student opens link with ?token=...
     * Fills personal details
     * Accepts waiver
     * (Signature UI placeholder)
     * Profile photo upload (hook for Google Drive)
     * On submit:
       - Creates student row
       - Creates users row with random temporary password
       - Marks token as completed
       - Queues email to student (with password + login link)
       - Queues email to superadmin to notify

   - student-register-success.php
     * Simple success page.

3) Database:
   Run these statements in phpMyAdmin on `aca_system`:

   3.1) Registration tokens

       CREATE TABLE registration_tokens (
           id INT AUTO_INCREMENT PRIMARY KEY,
           token VARCHAR(100) NOT NULL UNIQUE,
           email VARCHAR(150) NOT NULL,
           batch_id INT NOT NULL,
           invited_by_user_id INT NOT NULL,
           status ENUM('pending','completed','cancelled') DEFAULT 'pending',
           created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
           completed_at DATETIME DEFAULT NULL,
           CONSTRAINT fk_regtoken_batch FOREIGN KEY (batch_id) REFERENCES batches(id),
           CONSTRAINT fk_regtoken_user FOREIGN KEY (invited_by_user_id) REFERENCES users(id)
       );

   3.2) Add waiver template Doc ID to google_settings

       ALTER TABLE google_settings
           ADD COLUMN waiver_template_doc_id VARCHAR(255) DEFAULT NULL;

   This module assumes you will store your Google Docs Waiver Template ID
   into google_settings. For example, one row in google_settings with
   root_folder_id set and waiver_template_doc_id set.

4) Google Docs + Drive Integration
----------------------------------

This module DOES NOT implement live calls to Google APIs, because those
require service accounts, credentials and external libraries.

Instead, there is a helper function in student-register.php:

   createStudentWaiverFromTemplate($conn, $studentId, $studentData)

You should:

 - Implement this function using Google Drive + Docs API (PHP client)
 - Steps:
     1) Read waiver_template_doc_id from google_settings
     2) Copy that Google Doc to a new file under the student's folder
     3) Replace placeholders like:
        {{STUDENT_NAME}}, {{STUDENT_ID}}, {{DATE}}, {{PARENT_NAME}}, etc.
     4) Export that Doc as PDF and store on Drive
     5) Get the Drive File ID of the PDF
     6) Insert into `documents` table with:
        owner_type = 'student'
        owner_id   = studentId
        title      = 'Waiver Form'
        file_type  = 'application/pdf'
        drive_file_id = <ID>

For now, the helper just returns NULL so the rest of the flow still works.

5) Login URL
------------

In student-register.php, update the $loginUrl variable to your real login page,
for example:

   $loginUrl = "http://localhost/ACA-System/login.php";

or later, when deployed:

   $loginUrl = "https://your-domain.com/login.php";

6) Roles & Permissions
----------------------

- student-invite.php:
    * Accessible to roles: superadmin, admin

- student-register.php:
    * Public, but requires valid token from registration_tokens

- student-register-success.php:
    * Public

7) Flow Summary
---------------

Admin:
  - Goes to admin/student-invite.php
  - Selects batch
  - Enters student email
  - Submits
  - System:
      - Creates registration_tokens row with random token
      - Shows the registration URL which you can email manually for now
        (or in future, we also auto queue an email)

Student:
  - Opens link student-register.php?token=XYZ
  - Fills form
  - Checks waiver checkbox and signs
  - Submits
  - System:
      - Creates student row
      - Creates users row (role=student) with temporary password
      - Marks registration_tokens.status = 'completed'
      - Calls createStudentWaiverFromTemplate(...) (stub)
      - Inserts 2 email jobs into notifications_queue:
          * To student: registration success + credentials + login link
          * To superadmin: new student registered

You can then connect notifications_queue to your email sending worker.
