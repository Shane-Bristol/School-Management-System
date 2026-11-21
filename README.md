School Management System

A security-first web application for managing students, teachers, attendance, grades, and academic records, built with Laravel.

Status: 🚧 Version 1 in active development. The roadmap below shows what is done and what is next.

Overview

Most school record systems treat security as an afterthought. This project does the opposite: access control, auditing, and data protection are designed first, and features are built on top of them.

Student records are sensitive, so the project is built around three rules:

Least privilege: every user sees only what their role requires.
Everything is auditable: changes to grades and attendance are logged with who, what, and when.
No real data: development and demos use generated fake data only.
Planned Features

Version 1 (current focus)

Authentication with secure sessions and rate-limited login
Role-based access control (admin, teacher)
Student management
Attendance tracking
Audit log of all record changes

Later

Grades and report cards
Parent portal (parents see only their own children)
Analytics dashboard
Tech Stack
Layer	Technology
Backend	Laravel (PHP 8.3+)
Database	MySQL
Frontend	Blade, Tailwind CSS
Authorization	Laravel Policies and Gates
Testing	PHPUnit / Pest
Security Approach
Passwords hashed with Laravel's built-in hashing (bcrypt/Argon2)
Eloquent and query-builder parameter binding to prevent SQL injection
Form Request validation on every input
CSRF protection on all forms
Login rate limiting
Authorization checks through Policies, tested for each role
Secrets kept in .env, never committed
Audit log for create, update, and delete actions

A threat model and full security notes will live in SECURITY.md.

Roadmap
[ ] Database design and schema (users, roles, students, classes, attendance, audit log)
[ ] Threat model
[ ] Authentication
[ ] Role-based access control
[ ] Student management
[ ] Attendance module
[ ] Audit logging
[ ] SECURITY.md and documentation
[ ] Deployment on AWS
[ ] Grades and report cards
[ ] Parent portal
Getting Started

Setup instructions will be added with the first working version.

bash
git clone https://github.com/Shane-Bristol/School-Management-System.git
cd School-Management-System
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
Author

Shane Bristol: Information Technology student, database developer, and Grade 6 teacher. GitHub · LinkedIn
