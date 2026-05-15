<?php

use App\Http\Controllers\Admin\AdmissionController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\BiometricController;
use App\Http\Controllers\Admin\AssessmentController;
use App\Http\Controllers\Admin\CurriculumController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\LessonNoteController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\FeeController;
use App\Http\Controllers\Admin\FeedingController;
use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\ReportCardController;
use App\Http\Controllers\Admin\SchoolClassController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Admin\SmsController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\TeacherController;
use App\Http\Controllers\Admin\TimetableController;
use Illuminate\Support\Facades\Route;

// ── Dashboard ────────────────────────────────────────────────────────────────
Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');

// ── API Documentation ─────────────────────────────────────────────────────────
Route::get('/api-docs', fn () => redirect(url('/api/docs')))->name('admin.api.docs');

// ── Subscription ─────────────────────────────────────────────────────────────
Route::get('/subscription', fn () => view('admin.subscription.index'))->name('admin.subscription');

// ── Settings ──────────────────────────────────────────────────────────────────
Route::get   ('/settings',                  [SettingsController::class, 'school'])         ->name('admin.settings.school');
Route::put   ('/settings',                  [SettingsController::class, 'updateSchool'])   ->name('admin.settings.school.update');
Route::get   ('/settings/grading',          [SettingsController::class, 'grading'])        ->name('admin.settings.grading');
Route::put   ('/settings/grading',          [SettingsController::class, 'updateGrading'])  ->name('admin.settings.grading.update');
Route::delete('/settings/grading/reset',    [SettingsController::class, 'resetGrading'])   ->name('admin.settings.grading.reset');
Route::get   ('/settings/website',          [SettingsController::class, 'websiteContent'])       ->name('admin.settings.website');
Route::put   ('/settings/website',          [SettingsController::class, 'updateWebsiteContent']) ->name('admin.settings.website.update');
Route::get   ('/settings/account',          [SettingsController::class, 'account'])        ->name('admin.settings.account');
Route::put   ('/settings/account',          [SettingsController::class, 'updateAccount'])  ->name('admin.settings.account.update');
Route::get   ('/settings/calendar',         [SettingsController::class, 'calendar'])       ->name('admin.settings.calendar');
Route::put   ('/settings/calendar',         [SettingsController::class, 'updateCalendar']) ->name('admin.settings.calendar.update');

// ── Backup / Full Data Export ─────────────────────────────────────────────────
Route::get ('/backup',                   [BackupController::class, 'index'])    ->name('admin.backup.index');
Route::post('/backup',                   [BackupController::class, 'store'])    ->name('admin.backup.store');
Route::get ('/backup/{id}/download',     [BackupController::class, 'download']) ->name('admin.backup.download');
Route::delete('/backup/{id}',            [BackupController::class, 'destroy'])  ->name('admin.backup.destroy');

// ── Two-Factor Authentication ─────────────────────────────────────────────────
Route::get ('/settings/2fa',           [TwoFactorController::class, 'showSetup'])    ->name('admin.2fa.setup');
Route::post('/settings/2fa/confirm',   [TwoFactorController::class, 'confirmSetup']) ->name('admin.2fa.confirm');
Route::post('/settings/2fa/disable',   [TwoFactorController::class, 'disable'])      ->name('admin.2fa.disable');

// ── Student Promotion ─────────────────────────────────────────────────────────
Route::get ('/students/promotion',         [PromotionController::class, 'index'])   ->name('admin.promotion');
Route::post('/students/promotion/preview', [PromotionController::class, 'preview']) ->name('admin.promotion.preview');
Route::post('/students/promotion/execute', [PromotionController::class, 'execute']) ->name('admin.promotion.execute');
Route::get ('/students/promotion/history', [PromotionController::class, 'history']) ->name('admin.promotion.history');

