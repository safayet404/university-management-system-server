<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\FacultyController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\EnrollmentController;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('/logout',         [AuthController::class, 'logout']);
        Route::get('/me',              [AuthController::class, 'me']);
        Route::put('/profile',         [AuthController::class, 'updateProfile']);
        Route::put('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/avatar',         [AuthController::class, 'uploadAvatar']);
    });

    // Users
    Route::prefix('users')->group(function () {
        Route::get('/stats',                [UserController::class, 'stats']);
        Route::get('/export',               [UserController::class, 'export']);
        Route::post('/bulk-action',         [UserController::class, 'bulkAction']);
        Route::get('/',                     [UserController::class, 'index']);
        Route::post('/',                    [UserController::class, 'store']);
        Route::get('/{id}',                 [UserController::class, 'show']);
        Route::put('/{id}',                 [UserController::class, 'update']);
        Route::delete('/{id}',              [UserController::class, 'destroy']);
        Route::post('/{id}/restore',        [UserController::class, 'restore']);
        Route::patch('/{id}/status',        [UserController::class, 'updateStatus']);
        Route::patch('/{id}/role',          [UserController::class, 'updateRole']);
        Route::post('/{id}/avatar',         [UserController::class, 'uploadAvatar']);
        Route::post('/{id}/reset-password', [UserController::class, 'resetPassword']);
        Route::get('/{id}/activity',        [UserController::class, 'activity']);
    });

    // Departments
    Route::prefix('departments')->group(function () {
        Route::get('/',        [DepartmentController::class, 'index']);
        Route::post('/',       [DepartmentController::class, 'store']);
        Route::get('/{id}',    [DepartmentController::class, 'show']);
        Route::put('/{id}',    [DepartmentController::class, 'update']);
        Route::delete('/{id}', [DepartmentController::class, 'destroy']);
    });

    // Programs
    Route::prefix('programs')->group(function () {
        Route::get('/',        [ProgramController::class, 'index']);
        Route::post('/',       [ProgramController::class, 'store']);
        Route::put('/{id}',    [ProgramController::class, 'update']);
        Route::delete('/{id}', [ProgramController::class, 'destroy']);
    });

    // Students
    Route::prefix('students')->group(function () {
        Route::get('/stats',         [StudentController::class, 'stats']);
        Route::get('/export',        [StudentController::class, 'export']);
        Route::get('/',              [StudentController::class, 'index']);
        Route::post('/',             [StudentController::class, 'store']);
        Route::get('/{id}',          [StudentController::class, 'show']);
        Route::put('/{id}',          [StudentController::class, 'update']);
        Route::delete('/{id}',       [StudentController::class, 'destroy']);
        Route::patch('/{id}/status', [StudentController::class, 'updateStatus']);
    });

    // Faculty
    Route::prefix('faculty')->group(function () {
        Route::get('/stats',         [FacultyController::class, 'stats']);
        Route::get('/export',        [FacultyController::class, 'export']);
        Route::get('/',              [FacultyController::class, 'index']);
        Route::post('/',             [FacultyController::class, 'store']);
        Route::get('/{id}',          [FacultyController::class, 'show']);
        Route::put('/{id}',          [FacultyController::class, 'update']);
        Route::delete('/{id}',       [FacultyController::class, 'destroy']);
        Route::patch('/{id}/status', [FacultyController::class, 'updateStatus']);
    });

    // Courses
    Route::prefix('courses')->group(function () {
        Route::get('/stats',   [CourseController::class, 'stats']);
        Route::get('/',        [CourseController::class, 'index']);
        Route::post('/',       [CourseController::class, 'store']);
        Route::get('/{id}',    [CourseController::class, 'show']);
        Route::put('/{id}',    [CourseController::class, 'update']);
        Route::delete('/{id}', [CourseController::class, 'destroy']);
    });

    // Enrollments
    Route::prefix('enrollments')->group(function () {
        Route::get('/stats',               [EnrollmentController::class, 'stats']);
        Route::post('/bulk-approve',       [EnrollmentController::class, 'bulkApprove']);
        Route::get('/',                    [EnrollmentController::class, 'index']);
        Route::post('/',                   [EnrollmentController::class, 'store']);
        Route::patch('/{id}/approve',      [EnrollmentController::class, 'approve']);
        Route::patch('/{id}/reject',       [EnrollmentController::class, 'reject']);
        Route::patch('/{id}/drop',         [EnrollmentController::class, 'drop']);
    });

    // Roles
    Route::middleware('role:super-admin|admin')->prefix('roles')->group(function () {
        Route::get('/',                   [RoleController::class, 'index']);
        Route::post('/',                  [RoleController::class, 'store']);
        Route::put('/{id}',               [RoleController::class, 'update']);
        Route::delete('/{id}',            [RoleController::class, 'destroy']);
        Route::get('/{id}/permissions',   [RoleController::class, 'permissions']);
        Route::patch('/{id}/permissions', [RoleController::class, 'syncPermissions']);
        Route::post('/{id}/clone',        [RoleController::class, 'clone']);
        Route::get('/{id}/history',       [RoleController::class, 'history']);
    });

    // Permissions
    Route::middleware('role:super-admin|admin')->prefix('permissions')->group(function () {
        Route::get('/',        [PermissionController::class, 'index']);
        Route::post('/',       [PermissionController::class, 'store']);
        Route::put('/{id}',    [PermissionController::class, 'update']);
        Route::delete('/{id}', [PermissionController::class, 'destroy']);
        Route::post('/bulk',   [PermissionController::class, 'bulkCreate']);
    });

    // Activity Logs
    Route::middleware('role:super-admin|admin')->prefix('activity-logs')->group(function () {
        Route::get('/',          [ActivityLogController::class, 'index']);
        Route::get('/stats',     [ActivityLogController::class, 'stats']);
        Route::get('/modules',   [ActivityLogController::class, 'modules']);
        Route::get('/user/{id}', [ActivityLogController::class, 'userLogs']);
    });
});
