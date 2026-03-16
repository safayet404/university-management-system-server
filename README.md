# UniCore UIMS — Backend API

<div align="center">

![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?style=for-the-badge&logo=php&logoColor=white)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-316192?style=for-the-badge&logo=postgresql&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-Ready-2496ED?style=for-the-badge&logo=docker&logoColor=white)

**A comprehensive University Information Management System REST API**

</div>

---

## 📋 Table of Contents
- [Overview](#overview)
- [Tech Stack](#tech-stack)
- [Features](#features)
- [Getting Started](#getting-started)
- [Environment Variables](#environment-variables)
- [API Endpoints](#api-endpoints)
- [Database Schema](#database-schema)
- [Docker Deployment](#docker-deployment)
- [Default Credentials](#default-credentials)

---

## Overview

UniCore UIMS Backend is a RESTful API built with Laravel 12 that powers a full university management system. It handles everything from student admissions to graduation — including courses, enrollments, attendance, exams, grades, fees, library, and more.

---

## Tech Stack

| Technology | Version | Purpose |
|------------|---------|---------|
| PHP | 8.3 | Runtime |
| Laravel | 12 | Framework |
| PostgreSQL | 16 | Database |
| Laravel Sanctum | 4.x | API Authentication |
| Spatie Permission | 6.x | Roles & Permissions |
| Spatie Activity Log | 4.x | Audit Logging |
| Maatwebsite Excel | 3.x | Excel Export |
| DomPDF | 3.x | PDF Generation |
| Neon / Render | - | Hosting |

---

## Features

| Module | Description |
|--------|-------------|
| 🔐 **Authentication** | Token-based auth via Sanctum, login by email/employee ID/student ID |
| 👥 **Users & Roles** | 9 roles, 94 permissions, full RBAC |
| 🎓 **Students** | Profiles, CGPA tracking, academic status |
| 👨‍🏫 **Faculty** | Profiles, designations, research stats |
| 🏫 **Departments & Programs** | Academic structure management |
| 📚 **Courses** | Course catalog with faculty assignment |
| 📝 **Enrollments** | Student course registration with approval workflow |
| ✅ **Attendance** | Session-based attendance with P/A/L/E marking |
| 📄 **Exams** | Exam scheduling and result management |
| 📊 **Grades** | Grade entry, GPA calculation, CGPA updates |
| 💰 **Fee Management** | Invoice generation, payment collection, defaulters |
| 🎯 **Admissions** | Application pipeline from applied to enrolled |
| 📖 **Library** | Book catalog, issue/return, fine management |
| 🗓️ **Timetable** | Weekly schedule with conflict detection |
| 🔔 **Notifications** | In-app notifications with broadcast support |
| 📈 **Reports** | PDF reports for students, fees, attendance, grades |
| ⚙️ **Settings** | System-wide configuration management |
| 📋 **Activity Logs** | Full audit trail for all operations |

---

## Getting Started

### Prerequisites
- PHP 8.3+
- Composer
- PostgreSQL 15+
- Git

### Local Setup

```bash
# Clone the repository
git clone https://github.com/YOUR_USERNAME/unicore-backend.git
cd unicore-backend

# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure your database in .env
# DB_CONNECTION=pgsql
# DB_HOST=localhost
# DB_DATABASE=unicore
# DB_USERNAME=postgres
# DB_PASSWORD=your_password

# Run migrations and seed
php artisan migrate:fresh --seed

# Start development server
php artisan serve
```

The API will be available at `http://localhost:8000/api`

---

## Environment Variables

```env
APP_NAME=UniCore
APP_ENV=local
APP_KEY=base64:your-key-here
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=unicore
DB_USERNAME=postgres
DB_PASSWORD=your_password

# For Neon PostgreSQL
DB_SSLMODE=require

# CORS - set to your frontend URL
CORS_ALLOWED_ORIGINS=http://localhost:3000

# Sanctum
SANCTUM_STATEFUL_DOMAINS=localhost:3000
```

---

## API Endpoints

### Authentication
```
POST   /api/auth/login
POST   /api/auth/logout
GET    /api/auth/me
PUT    /api/auth/profile
PUT    /api/auth/change-password
POST   /api/auth/avatar
```

### Academic
```
GET|POST          /api/students
GET|PUT|DELETE    /api/students/{id}
GET|POST          /api/faculty
GET|POST          /api/courses
GET|POST          /api/enrollments
PATCH             /api/enrollments/{id}/approve
PATCH             /api/enrollments/{id}/reject
```

### Operations
```
GET|POST          /api/attendance/sessions
POST              /api/attendance/mark
GET               /api/attendance/report
GET|POST          /api/exams
POST              /api/exams/{id}/results
GET|POST          /api/grades/course
POST              /api/grades/save
POST              /api/grades/publish
```

### Finance & Services
```
GET               /api/fees/invoices
POST              /api/fees/invoices/{id}/pay
GET|POST          /api/admissions
PATCH             /api/admissions/{id}/accept
POST              /api/admissions/{id}/enroll
GET               /api/timetable
GET               /api/reports/pdf/{type}
```

---

## Database Schema

The system uses **18 main tables**:

```
users                  → All system users
student_profiles       → Student-specific data
faculty_profiles       → Faculty-specific data
departments            → Academic departments
programs               → Degree programs
courses                → Course catalog
enrollments            → Student-course relationships
attendance_sessions    → Attendance class sessions
attendance_records     → Per-student attendance
exams                  → Exam schedules
exam_results           → Per-student exam marks
course_grades          → Final course grades
fee_structures         → Fee definitions
fee_invoices           → Student invoices
fee_payments           → Payment records
admissions             → Admission applications
timetable_slots        → Class schedule
notifications          → In-app notifications
settings               → System configuration
```

---

## Docker Deployment

```bash
# Build and run with Docker
docker build -f docker/Dockerfile -t unicore-backend .
docker run -p 8000:80 --env-file .env unicore-backend

# Or with docker-compose (from parent directory)
docker-compose up --build
```

---

## Default Credentials

| Email | Password | Role |
|-------|----------|------|
| superadmin@unicore.edu | password | Super Admin |
| admin@unicore.edu | password | Admin |
| faculty@unicore.edu | password | Faculty |
| student@unicore.edu | password | Student |
| staff@unicore.edu | password | Staff |
| accountant@unicore.edu | password | Accountant |
| librarian@unicore.edu | password | Librarian |
| admission@unicore.edu | password | Admission Officer |

---

## License

MIT License — feel free to use for educational purposes.