// ── Students ─────────────────────────────────────────────────────────────────
Route::get   ('/students',                        [StudentController::class, 'index'])          ->name('admin.students');
Route::get   ('/students/export',                 [ExportController::class,  'students'])        ->name('admin.students.export');
Route::get   ('/students/import',                 [ImportController::class,  'studentForm'])     ->name('admin.students.import');
Route::get   ('/students/import/template',        [ImportController::class,  'studentTemplate']) ->name('admin.students.import.template');
Route::post  ('/students/import',                 [ImportController::class,  'studentImport'])   ->name('admin.students.import.store');
Route::get   ('/students/create',                 [StudentController::class, 'create'])          ->name('admin.students.create');
Route::post  ('/students',                        [StudentController::class, 'store'])           ->name('admin.students.store');
Route::get   ('/students/{student}',              [StudentController::class, 'show'])            ->name('admin.students.show');
Route::get   ('/students/{student}/transcript',   [StudentController::class, 'transcript'])      ->name('admin.students.transcript');
Route::get   ('/students/{student}/edit',         [StudentController::class, 'edit'])            ->name('admin.students.edit');
Route::put   ('/students/{student}',              [StudentController::class, 'update'])          ->name('admin.students.update');
Route::delete('/students/{student}',              [StudentController::class, 'destroy'])         ->name('admin.students.destroy');

// ── Teachers ──────────────────────────────────────────────────────────────────
Route::get   ('/teachers',                 [TeacherController::class, 'index'])           ->name('admin.teachers');
Route::get   ('/teachers/import',          [ImportController::class,  'teacherForm'])      ->name('admin.teachers.import');
Route::get   ('/teachers/import/template', [ImportController::class,  'teacherTemplate'])  ->name('admin.teachers.import.template');
Route::post  ('/teachers/import',          [ImportController::class,  'teacherImport'])    ->name('admin.teachers.import.store');
Route::get   ('/teachers/create',          [TeacherController::class, 'create'])           ->name('admin.teachers.create');
Route::post  ('/teachers',                 [TeacherController::class, 'store'])            ->name('admin.teachers.store');
Route::get   ('/teachers/{teacher}',       [TeacherController::class, 'show'])             ->name('admin.teachers.show');
Route::get   ('/teachers/{teacher}/edit',  [TeacherController::class, 'edit'])             ->name('admin.teachers.edit');
Route::put   ('/teachers/{teacher}',       [TeacherController::class, 'update'])           ->name('admin.teachers.update');
Route::delete('/teachers/{teacher}',                        [TeacherController::class, 'destroy'])            ->name('admin.teachers.destroy');
Route::post  ('/teachers/{teacher}/portal/activate',       [TeacherController::class, 'portalActivate'])      ->name('admin.teachers.portal.activate');
Route::post  ('/teachers/{teacher}/portal/deactivate',     [TeacherController::class, 'portalDeactivate'])    ->name('admin.teachers.portal.deactivate');
Route::post  ('/teachers/{teacher}/portal/reset-password', [TeacherController::class, 'portalResetPassword']) ->name('admin.teachers.portal.reset');

// ── Classes & Subjects ────────────────────────────────────────────────────────
Route::get   ('/classes',                        [SchoolClassController::class, 'index'])         ->name('admin.classes');
Route::get   ('/classes/create',                 [SchoolClassController::class, 'create'])        ->name('admin.classes.create');
Route::post  ('/classes',                        [SchoolClassController::class, 'store'])         ->name('admin.classes.store');
Route::get   ('/classes/{schoolClass}',          [SchoolClassController::class, 'show'])          ->name('admin.classes.show');
Route::get   ('/classes/{schoolClass}/edit',     [SchoolClassController::class, 'edit'])          ->name('admin.classes.edit');
Route::put   ('/classes/{schoolClass}',          [SchoolClassController::class, 'update'])        ->name('admin.classes.update');
Route::delete('/classes/{schoolClass}',          [SchoolClassController::class, 'destroy'])       ->name('admin.classes.destroy');
Route::get   ('/classes/{schoolClass}/subjects', [SchoolClassController::class, 'subjects'])      ->name('admin.classes.subjects');
Route::post  ('/classes/{schoolClass}/subjects', [SchoolClassController::class, 'storeSubject'])  ->name('admin.classes.subjects.store');
Route::delete('/classes/{schoolClass}/subjects/{subject}', [SchoolClassController::class, 'destroySubject'])->name('admin.classes.subjects.destroy');

// ── Attendance ────────────────────────────────────────────────────────────────
Route::get ('/attendance',        [AttendanceController::class, 'index'])  ->name('admin.attendance');
Route::get ('/attendance/sheet',  [AttendanceController::class, 'sheet'])  ->name('admin.attendance.sheet');
Route::post('/attendance/save',   [AttendanceController::class, 'save'])   ->name('admin.attendance.save');
Route::get ('/attendance/report', [AttendanceController::class, 'report']) ->name('admin.attendance.report');

