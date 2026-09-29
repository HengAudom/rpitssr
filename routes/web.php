<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\ResultController;
use Illuminate\Support\Facades\Route;

// ─── Public Endpoints (Essential for Guest Sign In & Registration) ────────────
Route::get('/api/public-settings', [AdminController::class, 'publicSettings']);

Route::middleware(['throttle:30,1'])->group(function () {
    Route::post('/api/check-identifier', [AuthController::class, 'checkIdentifier']);
});

Route::middleware(['throttle:login'])->group(function () {
    Route::post('/api/login', [AuthController::class, 'login']);
});

Route::middleware(['throttle:15,1'])->group(function () {
    Route::post('/api/register', [AuthController::class, 'register']);
});

Route::middleware(['throttle:password-reset'])->group(function () {
    Route::post('/api/password/verify-identity', [AuthController::class, 'verifyIdentity']);
    Route::post('/api/password/forgot', [AuthController::class, 'forgotPassword']);
    Route::post('/api/password/reset', [AuthController::class, 'resetPassword']);
});

Route::post('/api/logout', [AuthController::class, 'logout']);

// ─── Authenticated User Routes (Student & Admin) ─────────────────────────────
Route::middleware(['auth'])->group(function () {
    Route::get('/api/profile', [AuthController::class, 'profile']);
    Route::post('/api/profile/update', [AuthController::class, 'updateProfile']);
    Route::post('/api/profile/upload-image', [AuthController::class, 'uploadProfileImage']);
    Route::post('/api/profile/change-password', [AuthController::class, 'changePassword']);

    // ─── Exam (Student) ──────────────────────────────────────────────────────
    Route::get('/api/exam/{testId}/start', [ExamController::class, 'start']);
    Route::get('/api/exam/{submissionId}/status', [ExamController::class, 'checkStatus']);
    Route::post('/api/exam/answer', [ExamController::class, 'saveAnswer']);
    Route::post('/api/exam/interruption', [ExamController::class, 'recordInterruption']);
    Route::post('/api/exam/{submissionId}/complete', [ExamController::class, 'complete']);

    // ─── Results (Student) ───────────────────────────────────────────────────
    Route::get('/api/student/results', [ResultController::class, 'studentResults']);
    Route::get('/api/student/results/{id}', [ResultController::class, 'submissionDetail']);
});

// ─── Admin Dedicated Routes (Protected by Auth + Admin Role) ──────────────────
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/api/admin/dashboard', [AdminController::class, 'dashboard']);
    Route::get('/api/admin/students', [AdminController::class, 'students']);
    Route::post('/api/admin/students', [AdminController::class, 'addStudent']);
    Route::post('/api/admin/students/import', [AdminController::class, 'importStudents']);
    Route::put('/api/admin/students/{id}', [AdminController::class, 'updateStudent']);
    Route::delete('/api/admin/students/{id}', [AdminController::class, 'deleteStudent']);

    Route::get('/api/admin/skills-groups', [AdminController::class, 'skillsGroups']);
    Route::post('/api/admin/skills-groups', [AdminController::class, 'saveSkillsGroups']);
    Route::get('/api/admin/exam-sessions', [AdminController::class, 'examSessions']);
    Route::post('/api/admin/exam-sessions', [AdminController::class, 'addExamSession']);
    Route::put('/api/admin/exam-sessions/{id}', [AdminController::class, 'updateExamSession']);
    Route::delete('/api/admin/exam-sessions/{id}', [AdminController::class, 'deleteExamSession']);

    Route::get('/api/admin/tests', [TestController::class, 'index']);
    Route::post('/api/admin/tests', [TestController::class, 'store']);
    Route::post('/api/admin/tests/parse-doc', [TestController::class, 'parseDoc']);
    Route::get('/api/admin/tests/{id}', [TestController::class, 'show']);
    Route::get('/api/admin/tests/{id}/export-word', [TestController::class, 'exportWord']);
    Route::get('/api/admin/tests/{id}/export-txt', [TestController::class, 'exportTxt']);
    Route::put('/api/admin/tests/{id}', [TestController::class, 'update']);
    Route::delete('/api/admin/tests/{id}', [TestController::class, 'destroy']);

    Route::get('/api/admin/results', [AdminController::class, 'results']);
    Route::get('/api/admin/results/{id}', [ResultController::class, 'submissionDetail']);
    Route::delete('/api/admin/results/{id}', [AdminController::class, 'deleteSubmission']);

    Route::get('/api/admin/live-monitor', [AdminController::class, 'liveMonitor']);
    Route::post('/api/admin/live-monitor/{id}/force-submit', [AdminController::class, 'forceSubmit']);

    Route::get('/api/admin/schedule-days-years', [AdminController::class, 'getScheduleDaysYears']);
    Route::post('/api/admin/schedule-days-years', [AdminController::class, 'saveScheduleDaysYears']);

    Route::get('/api/admin/audit-logs', [AdminController::class, 'auditLogs']);
    Route::delete('/api/admin/audit-logs', [AdminController::class, 'clearAuditLogs']);
    Route::get('/api/admin/system-settings', [AdminController::class, 'systemSettings']);
    Route::post('/api/admin/system-settings', [AdminController::class, 'saveSystemSettings']);
});

// ─── Super Admin Dedicated Endpoints ──────────────────────────────────────────
Route::middleware(['auth', 'super_admin'])->group(function () {
    Route::get('/api/admin/roles-permissions', [AdminController::class, 'rolesPermissions']);
    Route::post('/api/admin/roles-permissions', [AdminController::class, 'saveRolesPermissions']);
});

// ─── SPA Catch-all ────────────────────────────────────────────────────────────
Route::view('/{any}', 'welcome')->where('any', '.*');
