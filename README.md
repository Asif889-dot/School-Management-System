# SCA Admin - School Management System (PHP)

[![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-005C84?style=for-the-badge&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)](https://developer.mozilla.org/en-US/docs/Web/JavaScript)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg?style=for-the-badge)](https://opensource.org/licenses/MIT)

A comprehensive, modern web-based application designed to manage school operations efficiently. Built with PHP, this system features a sleek admin dashboard to handle student admissions, attendance, results, and fee management for administrators, teachers, and students.

## 📸 Application Previews

### Secure Login Portal
![Login Portal](screenshots/login.png)
*Role-based login for Students, Teachers, Admins, and Guardians.*

### Dashboard Overview
![Dashboard Overview](screenshots/dashboard.png)
*Real-time metrics for student admissions, active teachers, and fee collections.*

| Student Directory | Admission Form |
| :---: | :---: |
| ![Students List](screenshots/students-list.png) | ![Admission Form](screenshots/admission-form.png) |
| *Advanced filtering and student management* | *Clean, multi-step data entry form* |

## 🚀 Features

**👥 User & Student Management**
- Manage Students, Teachers, and Guardians
- Comprehensive Student Admission Forms
- Advanced Search and Filtering (by Class, Section, or Name)
- Send Notices and Parent Messages

**💰 Financial Management**
- Configure Fee Settings and Discounts
- Track Fee Operations (Payments/Collections)
- View Accounts Matrix and Total Fees Realized

**📚 Academic Management**
- Define Academic Structure and Sessions
- Create Timetable Matrix
- Manage Exams Matrix and Attendance Reports
- Academic Calendar, Syllabus, and Daily Diary tracking

**⚙️ System**
- Real-time Dashboard Overview
- Secure Role-based Login/Logout (Admin, Teacher, Student)
- Fully customizable Settings

## 🛠️ Tech Stack
- **Backend:** PHP
- **Database:** MySQL
- **Frontend:** HTML, CSS, JavaScript
- **Dependency Manager:** Composer

## 📂 Project Structure

```text
SMS/
├── assets/          # CSS, JS, Images, and Frontend libraries
├── backend/         # Core PHP logic, Controllers, and Models
├── frontend/        # Views, UI templates, and Layouts
├── uploads/         # Directory for student photos/documents
├── vendor/          # Composer dependencies (Ignored in Git)
├── .htaccess        # Apache configuration
├── composer.json    # PHP package dependencies
├── database_migrations.sql # Database schema
└── index.php        # Application entry point