// ── Score Entry (Assessments) ─────────────────────────────────────────────────
Route::get('/report-cards/scores',        [AssessmentController::class, 'index'])  ->name('admin.assessments.index');
Route::get('/report-cards/scores/edit',   [AssessmentController::class, 'edit'])   ->name('admin.assessments.edit');
Route::put('/report-cards/scores/update', [AssessmentController::class, 'update']) ->name('admin.assessments.update');

// ── Report Cards ──────────────────────────────────────────────────────────────
Route::get ('/report-cards',                             [ReportCardController::class, 'index'])        ->name('admin.report-cards');
Route::get ('/report-cards/class',                       [ReportCardController::class, 'classCards'])   ->name('admin.report-cards.class');
Route::get ('/report-cards/batch-status',                [ReportCardController::class, 'batchStatus'])  ->name('admin.report-cards.batch-status');
Route::post('/report-cards/compute',                     [ReportCardController::class, 'computeStats']) ->name('admin.report-cards.compute');
Route::post('/report-cards/generate-all',                [ReportCardController::class, 'generateAll'])  ->name('admin.report-cards.generate-all');
Route::post('/report-cards/{reportCard}/generate',       [ReportCardController::class, 'generate'])     ->name('admin.report-cards.generate');
Route::get ('/report-cards/{reportCard}/download',       [ReportCardController::class, 'download'])     ->name('admin.report-cards.download');
Route::get ('/report-cards/{reportCard}/remarks',        [ReportCardController::class, 'editRemarks'])  ->name('admin.report-cards.remarks');
Route::put ('/report-cards/{reportCard}/remarks',        [ReportCardController::class, 'updateRemarks'])->name('admin.report-cards.remarks.update');

// ── Admissions ────────────────────────────────────────────────────────────────
Route::get   ('/admissions',                      [AdmissionController::class, 'index'])      ->name('admin.admissions.index');
Route::post  ('/admissions/bulk',                  [AdmissionController::class, 'bulkUpdate']) ->name('admin.admissions.bulk');
Route::get   ('/admissions/{admission}',           [AdmissionController::class, 'show'])       ->name('admin.admissions.show');
Route::post  ('/admissions/{admission}/accept',    [AdmissionController::class, 'accept'])     ->name('admin.admissions.accept');
Route::post  ('/admissions/{admission}/reject',    [AdmissionController::class, 'reject'])     ->name('admin.admissions.reject');
Route::get   ('/admissions/{admission}/enroll',    [AdmissionController::class, 'enrollForm']) ->name('admin.admissions.enroll.form');
Route::post  ('/admissions/{admission}/enroll',    [AdmissionController::class, 'enroll'])     ->name('admin.admissions.enroll');
Route::delete('/admissions/{admission}',           [AdmissionController::class, 'destroy'])    ->name('admin.admissions.destroy');

// ── Fees ──────────────────────────────────────────────────────────────────────
Route::get   ('/fees',                  [FeeController::class,    'index'])         ->name('admin.fees');
Route::get   ('/fees/summary',          [FeeController::class,    'summary'])       ->name('admin.fees.summary');
Route::get   ('/fees/bulk',             [FeeController::class,    'bulkCreate'])    ->name('admin.fees.bulk');
Route::post  ('/fees/bulk',             [FeeController::class,    'bulkStore'])     ->name('admin.fees.bulk.store');
Route::get   ('/fees/export',           [ExportController::class, 'fees'])          ->name('admin.fees.export');
Route::get   ('/fees/create',           [FeeController::class,    'create'])        ->name('admin.fees.create');
Route::post  ('/fees',                  [FeeController::class,    'store'])         ->name('admin.fees.store');
Route::get   ('/fees/{fee}/edit',       [FeeController::class,    'edit'])          ->name('admin.fees.edit');
Route::get   ('/fees/{fee}/receipt',    [FeeController::class,    'receipt'])       ->name('admin.fees.receipt');
Route::put   ('/fees/{fee}',            [FeeController::class,    'update'])        ->name('admin.fees.update');
Route::post  ('/fees/{fee}/payment',    [FeeController::class,    'recordPayment']) ->name('admin.fees.payment');
Route::delete('/fees/{fee}',            [FeeController::class,    'destroy'])       ->name('admin.fees.destroy');

