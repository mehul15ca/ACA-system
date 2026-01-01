ACA System – Announcements Module
=================================

This module lets Admin create announcements for:
- All students + coaches
- Only students
- Only coaches
- Students of a specific batch
- Students linked to a specific ground (via batch_schedule)

It also:
- Shows announcements on student & coach portals
- Allows Admin to archive/unarchive announcements
- Creates email jobs in notifications_queue (channel = 'email').


Database changes (IMPORTANT)
----------------------------
Run this SQL in phpMyAdmin on your `aca_system` database:

ALTER TABLE announcements
    ADD COLUMN status ENUM('active','archived') DEFAULT 'active' AFTER audience,
    ADD COLUMN batch_id INT NULL AFTER status,
    ADD COLUMN ground_id INT NULL AFTER batch_id,
    ADD CONSTRAINT fk_ann_batch FOREIGN KEY (batch_id) REFERENCES batches(id),
    ADD CONSTRAINT fk_ann_ground FOREIGN KEY (ground_id) REFERENCES grounds(id);

This keeps original fields (title, body, audience, published_from, published_to, created_at)
and adds:
- status    : active / archived (for "manually removed" announcements)
- batch_id  : optional, to scope to a specific batch
- ground_id : optional, to scope to a specific ground


Files
-----

admin/announcements.php
    - List of all announcements.
    - Filter by:
        - audience (all / students / coaches)
        - status (active / archived)
    - Shows:
        - Title
        - Audience
        - Scope (All / Batch / Ground)
        - Status
        - Created at
    - Actions:
        - View
        - Edit
    - Button: "New Announcement".

admin/announcement-add.php
    - Create a new announcement.
    - Fields:
        - Title
        - Message body
        - Scope:
            - All students + coaches
            - All students
            - All coaches
            - Students of a specific batch
            - Students of a specific ground
        - For batch/ground scopes, a corresponding dropdown appears.
    - Saves into announcements with:
        - audience: all / students / coaches
        - status: active
        - batch_id / ground_id set when needed.
    - After saving, automatically queues email notifications into `notifications_queue`
      (channel = 'email', status = 'pending', template_code = 'ANNOUNCEMENT') for:

        Scope "All" -> all active students + all active coaches
        Scope "Students" -> all active students
        Scope "Coaches" -> all active coaches
        Scope "Batch students" -> students in that batch
        Scope "Ground students" -> students whose batch has schedule entries at that ground
                                  (via batch_schedule).

admin/announcement-view.php
    - Shows:
        - Title
        - Audience
        - Scope (All / Batch / Ground)
        - Status
        - Created at
        - Message body
    - Buttons:
        - Archive / Mark Active (toggle status)
        - Edit
        - Back.

admin/announcement-edit.php
    - Edit title, body, status, and scope.
    - Same scope options as add:
        - All
        - Students
        - Coaches
        - Students of batch
        - Students of ground
    - Updates audience + batch_id + ground_id accordingly.
    - Does **not** re-queue emails (keeps it simple and avoids duplicates).

student/announcements.php
    - Student-facing announcements list.
    - Resolves student via users.student_id -> students -> batch_id.
    - Shows only:
        - status = 'active'
        - audience IN ('all','students')
        - For batch-specific announcements: batch_id must match student's batch_id.
        - For ground-specific announcements: student's batch must appear in batch_schedule
          rows that match the announcement ground_id.
    - Layout:
        - Card list with:
            - Title
            - Date (created_at)
            - Body text.

coach/announcements.php
    - Coach-facing announcements list.
    - Resolves coach via users.coach_id.
    - Shows announcements with:
        - status = 'active'
        - audience IN ('all','coaches')
        - Global announcements (no batch_id / ground_id)
        - PLUS batch/ground announcements where the coach appears in batch_schedule:
            - batch_schedule.coach_id = current coach
            - AND (batch_schedule.batch_id = announcements.batch_id
               OR  batch_schedule.ground_id = announcements.ground_id).


Navigation / Dashboard Integration
----------------------------------

1) Admin sidebar (admin/includes/sidebar.php)
   Under a suitable section (e.g., "System" or "Academy"), add:

       <li><a href="announcements.php">📢 Announcements</a></li>

2) Student portal
   Wherever you list links like My Attendance / My Fees / My Evaluations / My Injuries /
   Suggestions, also add:

       /student/announcements.php

3) Coach portal
   Similarly, add a link for coaches:

       /coach/announcements.php

4) Dashboards (optional)
   If you want announcements preview on dashboards, you can:
   - In admin/dashboard.php, run a small query:
        SELECT title, created_at FROM announcements
        WHERE status='active'
        ORDER BY created_at DESC
        LIMIT 3;
     And show them in a small widget.
   - Same idea for coach/student dashboards.

Notifications Queue
-------------------
This module only INSERTS rows into notifications_queue.
It does NOT send emails itself.

A future "Notification System" module can:
- Read pending rows from notifications_queue
- Send via actual email provider (SMTP / API)
- Update status to 'sent' or 'failed'.

For now, you can see all queued announcement emails by:

    SELECT * FROM notifications_queue WHERE template_code='ANNOUNCEMENT';

