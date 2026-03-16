# UniCore UIMS — Student Lifecycle
## From Admission to Graduation

---

## Overview

A student's journey through UniCore follows a structured lifecycle across multiple modules. Below is the complete flow from the moment an applicant submits their application to the day they graduate.

---

## 🗺️ Complete Lifecycle Flow

```
APPLICATION
    │
    ▼
ADMISSION REVIEW
    │
    ▼
ACCEPTANCE
    │
    ▼
STUDENT ACCOUNT CREATION
    │
    ▼
FEE PAYMENT (Admission)
    │
    ▼
┌─────────────────────────────────────────┐
│           EACH SEMESTER                 │
│                                         │
│  Course Registration (Enrollment)       │
│           │                             │
│           ▼                             │
│  Fee Payment (Tuition/Lab/Exam)         │
│           │                             │
│           ▼                             │
│  Timetable → Attend Classes             │
│           │                             │
│           ▼                             │
│  Attendance Tracked (P/A/L/E)           │
│           │                             │
│           ▼                             │
│  Mid-Semester Exams (Quiz/Midterm)      │
│           │                             │
│           ▼                             │
│  Final Exams                            │
│           │                             │
│           ▼                             │
│  Grades Published → CGPA Updated        │
│           │                             │
│           ▼                             │
│  Library Usage (optional)               │
│                                         │
└─────────────────────────────────────────┘
    │
    ▼ (repeat for each semester)
    │
    ▼
ACADEMIC PROGRESSION CHECK
    │
    ▼
GRADUATION
```

---

## Stage 1 — Application (Admissions Module)

**Who handles it:** Admission Officer

**What happens:**
1. Applicant submits application form with personal info, SSC/HSC results
2. System auto-calculates **merit score** = (SSC GPA × 40) + (HSC GPA × 60)
3. Application gets status: `Applied`

**UniCore actions:**
- Go to `/admissions` → Click **New Application**
- Fill: name, email, phone, gender, DOB, SSC/HSC GPA, department, semester

---

## Stage 2 — Admission Review (Admissions Module)

**Who handles it:** Admission Officer / Admin

**What happens:**
1. Officer reviews application → clicks **Mark Under Review** → status: `Under Review`
2. Officer shortlists promising candidates → clicks **Shortlist** → status: `Shortlisted`
3. Final decision:
   - **Accept** → status: `Accepted`
   - **Reject** → status: `Rejected` (rejection reason required)

**UniCore actions:**
- `/admissions` → View application → click action buttons in pipeline
- Pipeline: Applied → Under Review → Shortlisted → Accepted/Rejected

---

## Stage 3 — Student Account Creation (Admissions Module)

**Who handles it:** Admission Officer / Admin

**What happens:**
1. For accepted applicants → click **Enroll** button
2. System auto-creates:
   - User account with email + default password (`password`)
   - Student profile with auto-generated **Student ID** (format: `YYS-SSSS-DEPT`)
   - Assigns `student` role
3. Application status → `Enrolled`

**UniCore actions:**
- `/admissions` → Find accepted application → click **Enroll as Student**
- Student can now login with their email

---

## Stage 4 — Admission Fee Payment (Fee Module)

**Who handles it:** Accountant / Staff

**What happens:**
1. Generate admission fee invoice for new student
2. Student pays at accounts office
3. Payment recorded in system

**UniCore actions:**
- `/fees` → **New Invoice** → search student → select `admission` type → set amount + due date
- When student pays → click **Collect** → enter amount, payment method (Cash/bKash/Nagad), date

---

## Stage 5 — Course Registration / Enrollment (Enrollment Module)

**Who handles it:** Student (self) or Admin

**What happens each semester:**
1. Student selects courses to register for
2. Admin reviews and **approves** or **rejects** enrollment requests
3. Bulk approve multiple students at once

**UniCore actions:**
- `/enrollments` → **Enroll Student** → search student → select course, semester, section
- Admin approves: click ✓ per row OR select multiple pending → **Approve X**
- Students can be enrolled in multiple courses per semester