// ── Feeding Fees ──────────────────────────────────────────────────────────────
Route::get   ('/feeding',                        [FeedingController::class, 'index'])           ->name('admin.feeding.index');
Route::get   ('/feeding/config',                 [FeedingController::class, 'config'])          ->name('admin.feeding.config');
Route::put   ('/feeding/config',                 [FeedingController::class, 'updateConfig'])    ->name('admin.feeding.config.update');
Route::put   ('/feeding/config/class/{classId}', [FeedingController::class, 'updateClassConfig'])->name('admin.feeding.config.class');
Route::get   ('/feeding/assign',                 [FeedingController::class, 'assignForm'])      ->name('admin.feeding.assign');
Route::post  ('/feeding/assign',                 [FeedingController::class, 'assign'])          ->name('admin.feeding.assign.store');
Route::get   ('/feeding/report',                 [FeedingController::class, 'report'])          ->name('admin.feeding.report');
Route::get   ('/feeding/{id}',                   [FeedingController::class, 'show'])            ->name('admin.feeding.show');
Route::post  ('/feeding/{id}/pay',               [FeedingController::class, 'pay'])             ->name('admin.feeding.pay');
Route::post  ('/feeding/{id}/exempt',            [FeedingController::class, 'exempt'])          ->name('admin.feeding.exempt');
Route::get   ('/feeding/receipt/{paymentId}',    [FeedingController::class, 'receipt'])         ->name('admin.feeding.receipt');

// ── Attendance export ─────────────────────────────────────────────────────────
Route::get('/attendance/export', [ExportController::class, 'attendance'])->name('admin.attendance.export');

// ── Assessment export ─────────────────────────────────────────────────────────
Route::get('/assessments/export', [ExportController::class, 'assessments'])->name('admin.assessments.export');

// ── Timetable ─────────────────────────────────────────────────────────────────
Route::get   ('/timetables',                             [TimetableController::class, 'index'])          ->name('admin.timetables.index');
Route::get   ('/timetables/create',                      [TimetableController::class, 'create'])         ->name('admin.timetables.create');
Route::post  ('/timetables',                             [TimetableController::class, 'store'])          ->name('admin.timetables.store');
Route::get   ('/timetables/{timetable}/edit',            [TimetableController::class, 'edit'])           ->name('admin.timetables.edit');
Route::put   ('/timetables/{timetable}',                 [TimetableController::class, 'update'])         ->name('admin.timetables.update');
Route::delete('/timetables/{timetable}',                 [TimetableController::class, 'destroy'])        ->name('admin.timetables.destroy');
Route::get   ('/timetables/subjects/{schoolClass}',      [TimetableController::class, 'subjectsForClass'])->name('admin.timetables.subjects');

// ── SMS / WhatsApp ─────────────────────────────────────────────────────────────
Route::get ('/sms',      [SmsController::class, 'index'])->name('admin.sms.index');
Route::post('/sms/send', [SmsController::class, 'send']) ->name('admin.sms.send');

// ── Announcements ─────────────────────────────────────────────────────────────
Route::get   ('/announcements',                      [AnnouncementController::class, 'index'])  ->name('admin.announcements.index');
Route::get   ('/announcements/create',               [AnnouncementController::class, 'create']) ->name('admin.announcements.create');
Route::post  ('/announcements',                      [AnnouncementController::class, 'store'])  ->name('admin.announcements.store');
Route::get   ('/announcements/{announcement}/edit',  [AnnouncementController::class, 'edit'])   ->name('admin.announcements.edit');
Route::put   ('/announcements/{announcement}',       [AnnouncementController::class, 'update']) ->name('admin.announcements.update');
Route::delete('/announcements/{announcement}',       [AnnouncementController::class, 'destroy'])->name('admin.announcements.destroy');

// ── Audit Trail ───────────────────────────────────────────────────────────────
Route::get('/audit',      [AuditController::class, 'index']) ->name('admin.audit.index');
Route::get('/audit/{auditLog}', [AuditController::class, 'show'])  ->name('admin.audit.show');

// ── Analytics ─────────────────────────────────────────────────────────────────
Route::get('/analytics',            [AnalyticsController::class, 'index'])     ->name('admin.analytics.index');
Route::get('/analytics/export/csv', [AnalyticsController::class, 'exportCsv']) ->name('admin.analytics.export.csv');
Route::get('/analytics/export/pdf', [AnalyticsController::class, 'exportPdf']) ->name('admin.analytics.export.pdf');

// ── Finance ───────────────────────────────────────────────────────────────────
Route::get('/finance',           [FinanceController::class, 'index'])    ->name('admin.finance.index');
Route::get('/finance/ledger',    [FinanceController::class, 'ledger'])   ->name('admin.finance.ledger');
Route::get('/finance/statement', [FinanceController::class, 'statement'])->name('admin.finance.statement');

