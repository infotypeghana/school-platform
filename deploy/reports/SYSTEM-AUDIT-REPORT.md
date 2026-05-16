# SchoolMS Ghana — Complete System Feature Audit & Functionality Report

**Document Type:** Production-Grade System Audit  
**Prepared For:** Technical Investors / Deployment Readiness Review  
**Date:** 2026-05-16  
**Codebase State:** Laravel 13.8 / PHP 8.4 — 704 Tests Green  
**Audit Basis:** Full static codebase analysis (routes, controllers, models, services, migrations, config)

---

## TABLE OF CONTENTS

1. [System Overview](#1-system-overview)
2. [Complete Feature Inventory](#2-complete-feature-inventory)
3. [User Role Breakdown](#3-user-role-breakdown)
4. [Workflow Documentation](#4-workflow-documentation)
5. [Module-by-Module Breakdown](#5-module-by-module-breakdown)
6. [API & Route Map](#6-api--route-map)
7. [Database Structure Overview](#7-database-structure-overview)
8. [Feature Gating Analysis](#8-feature-gating-analysis)
9. [White-Label System Analysis](#9-white-label-system-analysis)
10. [Security Overview](#10-security-overview)
11. [Payment & Billing System Analysis](#11-payment--billing-system-analysis)
12. [System Limitations / Risks](#12-system-limitations--risks)
13. [Final System Scorecard](#13-final-system-scorecard)

---

## 1. SYSTEM OVERVIEW

### 1.1 High-Level Architecture

SchoolMS Ghana is a **multi-tenant Software-as-a-Service (SaaS) School Management Platform** purpose-built for Ghanaian basic schools (Primary, JHS, SHS). It operates as a shared-infrastructure, shared-database multi-tenant system where each school ("tenant") is logically isolated via a `tenant_id` column on every data table, enforced by Eloquent global scopes.

The system serves five distinct user groups — super admins, school administrators, teachers, parents, and implicitly students — across four separate domain zones:

```
superadmin.schoolms.com.gh       → Super Admin portal (platform operations)
{slug}.admin.schoolms.com.gh     → School Admin portal (per-school management)
{slug}.schoolms.com.gh           → Public school website + parent/teacher portals
schoolms.com.gh/api/v1/          → REST API (mobile school apps)
```

### 1.2 Tech Stack

| Layer | Technology |
|---|---|
| **Framework** | Laravel 13.8 |
| **Language** | PHP 8.4 |
| **Database** | MySQL 8 (production) / SQLite (testing) |
| **Cache / Queue / Session** | Redis (3 separate DBs: 0=queue, 1=cache, 2=session) |
| **Queue Manager** | Laravel Horizon (4 supervised queues) |
| **PDF Generation** | barryvdh/laravel-dompdf |
| **API Authentication** | Laravel Sanctum (Bearer token) |
| **2FA** | pragmarx/google2fa-laravel (TOTP RFC 6238) |
| **Error Tracking** | Sentry (sentry/sentry-laravel) |
| **File Storage** | Laravel Filesystem (local + S3) |
| **Frontend** | Blade templates + Tailwind CSS (CDN) + Alpine.js |
| **PWA** | Service worker + offline.html + manifest.json |
| **Biometric Hardware** | ZKTeco devices via TCP (ADMS push protocol) |
| **SMS Gateway** | Hubtel (Ghana) |
| **Payment Gateways** | Paystack (primary) + Moolre (secondary) |
| **Static Analysis** | PHPStan Level 5 + Larastan v3 |
| **CI/CD** | GitHub Actions (test → build → deploy) |
| **Containerisation** | Docker multi-stage + Docker Compose |

### 1.3 Multi-Tenant Structure

**Strategy:** Shared database with `tenant_id` column isolation.

Every tenant-scoped model uses the `HasTenantScope` Eloquent global scope, which automatically injects `WHERE tenant_id = <current_tenant_id>` into every query. The current tenant is resolved at the HTTP middleware layer (`ResolveTenantMiddleware`) from:

1. The subdomain slug (`accra-academy.admin.schoolms.com.gh` → tenant with slug `accra-academy`)
2. The `custom_domain` column (enterprise tenants with custom domains)
3. Domain aliases (`tenant_aliases` table)

Once resolved, the tenant is bound to the IoC container as `app('currentTenant')` — making it globally available to all downstream code including Eloquent scopes, services, and jobs.

**Bypass:** Super admin operations use `withoutTenantScope()` explicitly, and every such bypass is recorded via `TenantContext::recordBypass()` with reason and calling context.

### 1.4 SaaS Structure

```
Platform Layer (Super Admin)
  └── Tenant Registry
  └── Subscription Packages (Basic / Standard / Premium / Enterprise)
  └── Billing Engine (Paystack + Moolre)
  └── SaaS Metrics Dashboard
  └── Invoice Management

School Layer (Per Tenant)
  └── Academic Calendar (Years + Terms)
  └── School Data (Classes, Students, Teachers, Subjects)
  └── Academic Operations (Attendance, Assessments, Report Cards)
  └── Finance (Fees, Feeding, Expenses)
  └── Communication (SMS, Announcements)
  └── Planning (Timetable, Lesson Notes, Curriculum)
  └── Portals (Teacher Portal, Parent Portal)
  └── Public Website

Feature Gating
  └── 33 features across 5 tiers
  └── Route middleware + Service enforcement + Blade directives
```

---

## 2. COMPLETE FEATURE INVENTORY

### 2.1 DASHBOARD

**Feature Name:** Admin Dashboard  
**Module:** Core  
**Purpose:** Provides a real-time overview of school operations for the logged-in school administrator.  
**Technical Flow:** `DashboardController::index()` aggregates: active student count, teacher count, today's attendance rate, unpaid fee total, pending admissions, current term name. Results cached 5 minutes per tenant.  
**Input:** None (auto-resolved from current tenant + current term)  
**Output:** Stats cards, recent activity feed, current term details  
**Tables:** students, teachers, attendances, fees, admissions, academic_terms, tenants  
**Route:** `GET /dashboard`  
**UI:** `resources/views/admin/dashboard.blade.php`

---

### 2.2 STUDENT MANAGEMENT

**Feature Name:** Student CRUD  
**Module:** Academics  
**Purpose:** Full lifecycle management of student records.

**How it works:**
- List view with filtering by class, status, search by name/ID
- Create: form captures personal info, guardian details, class assignment, admission number
- Update: same form in edit mode
- Soft delete: sets `deleted_at` — record preserved for audit
- Show: student profile with linked attendance, fees, assessments, report cards

**Input:** Full name, date of birth, gender, guardian name/phone/email, class, admission number, photo (optional)  
**Output:** Student record; redirect with flash; student profile page  
**Tables:** students, school_classes, admissions  
**Routes:** `GET|POST /students`, `GET|PUT|DELETE /students/{student}`, `GET /students/{student}/transcript`  
**UI:** admin/students/index, create, edit, show, transcript

---

**Feature Name:** Student Import (CSV)  
**Module:** Academics  
**Purpose:** Bulk create students from a CSV file.  
**Technical Flow:** `ImportController::studentImport()` validates CSV structure against downloadable template, creates Student records in a transaction. Validation errors returned row-by-row.  
**Input:** CSV file (conforming to template)  
**Output:** Success count / error report  
**Feature Gate:** `standard` plan (`data_export` feature)  
**Routes:** `GET /students/import`, `GET /students/import/template`, `POST /students/import`

---

**Feature Name:** Student Export  
**Module:** Academics  
**Purpose:** Download all students as CSV.  
**Routes:** `GET /students/export`

---

**Feature Name:** Student Transcript  
**Module:** Academics  
**Purpose:** View a student's full academic history across all terms.  
**Route:** `GET /students/{student}/transcript`

---

**Feature Name:** Student Promotion  
**Module:** Academics  
**Purpose:** Bulk-promote or retain students at end of academic year.  
**Technical Flow:**
1. Admin selects source class → target class mapping
2. Preview shows which students will be promoted, which retained (based on grade threshold)
3. Execute creates `Promotion` records and updates `student.school_class_id`
4. History page shows all past promotion batches

**Input:** Class mappings, promotion rules  
**Output:** Promotion batch record; students moved to next class  
**Tables:** promotions, students, school_classes  
**Routes:** `GET|POST /students/promotion` (preview, execute), `GET /students/promotion/history`  
**Feature Gate:** `standard` plan

---

### 2.3 TEACHER MANAGEMENT

**Feature Name:** Teacher CRUD  
**Module:** Staff  
**Purpose:** Full lifecycle management of teacher records.  
**Input:** Name, email, phone, subject specialisation, class assignment  
**Output:** Teacher record; profile page  
**Tables:** teachers, school_classes  
**Routes:** `GET|POST /teachers`, `GET|PUT|DELETE /teachers/{teacher}`

---

**Feature Name:** Teacher Import  
**Module:** Staff  
**Purpose:** Bulk-create teachers from CSV.  
**Routes:** `GET|POST /teachers/import`, `GET /teachers/import/template`

---

**Feature Name:** Teacher Portal Management  
**Module:** Staff  
**Purpose:** Activate/deactivate teacher's access to the teacher-facing portal; reset portal password.  
**Technical Flow:** Admin toggles `portal_active` boolean on teacher record; generate bcrypt `portal_password` stored on teacher row (separate from main auth system — teachers are NOT in `users` table).  
**Routes:** `POST /teachers/{teacher}/portal/activate|deactivate|reset-password`

---

### 2.4 TEACHER PORTAL

**Feature Name:** Teacher Portal  
**Module:** Teacher Self-Service  
**Purpose:** Allows teachers to log in independently and manage their own subjects without admin access.  
**Feature Gate:** `premium` plan  
**Technical Flow:** Session-based auth using `teacher_portal_id` key. `EnsureTeacherPortalAuth` middleware checks session + `portal_active = true`. Teachers cannot see other teachers' data or admin functions.

**Sub-features:**
- **Dashboard:** My classes, today's announcements
- **Score Entry:** Enter/edit CA and exam scores for own subjects only; grade auto-computed by `GradeCalculator`
- **Timetable View:** Read-only timetable for own classes
- **Lesson Notes:** Full CRUD — create, submit, view feedback (approve/revision from admin)
- **Lesson Note Attachments:** Upload supporting files (PDFs, images)
- **Schemes of Work:** Full CRUD for weekly curriculum plans
- **Remarks:** Enter per-student term remarks visible on report cards

**Tables:** teachers, assessments, timetables, lesson_notes, lesson_note_attachments, schemes_of_work, scheme_of_work_weeks  
**Routes:** All under `{slug}.schoolms.com.gh/teacher/*`  
**UI:** resources/views/teacher/

---

### 2.5 CLASS & SUBJECT MANAGEMENT

**Feature Name:** School Class Management  
**Module:** Academics  
**Purpose:** Define school classes (Year 1A, JHS 2B etc.) and assign subjects.  
**Input:** Class name, level, capacity, class teacher assignment, subjects  
**Output:** Class record with subject list  
**Tables:** school_classes, subjects  
**Routes:** `GET|POST /classes`, `GET|PUT|DELETE /classes/{class}`, subject assignment routes

---

### 2.6 ATTENDANCE

**Feature Name:** Attendance Recording  
**Module:** Attendance  
**Purpose:** Daily attendance marking per class.  
**Technical Flow:** Admin selects class + date. All active students listed. Admin marks each: present / absent / late / excused. Saved to `attendances` table.  
**Input:** Class, date, status per student  
**Output:** Attendance records; attendance summary statistics  
**Tables:** attendances, students, school_classes, academic_terms  
**Routes:** `GET|POST /attendance`, `GET /attendance/export`

---

**Feature Name:** Biometric Attendance  
**Module:** Attendance  
**Purpose:** Automated attendance from ZKTeco fingerprint devices.  
**Feature Gate:** `premium` plan  
**Technical Flow:**
1. Admin registers biometric device (IP, port, serial number)
2. Students/teachers enrolled on device with `device_user_id` mapped to person
3. Device pushes attendance logs via ADMS protocol to `GET|POST /biometric/adms`
4. `BiometricAttendanceService` + `ZKTecoService` process raw logs
5. `SyncBiometricAttendance` command also pulls logs actively (every 15 min, 06:00–18:00)
6. Processed logs appear in admin biometric dashboard + feed into attendance records

**Tables:** biometric_devices, biometric_enrollments, biometric_logs  
**Routes:** `GET|POST|PUT|DELETE /biometric/*`, `POST|DELETE /biometric/{device}/enroll`, `GET /biometric/adms` (public, device-facing)  
**Services:** `BiometricAttendanceService`, `ZKTecoService`

---

### 2.7 ASSESSMENTS (SCORE ENTRY)

**Feature Name:** Assessment / Score Entry  
**Module:** Academics  
**Purpose:** Enter CA (Continuous Assessment) and exam scores per student per subject per term.  
**Technical Flow:**
1. Admin selects class + subject + term
2. Score entry table: each student row shows CA input + Exam input fields
3. On save: `total_score = CA + Exam`; `GradeCalculator::evaluate(total)` computes grade/remark/points
4. All stored on `assessments` table (one row per student+subject+term+class)

**Input:** CA score (max 30 default, tenant-configurable), Exam score (max 70 default)  
**Output:** Assessment record with computed grade  
**Tables:** assessments, students, subjects, school_classes, academic_terms  
**Routes:** `GET /report-cards/scores`, `GET /report-cards/scores/edit`, `PUT /report-cards/scores/update`  
**Services:** `GradeCalculator`

---

**Feature Name:** Customisable Grading Scale  
**Module:** Academics  
**Purpose:** Allows schools to override the default Ghana GES grading scale.  
**Technical Flow:** `SettingsController::grading()` renders current scale. Admin can modify band boundaries, grade labels, remarks. Stored as JSON in `tenants.grading_settings`. `GradeCalculator::tenantSettings()` reads this before defaulting to built-in GES scale.  
**Routes:** `GET|PUT /settings/grading`, `DELETE /settings/grading/reset`

---

**Feature Name:** Assessment Export  
**Module:** Academics  
**Routes:** `GET /assessments/export`

---

### 2.8 REPORT CARDS

**Feature Name:** Report Card Statistics Computation  
**Module:** Academics  
**Purpose:** Calculate class positions, subject averages, attendance summaries, and overall class rankings.  
**Technical Flow:** `ReportCardService::computeClassStatistics()`:
1. Per subject: compute class average, highest, lowest, per-student position
2. Per student: aggregate best-N grades (Ghana BECE style, lower aggregate = better)
3. Overall class position based on aggregate
4. Attendance summary (present / total days)
5. Upserts `report_cards` table header record per student

**Input:** School class, academic term (selected by admin)  
**Tables:** assessments, attendances, report_cards, students, school_classes  
**Route:** `POST /report-cards/compute`  
**Service:** `ReportCardService`

---

**Feature Name:** PDF Report Card Generation  
**Module:** Academics  
**Purpose:** Generate printable/downloadable PDF report cards.  
**Feature Gate:** `standard` plan (`report_cards_pdf`)  
**Technical Flow:**
1. Admin triggers generate for single student or entire class
2. `GenerateReportCardJob` dispatched to `pdf` queue (dedicated Horizon supervisor)
3. `ReportCardService::generatePdf()`:
   - Loads assessments + student + class + term
   - Passes tenant branding (colors, logo) to view
   - Renders `resources/views/pdf/report-card.blade.php` via DomPDF
   - Saves to `storage/app/report-cards/{tenant_id}/{student_id}_{name}_term{N}.pdf`
   - Emails guardian via `ReportCardReadyMail` (queued)
4. Admin can batch-check generation status; download when complete

**Input:** None (student/term resolved from report card record)  
**Output:** PDF file; download link; guardian email notification  
**Tables:** report_cards, assessments, attendances, students, school_classes, academic_terms, tenants  
**Routes:** `POST /report-cards/generate-all`, `POST /report-cards/{reportCard}/generate`, `GET /report-cards/{reportCard}/download`, `GET /report-cards/batch-status`  
**Job:** `GenerateReportCardJob` (pdf queue, 180s timeout)

---

**Feature Name:** Report Card Remarks  
**Module:** Academics  
**Purpose:** Admin adds class teacher remarks and head teacher remarks to report cards.  
**Routes:** `GET|PUT /report-cards/{reportCard}/remarks`

---

### 2.9 ADMISSIONS

**Feature Name:** Online Admissions (Public Form)  
**Module:** Admissions  
**Purpose:** Prospective students submit online admission applications from the school's public website.  
**Technical Flow:**
1. Public form at `{slug}.schoolms.com.gh/admissions/apply`
2. Form collects: student details, guardian details, desired class, documents
3. Admission record created with `status = pending`
4. Admin reviews in admin portal

**Tables:** admissions  
**Routes:** `GET|POST {slug}.schoolms.com.gh/admissions`, `GET {slug}.schoolms.com.gh/admissions/apply`, `GET {slug}.schoolms.com.gh/admissions/status`  
**Feature Gate:** `basic` plan (all tiers)

---

**Feature Name:** Admissions Management (Admin)  
**Module:** Admissions  
**Purpose:** Review, approve, or reject admission applications; enrol approved students.  
**Technical Flow:**
1. Admin views pending admissions list
2. Review individual application
3. Actions: Approve → creates Student record from admission data + links via `admissions.student_id`; notifies guardian via `StudentEnrolledMail`
4. Reject: marks `status = rejected`; optional reason stored
5. Bulk operations available

**Tables:** admissions, students  
**Routes:** `GET|POST /admissions`, `GET|POST|PUT|DELETE /admissions/{admission}`, `POST /admissions/bulk`  
**Mail:** `StudentEnrolledMail` (queued)

---

### 2.10 FEE MANAGEMENT

**Feature Name:** School Fee Management  
**Module:** Finance  
**Purpose:** Create, assign, and track school fee payments per student.  
**Technical Flow:**
1. Admin creates fee record for student: `type` (school_fees, books, uniform, etc.), `amount`, `due_date`
2. Optional: auto-generate for all students in a class
3. Record payment: `FeeController::recordPayment()` / `FeePaymentService` creates Payment record and marks fee as paid
4. Receipt generation: printable receipt with payment details

**Input:** Fee type, amount, student, due date; Payment: amount, date, reference, gateway  
**Output:** Fee record; Payment record; receipt PDF/printable  
**Tables:** fees, payments, payment_ledger, students  
**Routes:** `GET|POST /fees`, `GET|PUT|DELETE /fees/{fee}`, `POST /fees/{fee}/payment`, `GET /fees/{fee}/receipt`  
**Service:** `FeePaymentService`

---

**Feature Name:** Online Fee Payment (Parent Portal)  
**Module:** Finance  
**Purpose:** Parents pay school fees online via payment gateway from the parent portal.  
**Technical Flow:**
1. Parent logs into portal, views fees
2. Clicks "Pay Online" → `POST /portal/fees/{fee}/pay`
3. `PaymentGatewayService` initialises payment with Paystack or Moolre → returns `authorization_url`
4. Parent completes payment on gateway
5. Gateway calls webhook → payment confirmed → fee marked paid → `payment_ledger` entry written

**Tables:** fees, payments, payment_ledger, tenants  
**Routes:** `POST {slug}.schoolms.com.gh/portal/fees/{fee}/pay`, callbacks, webhooks

---

### 2.11 FEEDING FEE SYSTEM

**Feature Name:** Feeding Fee Configuration  
**Module:** Finance (Feeding)  
**Feature Gate:** `standard` plan  
**Purpose:** Schools that provide meals can configure feeding fees per class and manage cafeteria payments.  
**Technical Flow:**
1. Admin configures feeding fee rate per class (`FeedingConfig`)
2. Admin assigns feeding fee to enrolled students (`FeedingFee` record per student per term)
3. Admin or parent records payment (`FeedingPayment`)
4. Exempt: some students can be marked exempt (scholarship, etc.)
5. Reports: feeding fee collection summary

**Input:** Per-class fee amount, student assignment, payment amount  
**Output:** FeedingFee record; payment receipt; collection report  
**Tables:** feeding_configs, feeding_fees, feeding_payments  
**Routes:** All under `GET|POST|PUT /feeding/*` (gated: `feature:feeding_fees`)  
**Service:** `FeedingFeeService`

---

### 2.12 FINANCE / EXPENSE TRACKING

**Feature Name:** Expense Management  
**Module:** Finance  
**Purpose:** Track school operating expenses for finance reporting.  
**Input:** Description, amount, category, date, notes  
**Output:** Expense record; finance ledger view  
**Tables:** expenses  
**Routes:** `GET|POST|PUT|DELETE /finance/expenses/*`

---

**Feature Name:** Finance Dashboard  
**Module:** Finance  
**Purpose:** Consolidated financial overview: income vs expenses, fee collection rate, payment history.  
**Routes:** `GET /finance`, `GET /finance/ledger`, `GET /finance/statement`

---

### 2.13 TIMETABLE

**Feature Name:** Timetable Management  
**Module:** Academics  
**Purpose:** Create and manage the school's weekly lesson timetable.  
**Input:** Day, period, class, subject, teacher  
**Output:** Timetable records; grid view  
**Tables:** timetables, school_classes, subjects, teachers  
**Routes:** `GET|POST|PUT|DELETE /timetables/*`, `GET /timetables/subjects/{schoolClass}`

---

### 2.14 ANNOUNCEMENTS

**Feature Name:** Announcements  
**Module:** Communication  
**Purpose:** Publish announcements visible to admin staff and optionally on the public school website.  
**Input:** Title, body, category, publish date, visibility (internal/public)  
**Tables:** announcements  
**Routes:** `GET|POST|PUT|DELETE /announcements/*`

---

### 2.15 SMS / NOTIFICATIONS

**Feature Name:** SMS to Parents  
**Module:** Communication  
**Feature Gate:** `standard` plan (`sms_notifications`)  
**Purpose:** Send SMS messages to individual or all guardians of a class/school.  
**Technical Flow:**
1. Admin composes message, selects recipients (individual / class / all)
2. `SmsController::send()` or `::broadcast()` dispatches `SendSmsJob` to `notifications` queue
3. `SmsService` calls Hubtel API with configured sender ID
4. Response logged to `sms_logs` table
5. Broadcast limited to 5 per minute (throttle)

**Input:** Message text, recipient selection  
**Output:** SMS sent; log entry in sms_logs  
**Tables:** sms_logs  
**Routes:** `GET /sms`, `POST /sms/send`, `POST /sms/broadcast` (throttle:5,1)  
**Job:** `SendSmsJob` (notifications queue)  
**Service:** `SmsService`

---

### 2.16 LESSON NOTES & PLANNING

**Feature Name:** Lesson Notes (Teacher workflow)  
**Module:** Academic Planning  
**Feature Gate:** `standard` plan  
**Purpose:** Teachers prepare and submit lesson notes for admin review; admin approves or requests revision.

**Technical Flow:**
1. Teacher creates lesson note (subject, class, topic, objectives, content, resources)
2. Attaches files (PDF, images via `LessonNoteAttachment`)
3. Status: `draft` → `submitted` → `approved` / `revision_requested`
4. Admin reviews, approves or sends back with comments
5. Teacher revises and resubmits

**Tables:** lesson_notes, lesson_note_attachments  
**Routes (teacher):** Full CRUD under `/teacher/lesson-notes/*`  
**Routes (admin review):** `GET /lesson-notes`, `POST /lesson-notes/{id}/approve|revision`  
**Feature Gate:** `standard`

---

**Feature Name:** Schemes of Work  
**Module:** Academic Planning  
**Feature Gate:** `standard` plan  
**Purpose:** Teachers plan weekly curriculum delivery aligned to curriculum strands.  
**Technical Flow:** Teacher creates a scheme of work (class, subject, term), adds weekly entries (week number, topic, strand reference, activities, resources).  
**Tables:** schemes_of_work, scheme_of_work_weeks  
**Routes:** Full CRUD under `/teacher/lesson-notes/schemes/*`

---

**Feature Name:** Curriculum Management  
**Module:** Academic Planning  
**Feature Gate:** `standard` plan (`curriculum_management`)  
**Purpose:** Admin maintains the structured curriculum with strands and sub-strands (Ghana GES curriculum alignment).  
**Input:** Strand name, sub-strand name, class/subject association  
**Tables:** curriculum_strands, curriculum_sub_strands  
**Routes:** `GET|POST|PUT|DELETE /curriculum/*` (gated: `feature:curriculum_management`)

---

### 2.17 ANALYTICS

**Feature Name:** Analytics Dashboard  
**Module:** Analytics  
**Purpose:** Visual summary of academic and financial performance.  
**Includes:** Attendance trends, fee collection rates, assessment performance by class, student enrollment trends  
**Routes:** `GET /analytics`, `GET /analytics/export/csv`, `GET /analytics/export/pdf`

---

### 2.18 AUDIT TRAIL

**Feature Name:** Audit Log  
**Module:** Admin / Compliance  
**Purpose:** Immutable log of all create/update/delete operations on critical models.  
**Coverage:** Student, Teacher, Fee, Assessment, Admission, FeedingFee, SchoolClass, Subject  
**Technical Flow:** `AuditObserver` fires on Eloquent events. Writes diff (old values / new values) to `audit_logs`. Sensitive fields (`password`, `two_factor_secret`) scrubbed.  
**Tables:** audit_logs  
**Routes:** `GET /audit`, `GET /audit/{auditLog}`  
**Pruning:** `PruneAuditLogsCommand` — 1st of month, deletes rows older than 365 days

---

### 2.19 DATA BACKUP & EXPORT

**Feature Name:** School Data Export (ZIP)  
**Module:** Admin  
**Purpose:** School admin can export all their school data as a ZIP file containing 7 CSVs.  
**ZIP Contents:** README.txt, students.csv, teachers.csv, classes.csv, attendance.csv, assessments.csv, fees.csv  
**Technical Flow:**
1. Admin triggers `POST /backup` → `SchoolExport` record created (status=pending)
2. Duplicate guard: if pending/processing export exists, redirects with info flash
3. `ExportSchoolDataJob` dispatched to `exports` queue (300s timeout)
4. Job builds ZIP with `ZipArchive` + `fputcsv`; saves to `storage/app/exports/{tenant_id}_{uuid}.zip`
5. Download link available for 24 hours

**Tables:** school_exports  
**Routes:** `GET|POST /backup`, `GET /backup/{id}/download`, `DELETE /backup/{id}`  
**Job:** `ExportSchoolDataJob` (exports queue)

---

**Feature Name:** Database Backup  
**Module:** Platform Ops  
**Purpose:** Full MySQL dump with SHA-256 checksum sidecar, uploaded to S3.  
**Command:** `php artisan db:backup` (daily 02:00 WAT)  
**Command:** `php artisan db:restore [--list|--verify-only|--force]`

---

**Feature Name:** Disaster Recovery Drill  
**Module:** Platform Ops  
**Purpose:** Monthly automated restore verification on scratch schema.  
**Command:** `php artisan dr:drill [--dry-run]` (1st of month, 03:30 WAT)

---

### 2.20 SETTINGS

**Feature Name:** School Settings  
**Module:** Settings  
**Purpose:** Configure school information visible on reports and public site.  
**Settings groups:**
- **School:** name, address, phone, email, motto, logo
- **Grading:** CA/Exam max marks, grade scale override
- **Website:** hero text, about text, news/gallery content, mission/vision
- **Account:** change password
- **Calendar:** current academic year/term selection
- **2FA:** setup/confirm/disable TOTP

**Routes:** `GET|PUT /settings/*`

---

### 2.21 PARENT PORTAL

**Feature Name:** Parent Portal  
**Module:** Parent Self-Service  
**Feature Gate:** `basic` plan  
**Purpose:** Parents view their child's fees, attendance, and assessments without an admin account.

**Technical Flow:**
1. Parent visits `{slug}.schoolms.com.gh/portal`
2. Enters admission number + date of birth → student record located
3. `parent_portal_student_id` stored in session
4. Dashboard shows: unpaid fees, attendance summary, latest assessment scores
5. Logout clears session

**Multi-ward support:** `add-ward` and `switch-ward` routes allow a parent to manage multiple children.

**Tables:** students, fees, attendances, assessments  
**Routes:** `GET|POST /portal`, `/portal/dashboard`, `/portal/switch-ward`, `/portal/add-ward`, `POST /portal/logout`

---

### 2.22 PUBLIC SCHOOL WEBSITE

**Feature Name:** Public School Website  
**Module:** Website  
**Feature Gate:** `basic` plan  
**Purpose:** Each school gets a public-facing website at `{slug}.schoolms.com.gh`.

**Pages:**
- Home (hero, stats, news preview)
- About (mission, vision, history)
- Academics (curriculum overview)
- News / Gallery
- Contact (form with validation — logs to file)
- Admissions (application form link + status check)

**Content management:** Via `SettingsController::websiteContent()` in admin panel  
**Tables:** tenants (website_content JSON column), announcements

---

### 2.23 SCHOOL REGISTRATION (TENANT SELF-REGISTRATION)

**Feature Name:** School Self-Registration  
**Module:** SaaS Onboarding  
**Purpose:** New schools register themselves; super admin approves to activate.  
**Technical Flow:**
1. School fills `GET /register/school` form (name, email, phone, slug, domain)
2. `TenantRegistrationController::store()` creates Tenant (status=pending) + trial Subscription
3. Super admin approves: `POST /superadmin/tenants/{tenant}/approve`
4. On approval: Tenant status = active; trial subscription activated; onboarding email sent

**Tables:** tenants, subscriptions  
**Routes:** `GET|POST /register/school`, `GET /register/school/verify`, `GET /register/school/done`  
**Super Admin Routes:** `POST /superadmin/tenants/{tenant}/approve`

---

### 2.24 SAAS METRICS DASHBOARD (Super Admin)

**Feature Name:** SaaS Revenue Metrics  
**Module:** Super Admin  
**Purpose:** Platform-wide revenue and tenant health dashboard.

**Displays:**
- Total revenue (period: month/quarter/year)
- Revenue by gateway (Paystack vs Moolre breakdown)
- Active / trial / grace / locked / suspended tenant counts
- Top revenue-generating schools
- Reconciliation anomaly count
- Per-tenant detail drill-down: students, revenue, last payment, subscription status, recent audit activity

**Tables:** payments, subscriptions, tenants, audit_logs  
**Routes:** `GET /superadmin/metrics`, `GET /superadmin/metrics/tenant/{tenant}`  
**Controller:** `SaasMetricsController`

---

### 2.25 SUBSCRIPTION MANAGEMENT (Super Admin)

**Feature Name:** Subscription Lifecycle Management  
**Module:** SaaS Billing  
**Purpose:** Super admin manages all school subscriptions, transitions states, and extends grace periods.  
**Routes:** `GET|PUT /superadmin/subscriptions`, `POST /subscriptions/{subscription}/transition`, `POST /subscriptions/{subscription}/extend-grace`

---

### 2.26 INVOICE MANAGEMENT (Super Admin)

**Feature Name:** Invoice System  
**Module:** SaaS Billing  
**Purpose:** Generate, view, and manage invoices for school subscription payments.  
**Technical Flow:** `InvoiceService` generates invoices per term based on student count × price per student from the package.  
**Commands:** `php artisan invoices:generate` (1st of month, 07:00 WAT)  
**Tables:** invoices  
**Routes:** `GET|POST|PUT /superadmin/invoices`, `POST /superadmin/invoices/{invoice}/mark-paid|void`

---

### 2.27 SUBSCRIPTION PACKAGE MANAGEMENT

**Feature Name:** Package Management  
**Module:** SaaS Billing  
**Purpose:** Create and configure subscription packages (Basic/Standard/Premium/Enterprise) with pricing and feature tiers.  
**Tables:** subscription_packages  
**Routes:** `GET|POST|PUT|DELETE /superadmin/packages`

---

### 2.28 ACADEMIC YEAR & TERM MANAGEMENT

**Feature Name:** Academic Calendar  
**Module:** Platform / Per-School  
**Purpose:** Platform-level calendar management; schools select current year/term.  
**Tables:** academic_years, academic_terms  
**Super Admin Routes:** Resource CRUD for academic years and terms  
**School Admin:** `GET|PUT /settings/calendar` (select current term)

---

### 2.29 REST API (Mobile)

**Feature Name:** REST API v1  
**Module:** Integration  
**Feature Gate:** `premium` plan (`api_access`)  
**Purpose:** Provides a mobile app interface for school staff to access key data.

**Endpoints:**
- Auth: login, logout, me
- Dashboard stats
- Students: list, show
- Classes: list, show
- Attendance: list, record
- Announcements: list
- Timetable: view
- Assessments: list

**Auth:** Sanctum Bearer token (30-day expiry)  
**Rate Limits:** Plan-aware (30–300 req/min)  
**Documentation:** OpenAPI 3.1 at `public/api/openapi.yaml`, Swagger UI at `/api/docs`

---

### 2.30 TWO-FACTOR AUTHENTICATION

**Feature Name:** TOTP 2FA  
**Module:** Security  
**Purpose:** Optional TOTP-based 2FA for school admins and super admins.  
**Technical Flow:** Setup via QR code (Google Charts API) → confirm → persistent. Login flow: after credential check, if 2FA enabled → logout → session stores `2fa_pending_user_id` → redirect to challenge route → verify code → login.  
**Routes:** `GET|POST /settings/2fa`, `/settings/2fa/confirm|disable`, challenge routes

---

### 2.31 PAYMENT RECONCILIATION

**Feature Name:** Payment Reconciliation  
**Module:** Finance  
**Purpose:** Detect and optionally auto-heal payment anomalies.  
**Detects:** Orphaned payments (no subscription link), duplicate references, unactivated subscriptions  
**Command:** `php artisan payments:reconcile [--from --to --tenant --heal]` (weekly, Sunday 04:00 WAT)  
**Service:** `ReconciliationService`

---

### 2.32 IMMUTABLE PAYMENT LEDGER

**Feature Name:** Payment Ledger  
**Module:** Finance  
**Purpose:** Permanent append-only audit trail of every payment state change.  
**States:** pending → successful / failed → reversed / disputed  
**Model-level protection:** `save()` on existing rows throws; `delete()` always throws  
**Observer:** `PaymentObserver` auto-writes on every Payment status change  
**Table:** payment_ledger

---

### 2.33 HEALTH CHECK

**Feature Name:** Health Endpoint  
**Module:** Platform Ops  
**Purpose:** Liveness probe for load balancers, UptimeRobot, Kubernetes.  
**Checks:** DB, cache, queue, storage, Horizon, SaaS metrics  
**Route:** `GET /health` (throttle:60,1)  
**Response:** `{"status":"ok"}` (200) or `{"status":"degraded","checks":{...}}` (503)

---

## 3. USER ROLE BREAKDOWN

### 3.1 Super Admin

**Access domain:** `superadmin.schoolms.com.gh`  
**Auth:** Laravel Auth + optional 2FA  
**Gate bypass:** `Gate::before()` returns `true` for `isSuperAdmin()` — bypasses all policies

| Capability | Detail |
|---|---|
| Tenant management | List, approve, suspend, view all schools |
| Academic calendar | Create/edit years and terms (global) |
| Subscription management | View all subscriptions, transition states, extend grace |
| Package management | Create/edit pricing packages |
| Invoice management | Generate, mark paid, void invoices |
| Payment oversight | View all payments across all tenants |
| SaaS metrics dashboard | Revenue by gateway, top schools, anomalies |
| Per-tenant metrics | Drill-down: students, revenue, last payment |
| 2FA setup | Personal 2FA management |
| DB backup/restore | `php artisan db:backup|db:restore` |
| DR drill | `php artisan dr:drill` |
| All feature gates bypassed | FeatureGate returns `true` for super admin |

---

### 3.2 School Admin

**Access domain:** `{slug}.admin.schoolms.com.gh`  
**Auth:** Laravel Auth + optional 2FA + subscription check  
**Middleware stack:** `resolve.tenant → school.admin → 2fa → subscription.admin`

| Module | Capabilities |
|---|---|
| Dashboard | View school stats |
| Students | Full CRUD, import, export, transcript, promotion |
| Teachers | Full CRUD, import, portal activation/password reset |
| Classes & Subjects | Full CRUD |
| Attendance | Record, view, export |
| Assessments | Enter scores for all subjects/classes |
| Report Cards | View, compute stats, generate PDFs, download, add remarks |
| Admissions | Review, approve, reject applications |
| Fees | Create, record payment, receipt |
| Feeding Fees | Configure, assign, record payment, exempt |
| Finance | Expenses CRUD, ledger view, statements |
| Timetable | Full CRUD |
| SMS | Send to individuals/classes/all (feature-gated) |
| Announcements | Create/publish |
| Biometric | Register devices, enrol students, view logs (feature-gated) |
| Lesson Notes | Review submitted notes, approve/request revision |
| Curriculum | Manage strands/sub-strands (feature-gated) |
| Analytics | View dashboards, export |
| Audit Trail | View all changes |
| Backup/Export | Trigger ZIP export, download |
| Settings | School info, grading, website content, calendar, 2FA |
| Billing | View own invoices and subscription status |
| API Docs | Link to Swagger UI |

---

### 3.3 Teacher

**Access domain:** `{slug}.schoolms.com.gh/teacher/*`  
**Auth:** Session-based (NOT Laravel Auth — teachers not in `users` table)  
**Auth key:** `teacher_portal_id` session key  
**Middleware:** `EnsureTeacherPortalAuth` — checks session + `portal_active = true`  
**Feature gate:** Portal itself requires `premium` plan

| Capability | Scope |
|---|---|
| View dashboard | Own classes + announcements |
| Score entry | Own subjects only (subject ownership enforced) |
| View timetable | Own classes only |
| Lesson notes CRUD | Own notes only |
| Lesson note attachments | Upload/delete files on own notes |
| Schemes of work | Full CRUD for own plans |
| Per-student remarks | For own classes |

**Restrictions:** Teachers cannot see other teachers' data, cannot access admin portal, cannot modify student records.

---

### 3.4 Parent

**Access domain:** `{slug}.schoolms.com.gh/portal/*`  
**Auth:** Stateless lookup upgraded to session (`parent_portal_student_id`)  
**Access control:** Admission number + DOB verification

| Capability | Detail |
|---|---|
| View fees | All fees for their child(ren) |
| Pay fees online | Via Paystack or Moolre |
| View attendance summary | Per term |
| View assessment scores | Per term |
| Multi-ward | Add and switch between multiple children |
| Logout | Clears session |

**Restrictions:** Read-only except for online fee payments. Cannot see other students.

---

### 3.5 Student

**No dedicated login portal in current implementation.** Students are data subjects, not active users of the system. Their data is accessible to parents via the parent portal and to teachers/admins via their respective portals.

---

## 4. WORKFLOW DOCUMENTATION

### 4.1 Student Admission Process Flow

```
Step 1 [Public Website]
  → Parent visits {slug}.schoolms.com.gh/admissions/apply
  → Fills admission form (student details, guardian details, desired class)
  → Form submitted → Admission record created (status=pending)

Step 2 [Admin Portal]
  → Admin sees new admission in admissions list
  → Reviews application details
  → Can: Approve / Reject / Request more info

Step 3 [On Approval]
  → StudentController creates Student record from admission data
  → admission.student_id = new student ID (links records)
  → admission.status = 'enrolled'
  → StudentEnrolledMail queued → sent to guardian email
  → Student appears in student list with assigned class

Step 4 [Optional: Admission Number]
  → Admin can set/auto-generate unique admission number
  → Used for parent portal access
```

---

### 4.2 Fee Payment Flow (Admin-Initiated)

```
Step 1: Admin creates fee
  → FeeController::store()
  → Fee record created (tenant_id, student_id, type, amount, due_date, status=unpaid)

Step 2: Parent makes payment (cash/bank)
  → Admin records payment: POST /fees/{fee}/payment
  → FeePaymentService creates Payment record (type=fee)
  → PaymentObserver fires → PaymentLedger::record(state=pending)
  → Payment status updated to 'success'
  → PaymentObserver fires again → PaymentLedger::record(state=successful)
  → Fee status updated to 'paid'
  → Receipt generated

Step 3: Parent portal alternative
  → Parent logs in, views unpaid fees
  → Clicks "Pay Online"
  → PaymentGatewayService initialises Paystack/Moolre payment
  → Redirect to gateway → parent completes payment
  → Gateway webhooks → WebhookController → HMAC verified
  → Payment confirmed → fee marked paid → ledger entry
```

---

### 4.3 Result Generation Flow

```
Step 1: Score Entry
  → Admin/Teacher selects class + subject + term
  → Enters CA + Exam scores per student
  → GradeCalculator::evaluate() → grade, remark, points
  → Assessment records created/updated

Step 2: Statistics Computation
  → Admin clicks "Compute Statistics" for class + term
  → ReportCardService::computeClassStatistics():
    a. Per subject: avg, highest, lowest, per-student position
    b. Per student: aggregate (sum best-N points, lower=better)
    c. Overall class positions
    d. Attendance summary per student
    e. Upsert report_cards table rows

Step 3: PDF Generation (feature:report_cards_pdf)
  → Admin triggers generate (single or all)
  → GenerateReportCardJob dispatched to pdf queue
  → ReportCardService::generatePdf():
    - Loads all data with eager loading
    - Applies tenant branding (color, logo)
    - DomPDF renders A4 portrait
    - Saves to storage
    - Updates report_card.pdf_path + generated_at
    - Queues ReportCardReadyMail to guardian

Step 4: Download
  → GET /report-cards/{reportCard}/download
  → Returns Storage::download() from saved path
```

---

### 4.4 Subscription/Payment Flow (SaaS)

```
Step 1: School Registration
  → School fills /register/school
  → Tenant created (status=pending)
  → Trial subscription created (status=trial, amount=0)
  → Super admin approves → Tenant activated

Step 2: Subscription Upgrade
  → School admin views billing page
  → Selects package (Basic/Standard/Premium/Enterprise)
  → Initiates payment via Paystack or Moolre
  → Redirect to payment gateway

Step 3: Payment Confirmation
  → Gateway sends webhook to /webhooks/paystack or /webhooks/moolre
  → HMAC signature verified
  → Timestamp window checked (5 min)
  → Idempotency key checked (30 min)
  → SubscriptionService::activateFromPayment()
    → subscription.transitionToActive()
    → tenant.status = 'active'
    → FeatureGate cache flushed for tenant
  → PaymentLedger entry written (successful)

Step 4: Lifecycle Automation
  → CheckSubscriptionStatusJob runs daily at 06:00 WAT
  → active/trial where term.end_date < today → grace
  → grace where grace_ends_at < now → locked
  → Tenants notified at 7/3/1 days before expiry
```

---

### 4.5 Tenant Onboarding Flow

```
Step 1: Self-registration
  → /register/school → TenantRegistrationController::store()
  → Creates: Tenant (status=pending), User (role=school_admin), trial Subscription

Step 2: Super Admin Approval
  → POST /superadmin/tenants/{tenant}/approve
  → tenant.status = 'active'
  → Welcome email sent

Step 3: School Admin First Login
  → {slug}.admin.schoolms.com.gh/login
  → ResolveTenantMiddleware resolves tenant from subdomain
  → EnsureSchoolAdmin verifies user role + tenant match
  → AdminSubscriptionMiddleware checks subscription status

Step 4: Initial Configuration
  → Settings → School info, logo upload
  → Settings → Calendar → Select current academic year + term
  → Settings → Grading → Verify or customise grading scale
  → Classes → Create school classes + subjects
  → Teachers → Add teaching staff; activate portal access
  → Students → Import via CSV or enter individually

Step 5: Operations Begin
  → Admissions start accepting applications
  → Attendance can be recorded
  → Fees created and tracked
```

---

### 4.6 Report Card Full Lifecycle

```
Term begins:
  1. Admin creates/assigns students to classes
  2. Teachers assigned to subjects

During term:
  3. Teachers (via portal) OR admin enters CA scores progressively
  4. End of term: exam scores entered

End of term:
  5. Admin: Compute Statistics (positions, averages, ranks)
  6. Teachers: Enter per-student remarks via Teacher Portal
  7. Admin: Add class teacher + head teacher remarks
  8. Admin: Generate PDF for all (dispatched to pdf queue)
  9. Queue workers (GenerateReportCardJob) render DomPDF
 10. Parents receive email with notification (ReportCardReadyMail)
 11. Parents log into parent portal or download from link
 12. Admin can download individual or bulk PDFs
```

---

## 5. MODULE-BY-MODULE BREAKDOWN

### 5.1 Admissions Module

**Purpose:** Manage the full student intake pipeline from online application to enrolment.

**Internal Structure:**
- Public form on school website (`website.php` routes)
- Admin review pipeline in admin portal (`admin.php` routes)
- `AdmissionController` — list, show, approve, reject, bulk
- `Admission` model (HasTenantScope, SoftDeletes)
- Fields: student info, guardian info, desired class, document uploads, status, student_id link

**Key States:** pending → approved → enrolled / rejected

**Dependencies:** Student module (creates Student on approval), Mail system (StudentEnrolledMail)

---

### 5.2 Academics Module

**Purpose:** Core academic operations — students, classes, subjects, attendance, assessments, report cards, promotions.

**Key Services:**
- `GradeCalculator` — Ghana GES grade bands, aggregate computation, class positions, tenant-configurable scale
- `ReportCardService` — statistics computation, PDF generation, email notification
- `GenerateReportCardJob` — async PDF generation (pdf queue)

**Key Models:** Student, Teacher, SchoolClass, Subject, Attendance, Assessment, ReportCard, Promotion

**Dependencies:** Finance (for attendance in report cards), Communication (email notifications), Queue system (PDF generation)

---

### 5.3 Finance Module

**Purpose:** Fee collection, expense tracking, feeding fees, financial reporting.

**Internal Structure:**
- `FeeController` — fee CRUD + payment recording
- `FeePaymentService` — payment processing, ledger writing
- `FeedingFeeService` — cafeteria fee management
- `FeedingController` — feeding fee admin interface
- `FinanceController` — expenses, ledger, statements

**Key Models:** Fee, Payment, PaymentLedger, FeedingConfig, FeedingFee, FeedingPayment, Expense

**Feature Gates:** feeding_fees (standard+)

**Dependencies:** Payment gateway (Paystack/Moolre for online), SMS (payment receipts)

---

### 5.4 Communication Module

**Purpose:** SMS notifications to parents, announcements, email notifications.

**Internal Structure:**
- `SmsController` — compose and send/broadcast
- `SmsService` — Hubtel API integration
- `SendSmsJob` — async SMS dispatch
- `AnnouncementController` — create/publish announcements
- Mail: `StudentEnrolledMail`, `ReportCardReadyMail`, `BackupFailedNotification`

**Feature Gates:** sms_notifications (standard+)

**Dependencies:** Queue system (async SMS), Hubtel API, SMTP

---

### 5.5 Academic Planning Module

**Purpose:** Curriculum management, lesson planning, timetable.

**Internal Structure:**
- Teacher Portal lesson notes workflow (`LessonNoteController` admin-side, `LessonNoteController` teacher-side)
- `LessonNote` model (with file attachments via `LessonNoteAttachment`)
- `SchemeOfWork` / `SchemeOfWorkWeek` — weekly curriculum plans
- `CurriculumStrand` / `CurriculumSubStrand` — Ghana GES curriculum structure
- `TimetableController` — period/day/class/subject/teacher assignment

**Feature Gates:** lesson_notes (standard+), curriculum_management (standard+)

---

### 5.6 SaaS / Billing Module

**Purpose:** Platform monetisation, subscription lifecycle, invoice management, package configuration.

**Internal Structure:**
- `SubscriptionService` — lifecycle state machine, trial creation, billing calculations
- `FeatureGate` — feature resolution by tier, 10-min cache
- `InvoiceService` — invoice generation
- `ReconciliationService` — anomaly detection + auto-healing
- `PaymentGatewayService` — Paystack + Moolre initialisation
- `PaymentObserver` + `PaymentLedger` — immutable financial trail

**Scheduled Jobs:**
- `CheckSubscriptionStatusJob` — daily 06:00 WAT
- `GenerateTermInvoicesCommand` — 1st of month 07:00 WAT
- `ReconcilePaymentsCommand` — weekly Sunday 04:00 WAT

**Key Models:** Tenant, Subscription, SubscriptionPackage, Payment, PaymentLedger, Invoice

---

### 5.7 White-Label Module

**Purpose:** Per-tenant branding customisation across all user-facing surfaces.

**Internal Structure:**
- Branding fields on `Tenant` model (11 columns)
- Helper methods: `faviconUrl()`, `effectiveSmsSenderId()`, `effectiveEmailFromName()` etc.
- Admin settings interface: `SettingsController::school()`
- `StorageService` — tenant-namespaced file uploads for logos/favicons
- PDF template (`pdf/report-card.blade.php`) reads `$primaryColor` from tenant
- Emails use `$tenant->effectiveEmailFromAddress()` and `$tenant->effectiveEmailFromName()`

**Feature Gates:** custom_domain (enterprise), custom_branding (premium)

---

### 5.8 Admin Module (Super Admin)

**Purpose:** Platform-wide operations, tenant management, metrics.

**Internal Structure:**
- `TenantController` — CRUD + approval workflow
- `SubscriptionController` — lifecycle management + grace extension
- `SaasMetricsController` — revenue dashboard + per-tenant drill-down
- `InvoiceController` — invoice lifecycle
- `PackageController` — subscription tier configuration
- `AcademicYearController` / `AcademicTermController` — global calendar
- `PaymentController` — cross-tenant payment view

**Access Control:** `EnsureSuperAdmin` middleware + `Gate::before()` super admin bypass

---

## 6. API & ROUTE MAP

### 6.1 Public Routes (`routes/web.php`)

| Route | Middleware | Purpose |
|---|---|---|
| `GET /health` | throttle:60,1 | Health check probe |
| `GET /storage/signed/{path}` | — | Signed private file serving |
| `GET|POST /register/school` | — | School self-registration |
| `GET /register/school/verify` | — | Email verification landing |
| `GET /register/school/done` | — | Registration complete page |

### 6.2 Payment & Webhook Routes

| Route | Middleware | Purpose |
|---|---|---|
| `GET /pay/{slug}` | throttle:10,1 | Payment initiation |
| `GET /pay/{slug}/page` | throttle:10,1 | Hosted payment page |
| `GET /pay/{slug}/paystack/callback` | — | Paystack return |
| `GET /pay/{slug}/moolre/callback` | — | Moolre return |
| `POST /webhooks/paystack` | (CSRF exempt) | Paystack webhook receiver |
| `POST /webhooks/moolre` | (CSRF exempt) | Moolre webhook receiver |

### 6.3 Biometric Device Route

| Route | Middleware | Purpose |
|---|---|---|
| `GET|POST /biometric/adms` | (CSRF exempt, no auth) | ZKTeco ADMS push endpoint |

### 6.4 Super Admin Routes (`routes/superadmin.php`)

All protected by: `super.admin + 2fa` middleware  
Domain: `superadmin.{APP_DOMAIN}`

| Route Group | Routes |
|---|---|
| Dashboard | `GET /dashboard` |
| Tenants | Resource CRUD + `POST /tenants/{tenant}/approve` |
| Academic Years | Resource CRUD |
| Academic Terms | Resource CRUD |
| Subscriptions | Index, show, edit, update + transition + extend-grace |
| Payments | `GET /payments` |
| Metrics | `GET /metrics`, `GET /metrics/tenant/{tenant}` |
| Invoices | Index, generate, show, mark-paid, void |
| Packages | Resource CRUD (no show) |
| 2FA | Setup, confirm, disable |

### 6.5 School Admin Routes (`routes/admin.php`)

All protected by: `resolve.tenant → school.admin → 2fa → subscription.admin`  
Domain: `{slug}.admin.{APP_DOMAIN}`

| Route Group | Feature Gate | Key Routes |
|---|---|---|
| Dashboard | none | `GET /dashboard` |
| Settings | none | `GET|PUT /settings/*` |
| Backup | none | `GET|POST|DELETE /backup/*` |
| Students | none | Full CRUD + import + export + promotion |
| Teachers | none | Full CRUD + import + portal management |
| Classes/Subjects | none | Full CRUD |
| Attendance | none | `GET|POST /attendance`, export |
| Assessments | none | `GET|PUT /report-cards/scores*` |
| Report Cards (view) | none | `GET /report-cards*`, compute, remarks |
| Report Cards (PDF) | `report_cards_pdf` | generate-all, generate, download |
| Admissions | none | Full CRUD + bulk |
| Fees | none | Full CRUD + payment + receipt |
| Feeding | `feeding_fees` | Full CRUD |
| Timetable | none | Full CRUD |
| SMS | `sms_notifications` | `GET|POST /sms*` |
| Announcements | none | Full CRUD |
| Audit | none | `GET /audit*` |
| Analytics | none | `GET /analytics*` |
| Finance | none | `GET /finance*` + expenses CRUD |
| Biometric | `biometric` | Full CRUD + enrol + sync |
| Lesson Notes | `lesson_notes` | View + approve + revision |
| Curriculum | `curriculum_management` | Strands + sub-strands CRUD |
| Billing | none | `GET /billing` |
| 2FA | none | Setup, confirm, disable |

### 6.6 Public Website Routes (`routes/website.php`)

Domain: `{slug}.{APP_DOMAIN}`  
Middleware: `resolve.tenant → subscription.website`

| Route | Purpose |
|---|---|
| `GET /` | School homepage |
| `GET /about` | About page |
| `GET /academics` | Academics overview |
| `GET /news` | News listing |
| `GET /gallery` | Gallery |
| `GET /contact`, `POST /contact` | Contact form |
| `GET /admissions`, `GET /admissions/apply` | Admissions info + form |
| `POST /admissions` | Application submission |
| `GET /admissions/status` | Application status check |
| `GET|POST /portal` | Parent portal login |
| `GET /portal/dashboard` | Parent dashboard |
| `POST /portal/logout` | Parent logout |
| `POST /portal/fees/{fee}/pay` | Online fee payment |
| `GET|POST /teacher/*` | Teacher portal (EnsureTeacherPortalAuth) |

### 6.7 REST API Routes (`routes/api.php`)

Base: `/api/v1/`

| Route | Middleware | Purpose |
|---|---|---|
| `GET /api/docs` | public | Swagger UI |
| `POST /auth/login` | throttle:10,1 | Authenticate, get token |
| `DELETE /auth/logout` | sanctum + api.tenant + feature:api_access | Revoke token |
| `GET /auth/me` | sanctum + api.tenant + feature:api_access | Current user |
| `GET /dashboard` | sanctum + api.tenant + feature:api_access | Dashboard stats |
| `GET /students` | sanctum + api.tenant + feature:api_access | Student list |
| `GET /students/{id}` | sanctum + api.tenant + feature:api_access | Student detail |
| `GET /classes` | sanctum + api.tenant + feature:api_access | Class list |
| `GET /classes/{id}` | sanctum + api.tenant + feature:api_access | Class detail |
| `GET|POST /attendance` | sanctum + api.tenant + feature:api_access | Attendance |
| `GET /announcements` | sanctum + api.tenant + feature:api_access | Announcements |
| `GET /timetable` | sanctum + api.tenant + feature:api_access | Timetable |
| `GET /assessments` | sanctum + api.tenant + feature:api_access | Assessment scores |

---

## 7. DATABASE STRUCTURE OVERVIEW

### 7.1 Core Tenant & Auth Tables

| Table | Key Columns | Purpose |
|---|---|---|
| `tenants` | id, uuid, slug, name, email, status, custom_domain, grading_settings (JSON), website_content (JSON), white-label columns (×11), current_term_id | School registry + configuration |
| `users` | id, name, email, password, role (super_admin/school_admin), tenant_id (nullable), login_attempts, locked_until, two_factor_secret, two_factor_enabled | Auth users |
| `personal_access_tokens` | tokenable_id, tokenable_type, name, token (hashed), expires_at | Sanctum API tokens |

### 7.2 Academic Calendar

| Table | Key Columns | Relationships |
|---|---|---|
| `academic_years` | id, year_label, is_current | Has many academic_terms |
| `academic_terms` | id, academic_year_id, term_number, term_name, start_date, end_date, is_current | Belongs to academic_year |

### 7.3 School Data (All with tenant_id)

| Table | Key Columns | Relationships |
|---|---|---|
| `school_classes` | id, tenant_id, name, level, capacity, class_teacher_id | Has many students, subjects (many-to-many) |
| `teachers` | id, tenant_id, name, email, phone, portal_password, portal_active, portal_last_login | Belongs to school_class (as class teacher) |
| `students` | id, tenant_id, full_name, admission_number, date_of_birth, gender, school_class_id, guardian_name/phone/email, status (active/inactive/graduated), deleted_at | Belongs to school_class |
| `subjects` | id, tenant_id, name, code | Many-to-many with school_classes |

### 7.4 Academic Records

| Table | Key Columns | Relationships |
|---|---|---|
| `attendances` | id, tenant_id, student_id, school_class_id, term_id, date, status (present/absent/late/excused) | Belongs to student, term |
| `assessments` | id, tenant_id, student_id, school_class_id, subject_id, term_id, ca_score, exam_score, total_score, grade, class_average, highest_score, lowest_score, position_in_class, deleted_at | Belongs to student, subject, term |
| `report_cards` | id, tenant_id, student_id, school_class_id, term_id, total_subjects, overall_position, out_of, attendance_present, attendance_total, pdf_path, generated_at, teacher_remarks, head_remarks, conduct_rating | Belongs to student, class, term |
| `promotions` | id, tenant_id, batch_id, student_id, from_class_id, to_class_id, status (promoted/retained), promoted_at | Bulk promotion records |

### 7.5 Admissions

| Table | Key Columns |
|---|---|
| `admissions` | id, tenant_id, student_id (nullable→filled on approval), first_name, last_name, dob, gender, guardian_*, desired_class_id, status (pending/approved/enrolled/rejected), term_id, deleted_at |

### 7.6 Finance

| Table | Key Columns | Relationships |
|---|---|---|
| `fees` | id, tenant_id, student_id, fee_id, type, amount, paid_amount, status (unpaid/partial/paid), due_date, deleted_at | Belongs to student |
| `payments` | id, tenant_id, subscription_id (nullable), fee_id (nullable), payment_type (subscription/fee), amount, currency, gateway, reference (unique), gateway_reference, status (pending/success/failed/reversed/disputed), paid_at, webhook_received_at, metadata (JSON) | Belongs to tenant, subscription or fee |
| `payment_ledger` | id, payment_id, tenant_id, state (pending/successful/failed/reversed/disputed), amount, currency, gateway, reference, payment_type, triggered_by, triggered_by_user_id, trigger_reason, gateway_payload (JSON), recorded_at | Immutable audit trail |
| `expenses` | id, tenant_id, description, amount, category, date, notes | Operating expenses |
| `invoices` | id, tenant_id, subscription_id, invoice_number, amount, currency, status, due_date, issued_at, paid_at, voided_at | SaaS subscription invoices |

### 7.7 Feeding

| Table | Key Columns |
|---|---|
| `feeding_configs` | id, tenant_id, school_class_id, amount_per_day, billing_cycle |
| `feeding_fees` | id, tenant_id, student_id, term_id, amount, paid_amount, status, is_exempt |
| `feeding_payments` | id, tenant_id, feeding_fee_id, amount, paid_at, reference |

### 7.8 Communication

| Table | Key Columns |
|---|---|
| `sms_logs` | id, tenant_id (nullable), message, recipients, status, gateway_response, sent_at |
| `announcements` | id, tenant_id, title, body, category, published_at, visibility |

### 7.9 Subscriptions & Billing

| Table | Key Columns |
|---|---|
| `subscription_packages` | id, name, slug, price, price_per_student, min_students, features (JSON) |
| `subscriptions` | id, tenant_id, academic_year_id, term_id, package_id, student_count, price_per_student, amount, start_date, end_date, grace_ends_at, status, is_trial, activated_at, locked_at |
| `subscription_notifications` | id, tenant_id, subscription_id, type (7_day_warning etc.), sent_at |

### 7.10 Lesson Planning

| Table | Key Columns |
|---|---|
| `lesson_notes` | id, tenant_id, teacher_id, school_class_id, subject_id, title, topic, objectives, content, resources, status (draft/submitted/approved/revision_requested), reviewed_at, reviewer_comments |
| `lesson_note_attachments` | id, lesson_note_id, filename, path, size |
| `schemes_of_work` | id, tenant_id, teacher_id, school_class_id, subject_id, term_id, title |
| `scheme_of_work_weeks` | id, scheme_of_work_id, week_number, topic, strand_id, activities, resources |
| `curriculum_strands` | id, tenant_id, subject_id, name, description |
| `curriculum_sub_strands` | id, curriculum_strand_id, name, content_standards |

### 7.11 Biometric

| Table | Key Columns |
|---|---|
| `biometric_devices` | id, tenant_id, name, device_serial, ip_address, port, is_active |
| `biometric_enrollments` | id, tenant_id, device_id, device_user_id, person_type (student/teacher), person_id |
| `biometric_logs` | id, tenant_id, device_id, device_user_id, verified_at, verify_type, direction, is_processed |

### 7.12 Audit

| Table | Key Columns |
|---|---|
| `audit_logs` | id, tenant_id, user_id, user_name, action (created/updated/deleted), auditable_type, auditable_id, auditable_label, old_values (JSON), new_values (JSON), ip_address, user_agent, created_at |

### 7.13 Tenant Isolation Pattern

**Every tenant-scoped table** has `tenant_id BIGINT UNSIGNED NOT NULL` with FK to `tenants.id` and `CASCADE ON DELETE`.

**Eloquent enforcement:** `HasTenantScope` trait adds a global scope that auto-injects `WHERE tenant_id = {currentTenant->id}` on every query.

**Composite indexes:** All performance-critical query patterns have `(tenant_id, status)`, `(tenant_id, student_id)`, `(tenant_id, term_id)` composite indexes — ensuring the optimizer uses the tenant filter efficiently.

---

## 8. FEATURE GATING ANALYSIS

### 8.1 Architecture

Feature gating operates at **three independent layers**:

**Layer 1 — Route Middleware (`feature:key`)**
Applied as `Route::middleware('feature:featureKey')->group()` blocks. `EnsureFeatureEnabled` middleware checks `FeatureGate::enabled()`. Returns 302 redirect with `feature_locked` flash for web requests, or 403 JSON for API.

**Layer 2 — Service Enforcement (`FeatureGate::require()`)**  
Controllers call `app(FeatureGate::class)->require('feature')` → `abort_unless(enabled, 403)`.

**Layer 3 — Blade UI Directives**  
`@feature('key') ... @endfeature` hides nav items and action buttons for tenants without access.

### 8.2 Tier Hierarchy

```
trial (0) < basic (1) < standard (2) < premium (3) < enterprise (4)
```

Tier resolved from `subscription_packages.slug` (substring matching):
- contains 'enterprise' → enterprise
- contains 'premium' → premium  
- contains 'standard' or 'growth' → standard
- contains 'basic' or 'starter' → basic
- no package / trial → trial

**Special cases:**
- Locked subscriptions → forced 'basic' tier (basic features still accessible)
- Super admin → always 'enterprise' (bypasses all gates)

### 8.3 Feature-to-Plan Matrix

| Feature | trial | basic | standard | premium | enterprise |
|---|---|---|---|---|---|
| students, teachers, classes | ❌ | ✅ | ✅ | ✅ | ✅ |
| attendance, fees, admissions | ❌ | ✅ | ✅ | ✅ | ✅ |
| timetable, announcements | ❌ | ✅ | ✅ | ✅ | ✅ |
| parent_portal, website | ❌ | ✅ | ✅ | ✅ | ✅ |
| report_cards (basic) | ❌ | ✅ | ✅ | ✅ | ✅ |
| sms_notifications | ❌ | ❌ | ✅ | ✅ | ✅ |
| assessments, lesson_notes | ❌ | ❌ | ✅ | ✅ | ✅ |
| feeding_fees, promotions | ❌ | ❌ | ✅ | ✅ | ✅ |
| data_export, analytics | ❌ | ❌ | ✅ | ✅ | ✅ |
| curriculum_management | ❌ | ❌ | ✅ | ✅ | ✅ |
| report_cards_pdf | ❌ | ❌ | ✅ | ✅ | ✅ |
| biometric, api_access | ❌ | ❌ | ❌ | ✅ | ✅ |
| custom_branding, teacher_portal | ❌ | ❌ | ❌ | ✅ | ✅ |
| advanced_analytics, bulk_sms | ❌ | ❌ | ❌ | ✅ | ✅ |
| payroll, multi_branch | ❌ | ❌ | ❌ | ❌ | ✅ |
| custom_domain, dedicated_support | ❌ | ❌ | ❌ | ❌ | ✅ |
| audit_log_export, sso | ❌ | ❌ | ❌ | ❌ | ✅ |

> **Note:** trial tier has 0 access to paid features. Feature usage in trial is limited by student/SMS/storage limits even if the feature flag allows.

### 8.4 Resource Limits

| Limit | trial | basic | standard | premium | enterprise |
|---|---|---|---|---|---|
| Storage (MB) | 100 | 500 | 2,000 | 10,000 | 50,000 |
| Max students | 50 | 500 | 1,000 | 5,000 | Unlimited |
| SMS/term | 50 | 0 | 500 | 2,000 | Unlimited |

### 8.5 Caching

Feature tier and individual feature enabled/disabled status are cached in Redis:
- Key: `feature:{tenant_id}:{feature_key}` — TTL 10 minutes
- Key: `feature_tier:{tenant_id}` — TTL 10 minutes
- Key: `subscription_status:{tenant_id}` — TTL 5 minutes

Cache is explicitly flushed (`FeatureGate::flushCache()`) whenever:
- A subscription is activated via webhook
- An admin manually transitions a subscription state
- A subscription package is changed

---

## 9. WHITE-LABEL SYSTEM ANALYSIS

### 9.1 Branding Fields (Tenant Model)

| Column | Type | Purpose | Helper Method |
|---|---|---|---|
| `favicon` | string (path) | School favicon | `faviconUrl()` → signed URL or default |
| `primary_color` | string (hex) | Primary brand color | `primaryColor()` |
| `secondary_color` | string (hex) | Accent color | `secondaryColor()` |
| `font_family` | string | CSS font family | Direct use in CSS |
| `sms_sender_id` | string (max 11) | SMS "From" name | `effectiveSmsSenderId()` |
| `email_from_name` | string | Mail "From" display | `effectiveEmailFromName()` |
| `email_from_address` | string | Mail "From" address | `effectiveEmailFromAddress()` |
| `email_header_color` | string (hex) | Email template header | Direct use in mail view |
| `custom_domain` | string | Enterprise custom FQDN | Domain resolution |
| `report_card_template` | enum (standard/compact/detailed) | PDF template variant | |
| `login_welcome_text` | text | Login page hero message | |
| `login_bg_color` | string (hex) | Login page background | |

### 9.2 Where Branding Is Applied

| Surface | What is customised |
|---|---|
| Login page | `login_welcome_text`, `login_bg_color`, school logo, school name |
| Admin portal header | School name, logo, primary color |
| Report card PDFs | School name, logo, `$primaryColor`, grade scale, report_card_template |
| Outbound emails | `email_from_name`, `email_from_address`, `email_header_color` |
| SMS messages | `sms_sender_id` as the sender name |
| Public website | School name, logo, colors |
| Parent portal | School name, logo |

### 9.3 Domain Resolution

Three-tier resolution in `ResolveTenantMiddleware`:
1. `tenants.custom_domain` match (enterprise)
2. Subdomain slug match (`{slug}.admin.domain`)
3. Domain alias table match

### 9.4 Feature Gating for White-Label

- `custom_domain` → enterprise tier
- `custom_branding` → premium tier (colors, fonts, login customisation)
- Basic branding (school name, logo) → all tiers

---

## 10. SECURITY OVERVIEW

### 10.1 Authentication System

| Mechanism | Implementation |
|---|---|
| Password hashing | bcrypt (Laravel Hashing, cost 12) |
| Session fixation | `$request->session()->regenerate()` on every successful login |
| Session invalidation | `invalidate()` + `regenerateToken()` on logout |
| Session encryption | `SESSION_ENCRYPT=true` (production) |
| Remember-me | Laravel default rotating token |
| TOTP 2FA | pragmarx/google2fa-laravel, RFC 6238, ±60s tolerance |
| Account lockout | 5 failed attempts → 15-min lock; `login_attempts` + `locked_until` columns |
| Teacher auth | Session-based, separate from Laravel Auth (`teacher_portal_id`) |
| Parent auth | Session-based, admission number + DOB verification |
| API auth | Sanctum Bearer token, 30-day expiry, hashed storage |

### 10.2 Authorization System

| Mechanism | Coverage |
|---|---|
| Role-based middleware | `EnsureSchoolAdmin`, `EnsureSuperAdmin`, `EnsureTeacherPortalAuth` |
| Gates & Policies | `StudentPolicy`, `TeacherPolicy`, `AdmissionPolicy`, `FeePolicy` |
| Super admin bypass | `Gate::before()` returns true for `isSuperAdmin()` |
| Feature gating | `EnsureFeatureEnabled` middleware + `FeatureGate::require()` |
| Tenant isolation | `HasTenantScope` global scope on all tenant models |

### 10.3 Tenant Isolation Enforcement

Five independent layers (defence in depth):
1. **Database scope** — `HasTenantScope` auto-injects `WHERE tenant_id`
2. **Middleware** — `ResolveTenantMiddleware` + `SetTenantFromToken` (API)
3. **Service** — `TenantContext::tenant()` throws if no tenant bound
4. **Storage** — `StorageService::assertOwnership()` rejects cross-tenant paths
5. **Dev guard** — `DB::listen()` warns on unscoped queries (non-production)

### 10.4 Transport Security

| Control | Status |
|---|---|
| HTTPS forced | `URL::forceScheme('https')` in production |
| HSTS | `Strict-Transport-Security: max-age=31536000; includeSubDomains` |
| Secure cookies | `SESSION_SECURE_COOKIE=true` |
| Proxy trust | `trustProxies(at: '*')` for load balancer |

### 10.5 Security Headers (`SecureHeadersMiddleware`)

Applied globally (all requests):

| Header | Value |
|---|---|
| `Content-Security-Policy` | `default-src 'self'; script-src 'self' 'unsafe-inline' cdn.tailwindcss.com` |
| `X-Frame-Options` | `SAMEORIGIN` |
| `X-Content-Type-Options` | `nosniff` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | `geolocation=(), microphone=(), camera=()` |
| `X-XSS-Protection` | `1; mode=block` |

### 10.6 Rate Limiting

| Endpoint | Limit |
|---|---|
| Login (admin + superadmin) | 6/min |
| 2FA challenge | 10/min |
| Teacher portal login | 6/min |
| Parent portal lookup | 6/min |
| Forgot/reset password | 5/min |
| `/health` | 60/min |
| Payment initiation | 10/min |
| SMS broadcast | 5/min |
| API (trial/basic) | 30 req/min |
| API (standard) | 60 req/min |
| API (premium) | 120 req/min |
| API (enterprise) | 300 req/min |

### 10.7 Input Validation & Data Protection

| Control | Implementation |
|---|---|
| Form validation | `$request->validate()` with typed rules on all endpoints |
| File upload whitelist | jpeg, jpg, png, webp only; max 2 MB |
| SQL injection | Eloquent ORM — all queries parameterised |
| XSS | Blade `{{ }}` auto-escaping on all output |
| Mass assignment | Explicit `$fillable` on all models |
| Passwords in logs | Scrubbed in `AuditObserver::SCRUBBED` list |
| `expose_php` | Off (production PHP config) |
| `display_errors` | Off (production PHP config) |

### 10.8 Webhook Security

| Control | Paystack | Moolre |
|---|---|---|
| Signature algorithm | HMAC-SHA512 | HMAC-SHA256 |
| Timestamp window | 5 minutes | 5 minutes |
| Idempotency | 30-min Redis lock per event ID | 30-min Redis lock per event ID |

### 10.9 File Security

- Private files served via signed URL route only (`/storage/signed/{path}`)
- `URL::hasValidSignature()` checked before serving
- `..` path traversal blocked
- Files served from private disk (not public web root)
- All uploads stored under `tenants/{tenant_id}/` namespace

---

## 11. PAYMENT & BILLING SYSTEM ANALYSIS

### 11.1 Payment Flow (Subscription)

```
School Admin initiates payment
  → Selects package on billing page
  → POST to payment initiation route
  → PaymentGatewayService::initiate()
     → Paystack: POST /transaction/initialize → authorization_url
     → Moolre:   POST /embed/src/start (state: starter) → authorization_url
  → Redirect to gateway URL
  → Admin completes payment on gateway
  → Gateway POSTs to webhook endpoint
  → WebhookController verifies HMAC signature
  → Verifies timestamp window (5 min)
  → Checks idempotency key (Redis, 30 min)
  → Checks Payment::isSuccess() guard (prevent double activation)
  → SubscriptionService::activateFromPayment()
     → Subscription::transitionToActive()
     → Tenant::status = 'active'
     → FeatureGate::flushCache()
     → SubscriptionService::flushCache()
  → PaymentObserver fires → PaymentLedger::record(state='successful')
```

### 11.2 Payment Gateways

| Property | Paystack | Moolre |
|---|---|---|
| Market | Ghana (primary) | West Africa (secondary) |
| Init endpoint | `POST /transaction/initialize` | `POST /embed/src/start` (state: starter) |
| Verify endpoint | Webhook-based | `POST /embed/src/start` (state: confirm) |
| Signature header | `X-Paystack-Signature` | `X-Moolre-Signature` |
| Algorithm | HMAC-SHA512 | HMAC-SHA256 |
| Webhook path | `/webhooks/paystack` | `/webhooks/moolre` |

### 11.3 Payment Ledger

Every Payment state change produces an immutable `payment_ledger` row:

- **States:** pending → successful / failed → reversed / disputed
- **Immutability:** `save()` on existing row throws `LogicException`; `delete()` always throws
- **Auto-recording:** `PaymentObserver` registered on `Payment` model; fires on `created` and `updated` (when `status` changes)
- **Evidence:** `gateway_payload` JSON column snapshots raw gateway response

### 11.4 Subscription Lifecycle State Machine

```
trial ─────────────────────────► active (payment confirmed)
  │                                 │
  └─► locked (trial expired)        └─► grace (term ended, no payment)
                                          │
                                          └─► locked (grace expired)
                                                │
                                                └─► suspended (manual, super admin)
```

Automated transitions via `CheckSubscriptionStatusJob` (daily 06:00 WAT):
- active/trial where `term.end_date < today` → grace
- grace where `grace_ends_at < now` → locked

### 11.5 Invoice System

- Invoices generated 1st of each month via `GenerateTermInvoicesCommand`
- `InvoiceService::calculateInvoiceAmount()` — student count × price per student (with minimum floor from package)
- Invoice states: draft → sent → paid → overdue → voided
- Super admin can mark paid or void

### 11.6 Reconciliation

`ReconcilePaymentsCommand` (weekly, Sunday 04:00 WAT):

| Detection | Description |
|---|---|
| Orphaned payments | Success status but no subscription linkage |
| Duplicate references | Same gateway reference, multiple payment rows |
| Unactivated subscriptions | Payment confirmed, subscription still pending |

`--heal` flag: auto-links orphaned payments to tenant's latest subscription.

---

## 12. SYSTEM LIMITATIONS / RISKS

### 12.1 Missing Features (Not Yet Implemented)

| Feature | Mentioned in Config | Implementation Status |
|---|---|---|
| Bus fee system | ❌ Not in features.php | **Not implemented** — no tables, no routes, no controllers |
| Scholarships | ❌ Not in features.php | **Not implemented** — no tables or logic |
| CBT Examinations | ❌ Not in features.php | **Not implemented** — no exam engine, no question bank |
| Payroll (staff) | ✅ Enterprise feature in config | **Not implemented** — listed as enterprise feature but no code exists |
| Multi-branch / Multi-campus | ✅ Enterprise feature in config | **Not implemented** — single tenant_id model, no branch hierarchy |
| SSO (Single Sign-On) | ✅ Enterprise feature in config | **Not implemented** — no SAML/OAuth provider configured |
| Auto payment retry | ⚠️ Gap | No automated failed-payment retry logic |
| Auto-renewal | ⚠️ Gap | Requires Paystack recurring billing API (not implemented) |
| Per-tenant database backup | ⚠️ Gap | Shared DB — only full DB backup exists |
| Student performance predictions / AI | ❌ | No ML/analytics beyond basic averages |
| Student ID card generation | ❌ | No ID card PDF feature |
| Library management | ❌ | No module |
| Hostel / boarding management | ❌ | No module |
| Transport route management | ❌ | Bus fees mentioned in prompt but absent from codebase |
| Parent-teacher messaging | ❌ | SMS to parents is one-way only; no reply/messaging system |

### 12.2 Architectural Weaknesses

| Weakness | Detail | Risk Level |
|---|---|---|
| Shared DB (single schema) | All tenants share one MySQL schema — a large tenant's queries affect others | Medium |
| Single VPS (Phase 0) | No load balancer, no second node, no failover | High (infrastructure) |
| No read replica | All reads hit the primary; dashboard stats cached 5 min | Medium (at scale) |
| `unsafe-inline` in CSP | Required by CDN Tailwind — XSS mitigation relies on input validation | Medium |
| Teacher auth not Laravel Auth | `teacher_portal_id` session — custom auth, not standard; lacks some Laravel auth features | Low |
| Parent auth is stateless (lookup) | No persistent parent account; admission number + DOB is weak identity verification | Medium |
| No SMS delivery receipts | `sms_logs` stores sent status but no delivery confirmation from Hubtel | Low |
| No automated test for restore command | `DatabaseRestoreCommand --verify-only` is not exercised in CI | Low |

### 12.3 Security Risks

| Risk | Detail | Severity |
|---|---|---|
| `unsafe-inline` CSP | Inline JS allowed; mitigated by input validation but not ideal | Medium |
| Weak parent identity | DOB + admission number — guessable if admission numbers are sequential | Medium |
| Service worker caches flash messages | PWA SW may cache auth-related flash on shared devices | Low |
| No IP-based tenant restriction | Any internet user can attempt login on any tenant subdomain | Low (rate limited) |
| Teacher password stored as bcrypt on teacher row | Not subject to standard Laravel Auth password policies | Low |

### 12.4 Scalability Risks

| Risk | Threshold | Mitigation |
|---|---|---|
| Shared database contention | > 200 concurrent schools | Read replica (Phase 1) |
| Single VPS SPOF | Any hardware failure | Second node (Phase 1) |
| PDF generation queue depth | > 100 simultaneous classes generating PDFs | Scale pdf queue workers |
| Redis memory | > 500 tenants with active caches | Redis memory limit + eviction policy |
| Student count growth | > 10,000 students per tenant | Schema-per-tenant migration (Phase 3) |

### 12.5 Business Logic Gaps

| Gap | Detail |
|---|---|
| No CBT engine | The prompt mentions CBT examinations as a module — it is entirely absent from the codebase |
| No bus/transport fees | Mentioned as a system feature in the audit prompt — not implemented |
| No scholarships module | Mentioned as a system feature — not implemented |
| No payroll engine | Listed as enterprise feature in config — no implementation code |
| Feeding fee not linked to attendance | Feeding payment is independent of who actually ate on a given day |
| Report card template variants | `report_card_template` column supports standard/compact/detailed but only one template view exists |

---

## 13. FINAL SYSTEM SCORECARD

### 13.1 Category Ratings

| Category | Score | Rationale |
|---|---|---|
| **Architecture Quality** | **8.5/10** | Clean multi-tenant isolation, strong service layer, well-structured Eloquent models, proper use of observers and jobs. Loses 1.5 points for shared-schema scalability ceiling and custom teacher auth. |
| **Security** | **9.0/10** | Defence-in-depth at 5 layers, TOTP 2FA, account lockout, HMAC webhook verification, immutable ledger, signed file URLs, full security header suite. Loses 1 point for CSP `unsafe-inline` and weak parent identity verification. |
| **Scalability** | **7.0/10** | Well-indexed, Redis-backed, async queued. Current Phase 0 (single VPS, shared DB) is the ceiling. Read replica and second node are Phase 1 — not yet delivered. Shared-schema architecture limits ultimate tenant count without schema split. |
| **Maintainability** | **9.0/10** | PHPStan Level 5, 704 tests, comprehensive DocBlocks, CLAUDE.md AI context doc, 28 reports, strict model behaviour in dev. Minor deduction for CDN Tailwind (no asset pipeline in web layer). |
| **SaaS Readiness** | **8.5/10** | Full subscription lifecycle, dual gateways, feature gating at 3 layers, tenant self-registration, invoice system, SaaS metrics dashboard. Loses 1.5 for missing auto-renewal, missing payment retry automation, and missing CBT/payroll/bus modules that are expected in a full school SaaS. |
| **Commercial Readiness** | **8.0/10** | Deployable, installable, documented, CI/CD pipeline, Docker stack. Loses 2 points because several features mentioned in the product brief (bus fees, scholarships, CBT, payroll, multi-branch) are absent from the codebase, creating a gap between marketed capabilities and delivered software. |

### 13.2 Summary Table

| Dimension | Score | Status |
|---|---|---|
| Architecture Quality | 8.5/10 | ✅ Production Quality |
| Security | 9.0/10 | ✅ Enterprise Grade |
| Scalability | 7.0/10 | ⚠️ Phase 0 Limitation |
| Maintainability | 9.0/10 | ✅ Excellent |
| SaaS Readiness | 8.5/10 | ✅ Commercially Viable |
| Commercial Readiness | 8.0/10 | ⚠️ Feature Gaps Exist |
| **OVERALL** | **8.3/10** | **✅ Production Ready (Core Modules)** |

### 13.3 Recommended Priority Actions (Strictly Observational)

> *The following are observations based on the gap between stated features and implemented code. No code changes are implied.*

1. **CBT Examination Engine** — Absent entirely. If this is a marketed feature, it represents a significant product gap.
2. **Bus/Transport Fee Module** — Not implemented despite being listed as a system feature in the brief.
3. **Scholarship Module** — Not implemented.
4. **Auto-Payment Retry** — Failed payments require manual re-initiation.
5. **Parent Identity Hardening** — Admission number + DOB alone is weak. A PIN or OTP would strengthen parent portal security.
6. **Multi-Branch Support** — Enterprise tier feature listed in `config/features.php` but has no database or code implementation.
7. **Report Card Template Variants** — Three variants defined (standard/compact/detailed) but only one PDF template view exists.
8. **Restore Command Testing** — `DatabaseRestoreCommand --verify-only` is not exercised in CI.

---

## APPENDIX A — Files Audited

| Category | Count |
|---|---|
| Route files | 5 |
| Middleware classes | 10 |
| Admin controllers | 25 |
| Super admin controllers | 8 |
| API controllers | 8 |
| Auth controllers | 4 |
| Models | 37 |
| Services | 15 |
| Jobs | 4 |
| Artisan commands | 9 |
| Migrations | 52 |
| Config files | 3 (app, features, billing) |
| **Total** | **180+ files** |

---

## APPENDIX B — Confirmed Implemented vs Stated

| Feature (from audit prompt) | Implemented | Notes |
|---|---|---|
| Admissions | ✅ Full | Online form + admin pipeline |
| Student management | ✅ Full | CRUD + import + export + promotion |
| Teacher management | ✅ Full | CRUD + teacher portal |
| Attendance | ✅ Full | Manual + biometric |
| Fees & payments | ✅ Full | Manual + online via gateway |
| Feeding fee system | ✅ Full | Config + assign + pay + exempt |
| **Bus fee system** | ❌ **Missing** | Not implemented |
| **Scholarships** | ❌ **Missing** | Not implemented |
| **CBT examinations** | ❌ **Missing** | Not implemented |
| Report cards / marksheets | ✅ Full | Statistics + PDF + email |
| Lesson notes | ✅ Full | Teacher workflow + admin review |
| Lesson planning / schemes | ✅ Full | Schemes of work + curriculum |
| SMS/notifications | ✅ Full | Hubtel integration + broadcast |
| White-label system | ✅ Full | 11 branding fields + 3-layer application |
| SaaS billing + feature gating | ✅ Full | 5-tier, 33 features, 3-layer gate |
| Admin dashboard | ✅ Full | Stats + audit + analytics |
| Super admin dashboard | ✅ Full | SaaS metrics + tenant management |

---

*Document generated: 2026-05-16*  
*Audit basis: Full static codebase analysis — 180+ files across routes, controllers, models, services, migrations, configuration*  
*704/704 tests green at time of audit*
