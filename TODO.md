# Implementation Plan & Progress

## Phase 1 — Database & Core Infrastructure
- [x] Create `database_migrations.sql` with new tables
- [x] Run migration against sms_db

## Phase 2 — Parent (Guardian) Portal (`frontend/guardian/`)
- [x] Create `backend/guardian_sidebar.php`
- [x] dashboard.php
- [x] children.php
- [x] attendance.php
- [x] marks.php
- [x] fees.php
- [x] daily_diary.php
- [x] datesheet.php
- [x] apply_leave.php
- [x] messages.php
- [x] notices.php
- [x] profile.php

## Phase 3 — Student Portal (`frontend/student/`)
- [x] Create `backend/student_sidebar.php`
- [x] dashboard.php
- [x] academic_record.php
- [x] attendance.php
- [x] daily_diary.php
- [x] notices.php
- [x] calendar.php
- [x] marks.php
- [x] datesheet.php
- [x] profile.php

## Phase 4 — Missing Admin Features (`frontend/admin/`)
- [x] notices.php
- [x] daily_diary.php
- [x] syllabus.php
- [x] awards.php
- [x] salary_slips.php
- [x] salary_slip_print.php
- [x] promote_students.php
- [x] parent_messages.php
- [x] experience_letters.php
- [x] experience_letter_print.php
- [x] Updated `backend/sidebar.php` with new admin navigation links

## Phase 5 — Teacher Feature Additions (`frontend/teacher/`)
- [x] upload_study_plan.php
- [x] upload_diary.php
- [x] view_notices.php
- [x] reply_messages.php
- [x] download_awards.php
- [x] Updated `backend/teacher_sidebar.php` with new teacher navigation links
- [x] Created `frontend/teacher/marks_entry.php` — teacher-specific bulk test/exam marks entry restricted to their own assigned classes, sections & courses (fixes "page not found")
- [x] Updated teacher sidebar "Grades" dropdown to point to `marks_entry.php` (Enter Marks) and `students.php` (My Students)
- [x] Fixed teacher dashboard quick-action links to point to existing pages (`timetable.php`, `students.php`, `marks_entry.php`, `attendance.php`)
- [x] Cleaned up `backend/teacher_sidebar.php` (removed duplicate "Study Plans" item, fixed "Assignments" → "Study Plans")
- [x] Created `backend/get_teacher_sections_subjects.php` — teacher-scoped AJAX endpoint returning only sections & subjects assigned to the logged-in teacher
- [x] Added dynamic (AJAX) Section & Subject dropdown population to `frontend/teacher/marks_entry.php` so choices appear when a class is selected

## Phase 6 — Login/Registration Integration
- [x] Auto-create guardian & student accounts on approval (in `approve_student.php`)
- [x] Guardian & student accounts created with role-specific default permissions
- [x] Guardian accounts linked to students via `guardian_user_id`
- [x] Login redirects verified (admin/teacher/student/guardian in `backend/login.php`)
- [x] Created `frontend/admin/add_user.php` — manual creation of login accounts for Parent (Guardian), Teacher, Student & Admin with role-based default permissions, optional guardian↔student and teacher↔record linking
- [x] Rewrote `users_roles.php` as a full user-management listing page (search/filter by role & status, counts, "View" action per user)
- [x] Created `frontend/admin/user_details.php` — detailed view of existing Parent/Student/Teacher/Admin accounts (account info, linked student/teacher/guardian record, assigned permissions, and secure password reset)