Route::get   ('/finance/expenses/create',        [FinanceController::class, 'createExpense']) ->name('admin.finance.expenses.create');
Route::post  ('/finance/expenses',               [FinanceController::class, 'storeExpense'])  ->name('admin.finance.expenses.store');
Route::get   ('/finance/expenses/{expense}/edit',[FinanceController::class, 'editExpense'])   ->name('admin.finance.expenses.edit');
Route::put   ('/finance/expenses/{expense}',     [FinanceController::class, 'updateExpense']) ->name('admin.finance.expenses.update');
Route::delete('/finance/expenses/{expense}',     [FinanceController::class, 'destroyExpense'])->name('admin.finance.expenses.destroy');

// ── Biometric Attendance ───────────────────────────────────────────────────────
Route::get   ('/biometric',                                   [BiometricController::class, 'index'])          ->name('admin.biometric.index');
Route::get   ('/biometric/recent',                            [BiometricController::class, 'recentLogs'])     ->name('admin.biometric.recent');
Route::get   ('/biometric/create',                            [BiometricController::class, 'create'])         ->name('admin.biometric.create');
Route::post  ('/biometric',                                   [BiometricController::class, 'store'])          ->name('admin.biometric.store');
Route::get   ('/biometric/{device}/edit',                     [BiometricController::class, 'edit'])           ->name('admin.biometric.edit');
Route::put   ('/biometric/{device}',                          [BiometricController::class, 'update'])         ->name('admin.biometric.update');
Route::delete('/biometric/{device}',                          [BiometricController::class, 'destroy'])        ->name('admin.biometric.destroy');
Route::post  ('/biometric/{device}/sync',                     [BiometricController::class, 'sync'])           ->name('admin.biometric.sync');
Route::get   ('/biometric/{device}/enroll',                   [BiometricController::class, 'enroll'])         ->name('admin.biometric.enroll');
Route::post  ('/biometric/{device}/enroll',                   [BiometricController::class, 'storeEnrollment'])->name('admin.biometric.enroll.store');
Route::delete('/biometric/enrollments/{enrollment}',          [BiometricController::class, 'destroyEnrollment'])->name('admin.biometric.enroll.destroy');

// ── Lesson Notes (admin review) ───────────────────────────────────────────────
Route::get  ('/lesson-notes',                      [LessonNoteController::class, 'index'])          ->name('admin.lesson-notes.index');
Route::get  ('/lesson-notes/schemes',              [LessonNoteController::class, 'schemes'])         ->name('admin.lesson-notes.schemes');
Route::get  ('/lesson-notes/schemes/{id}',         [LessonNoteController::class, 'schemeShow'])      ->name('admin.lesson-notes.scheme-show');
Route::get  ('/lesson-notes/{id}',                 [LessonNoteController::class, 'show'])            ->name('admin.lesson-notes.show');
Route::post ('/lesson-notes/{id}/approve',         [LessonNoteController::class, 'approve'])         ->name('admin.lesson-notes.approve');
Route::post ('/lesson-notes/{id}/revision',        [LessonNoteController::class, 'requestRevision']) ->name('admin.lesson-notes.revision');

// ── Curriculum Management ─────────────────────────────────────────────────────
Route::get   ('/curriculum',                                [CurriculumController::class, 'index'])            ->name('admin.curriculum.index');
Route::post  ('/curriculum/strands',                        [CurriculumController::class, 'storeStrand'])      ->name('admin.curriculum.strands.store');
Route::put   ('/curriculum/strands/{id}',                   [CurriculumController::class, 'updateStrand'])     ->name('admin.curriculum.strands.update');
Route::delete('/curriculum/strands/{id}',                   [CurriculumController::class, 'destroyStrand'])    ->name('admin.curriculum.strands.destroy');
Route::post  ('/curriculum/sub-strands',                    [CurriculumController::class, 'storeSubStrand'])   ->name('admin.curriculum.sub-strands.store');
Route::put   ('/curriculum/sub-strands/{id}',               [CurriculumController::class, 'updateSubStrand'])  ->name('admin.curriculum.sub-strands.update');
Route::delete('/curriculum/sub-strands/{id}',               [CurriculumController::class, 'destroySubStrand']) ->name('admin.curriculum.sub-strands.destroy');
