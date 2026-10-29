<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Middleware\ReadOnlyDemo;
use App\Http\Middleware\RequirePasswordChange;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\FacultyController;
use App\Http\Controllers\CommunicationController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\FacultyPortalController;
use App\Http\Controllers\FeeController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\AdmissionController;
use App\Http\Controllers\TimetableController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StudentPortalController;
use App\Http\Controllers\StudentGroupController;
use App\Http\Controllers\SubjectController;

Route::get('/app-config', [AuthController::class, 'appConfig']);

Route::prefix('auth')->group(function () {
    Route::get('/demo-roles', [AuthController::class, 'demoRoles']);

    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/login',           [AuthController::class, 'login']);
        Route::post('/demo-login',      [AuthController::class, 'demoLogin']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password',  [AuthController::class, 'resetPassword']);
    });
});

Route::middleware(['auth:sanctum', ReadOnlyDemo::class, RequirePasswordChange::class])->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('/logout',         [AuthController::class, 'logout']);
        Route::get('/me',              [AuthController::class, 'me']);
        Route::put('/profile',         [AuthController::class, 'updateProfile']);
        Route::put('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/avatar',         [AuthController::class, 'uploadAvatar']);
    });

    // Student self-service portal: always the caller's own data
    Route::prefix('me/student')->group(function () {
        Route::get('/overview',   [StudentPortalController::class, 'overview'])->middleware('permission:grades.own');
        Route::get('/courses',    [StudentPortalController::class, 'courses'])->middleware('permission:grades.own');
        Route::get('/grades',     [StudentPortalController::class, 'grades'])->middleware('permission:grades.own');
        Route::get('/attendance', [StudentPortalController::class, 'attendance'])->middleware('permission:attendance.own');
        Route::get('/fees',       [StudentPortalController::class, 'fees'])->middleware('permission:fees.own');
        Route::get('/timetable',  [StudentPortalController::class, 'timetable'])->middleware('permission:timetable.read');
    });

    // Faculty self-service portal: the caller's own courses and schedule
    Route::prefix('me/faculty')->group(function () {
        Route::get('/overview',  [FacultyPortalController::class, 'overview'])->middleware('permission:attendance.read');
        Route::get('/courses',   [FacultyPortalController::class, 'courses'])->middleware('permission:attendance.read');
        Route::get('/timetable', [FacultyPortalController::class, 'timetable'])->middleware('permission:timetable.read');
    });

    Route::prefix('users')->group(function () {
        Route::get('/stats',                [UserController::class, 'stats'])->middleware('permission:users.read');
        Route::get('/export',               [UserController::class, 'export'])->middleware('permission:users.export');
        Route::post('/bulk-action',         [UserController::class, 'bulkAction'])->middleware('permission:users.edit|users.delete');
        Route::get('/',                     [UserController::class, 'index'])->middleware('permission:users.read');
        Route::post('/',                    [UserController::class, 'store'])->middleware('permission:users.create');
        Route::get('/{id}',                 [UserController::class, 'show'])->middleware('permission:users.read');
        Route::put('/{id}',                 [UserController::class, 'update'])->middleware('permission:users.edit');
        Route::delete('/{id}',              [UserController::class, 'destroy'])->middleware('permission:users.delete');
        Route::post('/{id}/restore',        [UserController::class, 'restore'])->middleware('permission:users.delete');
        Route::patch('/{id}/status',        [UserController::class, 'updateStatus'])->middleware('permission:users.edit');
        Route::patch('/{id}/role',          [UserController::class, 'updateRole'])->middleware('permission:users.edit');
        Route::post('/{id}/avatar',         [UserController::class, 'uploadAvatar'])->middleware('permission:users.edit');
        Route::post('/{id}/reset-password', [UserController::class, 'resetPassword'])->middleware('permission:users.edit');
        Route::get('/{id}/activity',        [UserController::class, 'activity'])->middleware('permission:users.read');
    });

    Route::prefix('departments')->group(function () {
        Route::get('/',        [DepartmentController::class, 'index']);
        Route::post('/',       [DepartmentController::class, 'store'])->middleware('permission:departments.create');
        Route::get('/{id}',    [DepartmentController::class, 'show']);
        Route::put('/{id}',    [DepartmentController::class, 'update'])->middleware('permission:departments.edit');
        Route::delete('/{id}', [DepartmentController::class, 'destroy'])->middleware('permission:departments.delete');
    });

    Route::prefix('programs')->group(function () {
        Route::get('/',        [ProgramController::class, 'index']);
        Route::post('/',       [ProgramController::class, 'store'])->middleware('permission:programs.create');
        Route::put('/{id}',    [ProgramController::class, 'update'])->middleware('permission:programs.edit');
        Route::delete('/{id}', [ProgramController::class, 'destroy'])->middleware('permission:programs.delete');
    });

    Route::prefix('students')->group(function () {
        Route::get('/stats',         [StudentController::class, 'stats'])->middleware('permission:students.read');
        Route::get('/export',        [StudentController::class, 'export'])->middleware('permission:students.export');
        Route::get('/',              [StudentController::class, 'index'])->middleware('permission:students.read');
        Route::post('/',             [StudentController::class, 'store'])->middleware('permission:students.create');
        Route::get('/{id}',          [StudentController::class, 'show'])->middleware('permission:students.read');
        Route::put('/{id}',          [StudentController::class, 'update'])->middleware('permission:students.edit');
        Route::delete('/{id}',       [StudentController::class, 'destroy'])->middleware('permission:students.delete');
        Route::patch('/{id}/status', [StudentController::class, 'updateStatus'])->middleware('permission:students.edit');
    });

    Route::prefix('faculty')->group(function () {
        Route::get('/stats',         [FacultyController::class, 'stats'])->middleware('permission:faculty.read');
        Route::get('/export',        [FacultyController::class, 'export'])->middleware('permission:faculty.export');
        Route::get('/',              [FacultyController::class, 'index'])->middleware('permission:faculty.read');
        Route::post('/',             [FacultyController::class, 'store'])->middleware('permission:faculty.create');
        Route::get('/{id}',          [FacultyController::class, 'show'])->middleware('permission:faculty.read');
        Route::put('/{id}',          [FacultyController::class, 'update'])->middleware('permission:faculty.edit');
        Route::delete('/{id}',       [FacultyController::class, 'destroy'])->middleware('permission:faculty.delete');
        Route::patch('/{id}/status', [FacultyController::class, 'updateStatus'])->middleware('permission:faculty.edit');
    });

    Route::prefix('courses')->group(function () {
        Route::get('/stats',   [CourseController::class, 'stats'])->middleware('permission:courses.read');
        Route::get('/',        [CourseController::class, 'index']);
        Route::post('/',       [CourseController::class, 'store'])->middleware('permission:courses.create');
        Route::get('/{id}',    [CourseController::class, 'show']);
        Route::put('/{id}',    [CourseController::class, 'update'])->middleware('permission:courses.edit');
        Route::delete('/{id}', [CourseController::class, 'destroy'])->middleware('permission:courses.delete');
    });

    Route::prefix('enrollments')->group(function () {
        Route::get('/stats',         [EnrollmentController::class, 'stats'])->middleware('permission:enrollments.read');
        Route::post('/bulk-approve', [EnrollmentController::class, 'bulkApprove'])->middleware('permission:enrollments.approve');
        Route::get('/',              [EnrollmentController::class, 'index'])->middleware('permission:enrollments.read');
        Route::post('/',             [EnrollmentController::class, 'store'])->middleware('permission:enrollments.create');
        Route::patch('/{id}/approve', [EnrollmentController::class, 'approve'])->middleware('permission:enrollments.approve');
        Route::patch('/{id}/reject', [EnrollmentController::class, 'reject'])->middleware('permission:enrollments.reject');
        Route::patch('/{id}/drop',   [EnrollmentController::class, 'drop'])->middleware('permission:enrollments.edit');
    });

    Route::prefix('attendance')->group(function () {
        Route::get('/stats',    [AttendanceController::class, 'stats'])->middleware('permission:attendance.read');
        Route::get('/sessions', [AttendanceController::class, 'sessions'])->middleware('permission:attendance.read');
        Route::get('/students', [AttendanceController::class, 'getStudentsForCourse'])->middleware('permission:attendance.read');
        Route::post('/mark',    [AttendanceController::class, 'mark'])->middleware('permission:attendance.create|attendance.edit');
        Route::get('/report',   [AttendanceController::class, 'report'])->middleware('permission:attendance.read');
        Route::get('/calendar', [AttendanceController::class, 'calendar'])->middleware('permission:attendance.read');
    });

    Route::prefix('exams')->group(function () {
        Route::get('/stats',          [ExamController::class, 'stats'])->middleware('permission:exams.read');
        Route::get('/',               [ExamController::class, 'index'])->middleware('permission:exams.read');
        Route::post('/',              [ExamController::class, 'store'])->middleware('permission:exams.create|exams.schedule');
        Route::put('/{id}',           [ExamController::class, 'update'])->middleware('permission:exams.edit|exams.schedule');
        Route::delete('/{id}',        [ExamController::class, 'destroy'])->middleware('permission:exams.delete');
        Route::get('/{id}/students',  [ExamController::class, 'students'])->middleware('permission:exams.read');
        Route::post('/{id}/results',  [ExamController::class, 'saveResults'])->middleware('permission:grades.create|grades.edit');
        Route::patch('/{id}/publish', [ExamController::class, 'publishResults'])->middleware('permission:grades.publish');
    });

    Route::prefix('grades')->group(function () {
        Route::get('/stats',        [GradeController::class, 'stats'])->middleware('permission:grades.read');
        Route::get('/course',       [GradeController::class, 'forCourse'])->middleware('permission:grades.read');
        Route::post('/save',        [GradeController::class, 'save'])->middleware('permission:grades.create|grades.edit');
        Route::post('/publish',     [GradeController::class, 'publish'])->middleware('permission:grades.publish');
        Route::get('/student/{id}', [GradeController::class, 'studentGrades'])->middleware('permission:grades.read|grades.own');
        Route::get('/',             [GradeController::class, 'index'])->middleware('permission:grades.read');
    });

    Route::prefix('fees')->group(function () {
        Route::get('/stats',                   [FeeController::class, 'stats'])->middleware('permission:fees.read');
        Route::get('/defaulters',              [FeeController::class, 'defaulters'])->middleware('permission:fees.read');
        Route::get('/student/{id}',            [FeeController::class, 'studentInvoices'])->middleware('permission:fees.read|fees.own');
        Route::get('/structures',              [FeeController::class, 'structures'])->middleware('permission:fees.read');
        Route::post('/structures',             [FeeController::class, 'storeStructure'])->middleware('permission:fees.create');
        Route::put('/structures/{id}',         [FeeController::class, 'updateStructure'])->middleware('permission:fees.edit');
        Route::delete('/structures/{id}',      [FeeController::class, 'destroyStructure'])->middleware('permission:fees.delete');
        Route::get('/invoices',                [FeeController::class, 'invoices'])->middleware('permission:fees.read');
        Route::post('/invoices',               [FeeController::class, 'storeInvoice'])->middleware('permission:fees.create');
        Route::post('/invoices/bulk-generate', [FeeController::class, 'bulkGenerate'])->middleware('permission:fees.create');
        Route::post('/invoices/{id}/pay',      [FeeController::class, 'collectPayment'])->middleware('permission:fees.collect');
    });

    Route::prefix('admissions')->group(function () {
        Route::get('/stats',            [AdmissionController::class, 'stats'])->middleware('permission:admissions.read');
        Route::get('/',                 [AdmissionController::class, 'index'])->middleware('permission:admissions.read');
        Route::post('/',                [AdmissionController::class, 'store'])->middleware('permission:admissions.create');
        Route::get('/{id}',             [AdmissionController::class, 'show'])->middleware('permission:admissions.read');
        Route::put('/{id}',             [AdmissionController::class, 'update'])->middleware('permission:admissions.edit');
        Route::delete('/{id}',          [AdmissionController::class, 'destroy'])->middleware('permission:admissions.delete');
        Route::patch('/{id}/review',    [AdmissionController::class, 'review'])->middleware('permission:admissions.edit');
        Route::patch('/{id}/shortlist', [AdmissionController::class, 'shortlist'])->middleware('permission:admissions.edit');
        Route::patch('/{id}/accept',    [AdmissionController::class, 'accept'])->middleware('permission:admissions.approve');
        Route::patch('/{id}/reject',    [AdmissionController::class, 'reject'])->middleware('permission:admissions.reject');
        Route::post('/{id}/enroll',     [AdmissionController::class, 'enroll'])->middleware('permission:admissions.approve');
    });

    Route::prefix('library')->group(function () {
        Route::get('/stats',                [LibraryController::class, 'stats'])->middleware('permission:library.read');
        // Books
        Route::get('/books',                [LibraryController::class, 'books'])->middleware('permission:library.read');
        Route::post('/books',               [LibraryController::class, 'storeBook'])->middleware('permission:library.create');
        Route::put('/books/{id}',           [LibraryController::class, 'updateBook'])->middleware('permission:library.edit');
        Route::delete('/books/{id}',        [LibraryController::class, 'destroyBook'])->middleware('permission:library.delete');
        // Members
        Route::get('/members/search',       [LibraryController::class, 'searchMembers'])->middleware('permission:library.issue');
        Route::get('/members',              [LibraryController::class, 'members'])->middleware('permission:library.issue');
        Route::post('/members',             [LibraryController::class, 'storeMember'])->middleware('permission:library.create');
        Route::get('/users/search',         [LibraryController::class, 'searchUsers'])->middleware('permission:library.create');
        // Issues
        Route::get('/issues',               [LibraryController::class, 'issues'])->middleware('permission:library.issue');
        Route::post('/issues',              [LibraryController::class, 'issueBook'])->middleware('permission:library.issue');
        Route::patch('/issues/{id}/return', [LibraryController::class, 'returnBook'])->middleware('permission:library.return');
    });

    Route::prefix('timetable')->group(function () {
        Route::get('/stats',   [TimetableController::class, 'stats'])->middleware('permission:timetable.read');
        Route::get('/rooms',   [TimetableController::class, 'rooms'])->middleware('permission:timetable.read');
        Route::get('/list',    [TimetableController::class, 'list'])->middleware('permission:timetable.read');
        Route::get('/',        [TimetableController::class, 'index'])->middleware('permission:timetable.read');
        Route::post('/',       [TimetableController::class, 'store'])->middleware('permission:timetable.create');
        Route::put('/{id}',    [TimetableController::class, 'update'])->middleware('permission:timetable.edit');
        Route::delete('/{id}', [TimetableController::class, 'destroy'])->middleware('permission:timetable.delete');
    });

    // Schools and coaching centres: class levels, sections/batches, subjects
    Route::get('/class-levels', [StudentGroupController::class, 'levels'])->middleware('permission:classes.read');
    Route::prefix('student-groups')->group(function () {
        Route::get('/',                         [StudentGroupController::class, 'index'])->middleware('permission:classes.read');
        Route::post('/',                        [StudentGroupController::class, 'store'])->middleware('permission:classes.manage');
        Route::get('/{id}',                     [StudentGroupController::class, 'show'])->middleware('permission:classes.read');
        Route::put('/{id}',                     [StudentGroupController::class, 'update'])->middleware('permission:classes.manage');
        Route::delete('/{id}',                  [StudentGroupController::class, 'destroy'])->middleware('permission:classes.manage');
        Route::post('/{id}/members',            [StudentGroupController::class, 'addMembers'])->middleware('permission:classes.manage');
        Route::patch('/{id}/members/{memberId}', [StudentGroupController::class, 'updateMember'])->middleware('permission:classes.manage');
        Route::delete('/{id}/members/{memberId}', [StudentGroupController::class, 'removeMember'])->middleware('permission:classes.manage');
        Route::put('/{id}/subjects',            [StudentGroupController::class, 'syncSubjects'])->middleware('permission:classes.manage');
    });
    Route::prefix('subjects')->group(function () {
        Route::get('/',        [SubjectController::class, 'index'])->middleware('permission:classes.read');
        Route::post('/',       [SubjectController::class, 'store'])->middleware('permission:classes.manage');
        Route::put('/{id}',    [SubjectController::class, 'update'])->middleware('permission:classes.manage');
        Route::delete('/{id}', [SubjectController::class, 'destroy'])->middleware('permission:classes.manage');
    });

    // Personal emails to one person (user, guardian or any address)
    Route::prefix('communications')->group(function () {
        Route::get('/recipients', [CommunicationController::class, 'recipients'])->middleware('permission:communications.send');
        Route::get('/email',      [CommunicationController::class, 'index'])->middleware('permission:communications.send|communications.read');
        Route::post('/email',     [CommunicationController::class, 'send'])->middleware(['permission:communications.send', 'throttle:10,1']);
    });

    Route::prefix('notifications')->group(function () {
        Route::get('/unread-count',    [NotificationController::class, 'unreadCount']);
        Route::get('/recent',          [NotificationController::class, 'recent']);
        Route::get('/stats',           [NotificationController::class, 'stats']);
        Route::post('/send',           [NotificationController::class, 'send'])->middleware('permission:notifications.create');
        Route::patch('/mark-all-read', [NotificationController::class, 'markAllRead']);
        Route::delete('/clear-all',    [NotificationController::class, 'clearAll']);
        Route::get('/',                [NotificationController::class, 'index']);
        Route::patch('/{id}/read',     [NotificationController::class, 'markRead']);
        Route::delete('/{id}',         [NotificationController::class, 'destroy']);
    });

    Route::prefix('roles')->group(function () {
        Route::get('/',                   [RoleController::class, 'index'])->middleware('permission:roles.read');
        Route::post('/',                  [RoleController::class, 'store'])->middleware('permission:roles.create');
        Route::put('/{id}',               [RoleController::class, 'update'])->middleware('permission:roles.edit');
        Route::delete('/{id}',            [RoleController::class, 'destroy'])->middleware('permission:roles.delete');
        Route::get('/{id}/permissions',   [RoleController::class, 'permissions'])->middleware('permission:roles.read');
        Route::patch('/{id}/permissions', [RoleController::class, 'syncPermissions'])->middleware('permission:roles.edit');
        Route::post('/{id}/clone',        [RoleController::class, 'clone'])->middleware('permission:roles.create');
        Route::get('/{id}/history',       [RoleController::class, 'history'])->middleware('permission:roles.read');
    });

    Route::prefix('permissions')->group(function () {
        Route::get('/',        [PermissionController::class, 'index'])->middleware('permission:permissions.read');
        Route::post('/',       [PermissionController::class, 'store'])->middleware('permission:permissions.create');
        Route::put('/{id}',    [PermissionController::class, 'update'])->middleware('permission:permissions.edit');
        Route::delete('/{id}', [PermissionController::class, 'destroy'])->middleware('permission:permissions.delete');
        Route::post('/bulk',   [PermissionController::class, 'bulkCreate'])->middleware('permission:permissions.create');
    });

    Route::prefix('activity-logs')->group(function () {
        Route::get('/',          [ActivityLogController::class, 'index'])->middleware('permission:activity-logs.read');
        Route::get('/stats',     [ActivityLogController::class, 'stats'])->middleware('permission:activity-logs.read');
        Route::get('/modules',   [ActivityLogController::class, 'modules'])->middleware('permission:activity-logs.read');
        Route::get('/user/{id}', [ActivityLogController::class, 'userLogs'])->middleware('permission:activity-logs.read');
    });

    Route::prefix('reports')->group(function () {
        Route::get('/overview',  [ReportController::class, 'overview'])->middleware('permission:reports.read');
        Route::get('/students',  [ReportController::class, 'students'])->middleware('permission:reports.read');
        Route::get('/fees',      [ReportController::class, 'fees'])->middleware('permission:reports.read');
        Route::get('/attendance', [ReportController::class, 'attendance'])->middleware('permission:reports.read');
        Route::get('/exams',     [ReportController::class, 'exams'])->middleware('permission:reports.read');
        Route::get('/admissions', [ReportController::class, 'admissions'])->middleware('permission:reports.read');
        Route::get('/pdf/{type}', [ReportController::class, 'exportPdf'])->middleware('permission:reports.export');
    });

    Route::prefix('settings')->group(function () {
        Route::get('/',          [SettingsController::class, 'index'])->middleware('permission:settings.read');
        Route::get('/{group}',   [SettingsController::class, 'group'])->middleware('permission:settings.read');
        Route::put('/{group}',   [SettingsController::class, 'update'])->middleware('permission:settings.edit');
    });
    Route::get('/public-settings', [SettingsController::class, 'publicSettings']);
});