**Business rules:**
- Student cannot enroll in same course twice in same semester
- Course has max capacity (set in course settings)
- Enrollment must be approved before attendance/grades apply

---

## Stage 6 — Tuition & Fee Payment (Fee Module)

**Who handles it:** Accountant

**What happens each semester:**
1. Bulk generate invoices for all students (tuition, lab, exam fees)
2. Students pay before deadline
3. Overdue invoices automatically flagged

**UniCore actions:**
- `/fees` → Fee Structures tab → select structure → **Bulk Generate**
- Defaulters tab shows students with overdue fees
- Collect payments individually or in batch

---

## Stage 7 — Class Schedule (Timetable Module)

**Who handles it:** Admin / Academic Officer

**What happens:**
1. Weekly timetable created per department/semester
2. Room conflicts auto-detected
3. Faculty assigned to each slot

**UniCore actions:**
- `/timetable` → **Add Slot** → select course, day, time, room, faculty
- Grid view shows Mon–Sat with color-coded slots per course
- Students check timetable to know when/where their classes are

---

## Stage 8 — Attendance (Attendance Module)

**Who handles it:** Faculty / Admin

**What happens each class:**
1. Faculty marks attendance after each session
2. Each student marked: **P** (Present) / **A** (Absent) / **L** (Late) / **E** (Excused)
3. System tracks cumulative attendance percentage

**UniCore actions:**
- `/attendance` → **Mark Attendance** → select course, date, semester
- Students auto-loaded from approved enrollments
- Quick buttons: "All Present" or mark individually
- Save as Draft or **Finalize**

**Business rules:**
- Minimum 75% attendance required (configurable in Settings)
- Students below threshold flagged in attendance report
- Low attendance triggers notification to student

---

## Stage 9 — Mid-Semester Exams (Exams Module)

**Who handles it:** Faculty / Exam Controller

**What happens:**
1. Exams scheduled (Quiz 1, Midterm, Assignment)
2. After exam: faculty enters marks per student
3. Marks saved → grade auto-calculated

**UniCore actions:**
- `/exams` → **Schedule Exam** → set title, type (quiz/midterm), course, date, total marks, weightage
- After exam: click pencil icon → **Enter Results** → type marks per student
- Grade shows instantly (A+/A/A-/B+...)
- Click **Save & Publish** to release results

---

## Stage 10 — Final Exams (Exams Module)

**Who handles it:** Faculty / Exam Controller

**What happens:**
1. Final exam scheduled with higher weightage (e.g. 50%)
2. Results entered same as mid-semester
3. All exam results published

**Grading Scale:**

| Marks % | Grade | GPA |
|---------|-------|-----|
| ≥ 80% | A+ | 4.00 |
| ≥ 75% | A | 3.75 |
| ≥ 70% | A- | 3.50 |
| ≥ 65% | B+ | 3.25 |
| ≥ 60% | B | 3.00 |
| ≥ 55% | B- | 2.75 |
| ≥ 50% | C+ | 2.50 |
| ≥ 45% | C | 2.25 |
| ≥ 40% | D | 2.00 |
| < 40% | F | 0.00 |

---

## Stage 11 — Final Grades & CGPA (Grades Module)

**Who handles it:** Faculty / Academic Officer

**What happens:**
1. Load students for a course + semester
2. Enter final weighted marks (0–100)
3. Grade auto-calculates
4. **Publish** grades → students can see results
5. System auto-updates each student's **CGPA**
6. Enrollment status → `Completed`

**UniCore actions:**
- `/grades` → select course + semester → **Load Students**
- Enter marks → grades show live → **Save Grades**
- When ready → **Publish All** → students notified

**CGPA Calculation:**
```
CGPA = Average of all published grade points across all courses
```

---

## Stage 12 — Library Usage (Library Module)

**Who handles it:** Librarian

**What happens (optional, ongoing):**
1. Student requests a book
2. Librarian issues book → due date set
3. Student returns book on time
4. Late returns → fine calculated automatically

**UniCore actions:**
- `/library` → search student → **Issue Book**
- Return: find active issue → click **Return**
- Overdue issues shown separately with fine amount

---

## Stage 13 — Semester Completion Check

**Who handles it:** Academic Officer / Admin

**What happens after each semester:**
1. Check student has passed all courses (GPA ≥ 2.0)
2. Check attendance was above minimum in all courses
3. Check all fees are paid
4. Update student semester (e.g. 1st → 2nd)

**Academic status options:**
- `regular` — progressing normally
- `probation` — GPA below threshold
- `suspended` — disciplinary or fee issues
- `graduated` — completed all requirements

**UniCore actions:**
- `/students` → view student → edit → update `semester` and `academic_status`
- `/reports` → Students report → filter by department to review batch

---

## Stage 14 — Repeat Semesters (Stages 5–13)

The student repeats stages 5–13 for each semester of their program:

| Program | Duration | Semesters |
|---------|----------|-----------|
| BSc/BBA (4 year) | 4 years | 8 semesters |
| BSc (3 year) | 3 years | 6 semesters |
| MSc/MBA | 2 years | 4 semesters |

---

## Stage 15 — Graduation

**Who handles it:** Academic Officer / Admin

**Requirements checklist:**
- ✅ All required courses completed with passing grade
- ✅ Minimum CGPA met (e.g. 2.0 or higher)
- ✅ All fees paid (no outstanding invoices)
- ✅ No library books overdue
- ✅ Completed required credit hours

**UniCore actions:**
1. `/reports` → Students report → verify CGPA and completed courses
2. `/fees` → Defaulters tab → confirm no outstanding fees
3. `/library` → confirm no overdue books
4. `/students` → find student → Edit → set `academic_status` = `graduated`
5. Generate graduation certificate via `/reports` → Students PDF

---

## 📊 Summary Table

| Stage | Module | Status Change |
|-------|--------|---------------|
| 1. Apply | Admissions | `applied` |
| 2. Review | Admissions | `under_review` → `shortlisted` |
| 3. Decision | Admissions | `accepted` / `rejected` |
| 4. Enroll | Admissions | `enrolled` + Student account created |
| 5. Pay admission fee | Fees | Invoice `paid` |
| 6. Register courses | Enrollments | `pending` → `approved` |
| 7. Pay tuition | Fees | Invoice `paid` |
| 8. Attend classes | Timetable | — |
| 9. Attendance tracked | Attendance | Present/Absent/Late/Excused |
| 10. Sit exams | Exams | Results `published` |
| 11. Grades published | Grades | `completed` + CGPA updated |
| 12. Library (optional) | Library | Issued → Returned |
| 13. Next semester | Students | Semester incremented |
| 14. Repeat | — | Stages 6–13 per semester |
| 15. Graduate | Students | `graduated` |

---

## 👥 Who Does What

| Role | Responsibilities |
|------|-----------------|
| **Super Admin** | Full system access, user management |
| **Admin** | Day-to-day operations, all modules |
| **Admission Officer** | Applications, acceptance, enrollment conversion |
| **Faculty** | Mark attendance, enter exam results, grade students |
| **Accountant** | Fee invoices, payment collection, financial reports |
| **Librarian** | Book catalog, issue/return, fines |
| **Staff** | General operations support |
| **Student** | View own data (grades, attendance, fees, timetable) |

---

## 🔔 System Notifications Triggered

| Event | Notification Sent To |
|-------|---------------------|
| Application accepted/rejected | Applicant |
| Enrollment approved | Student |
| Fee invoice generated | Student |
| Fee payment overdue | Student |
| Exam results published | Students in that course |
| Grade published | Students in that course |
| Attendance below 75% | Student |
| Book return reminder | Student |
| New timetable published | All students |