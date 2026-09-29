<?php

namespace App\Http\Controllers;

use App\Models\ExamSession;
use App\Models\Student;
use App\Models\Admin;
use App\Models\Test;
use App\Models\StudentSubmission;
use App\Models\SubmissionDetail;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class AdminController extends Controller
{
    /**
     * Check permission against storage/app/permissions.json for Admin role.
     * Super Admin always has full unrestricted access.
     */
    public static function checkAdminPermission($user, string $module, string $action = 'view'): bool
    {
        if (!$user) return false;
        if (in_array($user->role ?? $user->Role, ['Super Admin', 'SuperAdmin'])) {
            return true;
        }
        if (($user->role ?? $user->Role) !== 'Admin') {
            return false;
        }

        $defaultMatrix = [
            ['module' => 'Students', 'view' => true, 'create' => true, 'edit' => true, 'delete' => true, 'export' => true],
            ['module' => 'Exams', 'view' => true, 'create' => true, 'edit' => true, 'delete' => true, 'export' => true],
            ['module' => 'Question Bank', 'view' => true, 'create' => true, 'edit' => true, 'delete' => true, 'export' => true],
            ['module' => 'Skills & Groups', 'view' => true, 'create' => true, 'edit' => true, 'delete' => true, 'export' => false],
            ['module' => 'Results', 'view' => true, 'create' => false, 'edit' => false, 'delete' => true, 'export' => true],
            ['module' => 'Analytics', 'view' => true, 'create' => false, 'edit' => false, 'delete' => false, 'export' => false],
            ['module' => 'Audit Logs', 'view' => true, 'create' => false, 'edit' => false, 'delete' => false, 'export' => false],
            ['module' => 'System Settings', 'view' => true, 'create' => false, 'edit' => true, 'delete' => false, 'export' => false],
        ];

        $permsFile = storage_path('app/permissions.json');
        $matrix = $defaultMatrix;
        if (file_exists($permsFile)) {
            $saved = json_decode(file_get_contents($permsFile), true) ?: [];
            if (!empty($saved) && is_array($saved)) {
                $matrix = $saved;
            }
        }

        $moduleAliases = [
            'exam sessions' => 'skills & groups',
            'examsessions' => 'skills & groups',
            'skills and groups' => 'skills & groups',
            'tests' => 'exams',
        ];

        $targetModule = strtolower(trim($module));
        if (isset($moduleAliases[$targetModule])) {
            $targetModule = $moduleAliases[$targetModule];
        }

        foreach ($matrix as $item) {
            $mName = strtolower(trim($item['module'] ?? ''));
            if ($mName === $targetModule || (isset($moduleAliases[$mName]) && $moduleAliases[$mName] === $targetModule)) {
                if (isset($item[$action])) {
                    return (bool) $item[$action];
                }
            }
        }
        return true;
    }

    public function dashboard(Request $request)
    {
        $studentCount = Student::count();
        $adminCount = Admin::whereIn('Role', ['Admin', 'Super Admin', 'SuperAdmin'])->count();
        $totalUsers = $studentCount + $adminCount;
        $testCount = DB::table('tbltest')->count();
        $completedCount = DB::table('tblstudentsubmission')->whereNotNull('CompletedAt')->count();
        $avgScore = DB::table('tblstudentsubmission')
            ->whereNotNull('CompletedAt')
            ->avg('Score') ?? 0;

        $submissions = DB::table('tblstudentsubmission as ss')
            ->leftJoin('tblstudent as s', 'ss.StudentId', '=', 's.StudentId')
            ->leftJoin('tbltest as t', 'ss.TestId', '=', 't.TestId')
            ->orderBy('ss.SubmissionId', 'desc')
            ->limit(20)
            ->select('s.FirstName', 's.LastName', 't.TestName', 'ss.Score', 'ss.CompletedAt as Date', 'ss.SubmissionId')
            ->get()
            ->map(function($r) {
                $studentName = trim(($r->FirstName ?? '') . ' ' . ($r->LastName ?? ''));
                if (!$studentName) $studentName = 'Student #' . $r->SubmissionId;
                $testName = $r->TestName ?? 'Exam';
                $time = !empty($r->Date) ? \Carbon\Carbon::parse($r->Date)->timestamp : (1700000000 + ($r->SubmissionId * 10));
                $dateFormatted = !empty($r->Date) ? date('M d, Y', strtotime($r->Date)) : 'Recent';
                return [
                    'id' => 'sub_' . $r->SubmissionId,
                    'type' => 'exam_completion',
                    'module' => 'Exams',
                    'action' => 'Exam Completed',
                    'user' => $studentName,
                    'title' => "{$studentName} completed {$testName}",
                    'description' => "Score: {$r->Score} · {$dateFormatted}",
                    'timestamp' => $time,
                    'status' => 'Success'
                ];
            });

        $newTests = DB::table('tbltest')
            ->orderBy('TestId', 'desc')
            ->limit(10)
            ->select('TestName', 'created_at as Date', 'TestId')
            ->get()
            ->map(function($r) {
                $time = !empty($r->Date) ? \Carbon\Carbon::parse($r->Date)->timestamp : (1700000000 + ($r->TestId * 50));
                $dateFormatted = !empty($r->Date) ? date('M d, Y', strtotime($r->Date)) : 'Active';
                return [
                    'id' => 'test_' . $r->TestId,
                    'type' => 'new_test',
                    'module' => 'Exams',
                    'action' => 'Test Published',
                    'user' => 'Admin',
                    'title' => "New exam published: {$r->TestName}",
                    'description' => "Test ID #{$r->TestId} · {$dateFormatted}",
                    'timestamp' => $time,
                    'status' => 'Success'
                ];
            });

        $newStudents = DB::table('tblstudent as s')
            ->leftJoin('tblexamsession as es', 's.SessionId', '=', 'es.SessionId')
            ->orderBy('s.StudentId', 'desc')
            ->limit(10)
            ->select('s.FirstName', 's.LastName', 'es.SessionName', 's.created_at as Date', 's.StudentId')
            ->get()
            ->map(function($r) {
                $studentName = trim(($r->FirstName ?? '') . ' ' . ($r->LastName ?? ''));
                if (!$studentName) $studentName = 'Candidate #' . $r->StudentId;
                $session = $r->SessionName ?? 'General Session';
                $time = !empty($r->Date) ? \Carbon\Carbon::parse($r->Date)->timestamp : (1700000000 + ($r->StudentId * 20));
                return [
                    'id' => 'stu_' . $r->StudentId,
                    'type' => 'new_student',
                    'module' => 'Students',
                    'action' => 'Candidate Registered',
                    'user' => $studentName,
                    'title' => "New candidate registered: {$studentName}",
                    'description' => "Shift: {$session}",
                    'timestamp' => $time,
                    'status' => 'Success'
                ];
            });

        $adminTable = Schema::hasTable('tbladmin') ? 'tbladmin' : 'tbladminprofile';
        $adminIdCol = Schema::hasColumn($adminTable, 'AdminId') ? 'AdminId' : 'AdminProfileId';
        $newAdmins = DB::table($adminTable)
            ->orderBy($adminIdCol, 'desc')
            ->limit(10)
            ->get()
            ->map(function($r) use ($adminIdCol) {
                $time = !empty($r->created_at) ? \Carbon\Carbon::parse($r->created_at)->timestamp : time();
                $id = $r->{$adminIdCol};
                $name = trim("{$r->FirstName} {$r->LastName}") ?: $r->Username;
                return [
                    'id' => 'adm_' . $id,
                    'type' => 'new_admin',
                    'module' => 'Admins',
                    'action' => 'Admin Account Added',
                    'user' => $name,
                    'title' => "New Admin added: {$name}",
                    'description' => "Username: @{$r->Username}",
                    'timestamp' => $time,
                    'status' => 'Success'
                ];
            });

        $newSessions = DB::table('tblexamsession')
            ->orderBy('SessionId', 'desc')
            ->limit(10)
            ->get()
            ->map(function($r) {
                $time = !empty($r->created_at) ? \Carbon\Carbon::parse($r->created_at)->timestamp : (1700000000 + ($r->SessionId * 5));
                return [
                    'id' => 'sess_' . $r->SessionId,
                    'type' => 'new_session',
                    'module' => 'Exam Shifts',
                    'action' => 'Exam Shift Created',
                    'user' => 'Admin',
                    'title' => "New exam shift added: {$r->SessionName}",
                    'description' => "Date: {$r->ExamDate} ({$r->StartTime} - {$r->EndTime})",
                    'timestamp' => $time,
                    'status' => 'Success'
                ];
            });

        $latestActivity = collect()
            ->concat($submissions)
            ->concat($newTests)
            ->concat($newStudents)
            ->concat($newAdmins)
            ->concat($newSessions)
            ->sort(function ($a, $b) {
                if ($a['timestamp'] === $b['timestamp']) {
                    return strcmp($b['id'], $a['id']);
                }
                return $b['timestamp'] <=> $a['timestamp'];
            })
            ->take(30)
            ->values();

        $payload = [
            'totalUsers' => $totalUsers,
            'totalAdmins' => $adminCount,
            'activeStudents' => $studentCount,
            'publishedTests' => $testCount,
            'completedExams' => $completedCount,
            'avgScore' => round($avgScore, 1),
            'latestActivity' => $latestActivity,
        ];

        return response()->json($payload);
    }

    public function auditLogs(Request $request)
    {
        if (!self::checkAdminPermission($request->user(), 'Audit Logs', 'view')) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to view audit logs.'], 403);
        }

        $clearedAt = null;
        $metaFile = storage_path('app/audit_logs_meta.json');
        if (file_exists($metaFile)) {
            $meta = json_decode(file_get_contents($metaFile), true);
            $clearedAt = $meta['cleared_at'] ?? null;
        }

        $loginLogs = collect();

        try {
            $jsonFile = storage_path('app/login_logs.json');
            if (file_exists($jsonFile)) {
                $fileLogs = json_decode(file_get_contents($jsonFile), true) ?: [];
                $loginLogs = collect($fileLogs)->map(fn($l) => [
                    'id' => $l['id'] ?? ('auth-json-' . uniqid()),
                    'date' => $l['date'] ?? now()->toDateTimeString(),
                    'user' => $l['displayName'] ?: ($l['username'] ?? 'Unknown'),
                    'role' => $l['role'] ?? 'User',
                    'action' => ($l['status'] ?? '') === 'Failed' ? 'Failed Login Attempt' : (($l['status'] ?? '') === 'Logged Out' ? 'User Logout' : 'User Login'),
                    'module' => 'Authentication',
                    'target' => '@' . ($l['username'] ?? 'unknown'),
                    'status' => ($l['status'] ?? '') === 'Failed' ? 'Failed' : (($l['status'] ?? '') === 'Logged Out' ? 'Logged Out' : 'Success'),
                    'details' => $l['details'] ?? "IP: " . ($l['ipAddress'] ?? '127.0.0.1')
                ]);
            }
        } catch (\Throwable $e) {
            \Log::warning('Error reading login_logs.json: ' . $e->getMessage());
        }

        if ($clearedAt) {
            $loginLogs = $loginLogs->filter(fn($l) => isset($l['date']) && $l['date'] > $clearedAt);
        }

        $uniqueLoginLogs = $loginLogs->take(80);

        $subQuery = DB::table('tblstudentsubmission as ss')
            ->join('tblstudent as s', 'ss.StudentId', '=', 's.StudentId')
            ->join('tbltest as t', 'ss.TestId', '=', 't.TestId')
            ->whereNotNull('ss.CompletedAt');

        if ($clearedAt) {
            $subQuery->where('ss.CompletedAt', '>', $clearedAt);
        }

        $submissions = $subQuery
            ->orderBy('ss.CompletedAt', 'desc')
            ->limit(50)
            ->select('ss.SubmissionId', 's.FirstName', 's.LastName', 't.TestName', 'ss.Score', 'ss.CompletedAt as Date')
            ->get()
            ->map(fn($r) => [
                'id' => 'sub-' . $r->SubmissionId,
                'date' => $r->Date,
                'user' => "{$r->FirstName} {$r->LastName}",
                'role' => 'Student',
                'action' => 'Exam Submission',
                'module' => 'Exams',
                'target' => $r->TestName,
                'status' => 'Completed',
                'details' => "Score: {$r->Score} points"
            ]);

        $testQuery = DB::table('tbltest as t')
            ->leftJoin('tbladmin as a', 't.CreatedByUserId', '=', 'a.AdminId');

        if ($clearedAt) {
            $testQuery->where('t.created_at', '>', $clearedAt);
        }

        $tests = $testQuery
            ->orderBy('t.created_at', 'desc')
            ->limit(50)
            ->select('t.TestId', 't.TestName', 'a.FirstName', 'a.LastName', 'a.Username', 't.created_at as Date', 't.Status')
            ->get()
            ->map(fn($r) => [
                'id' => 'test-' . $r->TestId,
                'date' => $r->Date,
                'user' => trim("{$r->FirstName} {$r->LastName}") ?: ($r->Username ?? 'Admin'),
                'role' => 'Admin',
                'action' => 'Exam Published / Drafted',
                'module' => 'Exams',
                'target' => $r->TestName,
                'status' => $r->Status,
                'details' => "Exam status set to {$r->Status}"
            ]);

        $stuQuery = DB::table('tblstudent as s')
            ->leftJoin('tblexamsession as es', 's.SessionId', '=', 'es.SessionId');

        if ($clearedAt) {
            $stuQuery->where('s.created_at', '>', $clearedAt);
        }

        $students = $stuQuery
            ->orderBy('s.created_at', 'desc')
            ->limit(50)
            ->select('s.StudentId', 's.StudentCode', 's.FirstName', 's.LastName', 'es.SessionName', 's.created_at as Date')
            ->get()
            ->map(fn($r) => [
                'id' => 'stu-' . $r->StudentId,
                'date' => $r->Date,
                'user' => trim("{$r->FirstName} {$r->LastName}") ?: ($r->StudentCode ? "#{$r->StudentCode}" : ('Student #' . $r->StudentId)),
                'role' => 'Student',
                'action' => 'Candidate Registration',
                'module' => 'Students',
                'target' => $r->StudentCode ? "@{$r->StudentCode}" : ("#{$r->StudentId}"),
                'status' => 'Active',
                'details' => $r->SessionName ? "Assigned to {$r->SessionName}" : "Candidate Registration"
            ]);

        $adminTable = Schema::hasTable('tbladmin') ? 'tbladmin' : 'tbladminprofile';
        $adminIdCol = Schema::hasColumn($adminTable, 'AdminId') ? 'AdminId' : 'AdminProfileId';
        $admQuery = DB::table($adminTable);

        if ($clearedAt) {
            $admQuery->where('created_at', '>', $clearedAt);
        }

        $admins = $admQuery
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function($r) use ($adminIdCol) {
                $name = trim("{$r->FirstName} {$r->LastName}") ?: $r->Username;
                return [
                    'id' => 'adm-' . $r->{$adminIdCol},
                    'date' => $r->created_at,
                    'user' => $name,
                    'role' => $r->Role ?? 'Admin',
                    'action' => 'Admin Creation',
                    'module' => 'User Management',
                    'target' => "@{$r->Username}",
                    'status' => $r->Status ?? 'Active',
                    'details' => "Role: " . ($r->Role ?? 'Admin')
                ];
            });

        $logs = collect()
            ->concat($uniqueLoginLogs)
            ->concat($submissions)
            ->concat($tests)
            ->concat($students)
            ->concat($admins)
            ->sortByDesc('date')
            ->values();

        return response()->json(['logs' => $logs]);
    }

    public function clearAuditLogs(Request $request)
    {
        if (!self::checkAdminPermission($request->user(), 'Audit Logs', 'delete')) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to clear audit logs.'], 403);
        }

        try {
            $jsonFile = storage_path('app/login_logs.json');
            if (file_exists($jsonFile)) {
                file_put_contents($jsonFile, json_encode([]));
            }

            $activityFile = storage_path('app/activity_logs.json');
            if (file_exists($activityFile)) {
                file_put_contents($activityFile, json_encode([]));
            }

            $adminTable = Schema::hasTable('tbladmin') ? 'tbladmin' : 'tbladminprofile';
            // Find highest timestamp among current logs to guarantee everything existing is purged
            $maxSub = DB::table('tblstudentsubmission')->max('CompletedAt');
            $maxTest = DB::table('tbltest')->max('created_at');
            $maxStu = DB::table('tblstudent')->max('created_at');
            $maxAdm = DB::table($adminTable)->max('created_at');

            $dates = array_filter([$maxSub, $maxTest, $maxStu, $maxAdm, now()->toDateTimeString()]);
            rsort($dates);
            $highWaterMark = $dates[0] ?? now()->toDateTimeString();

            $metaFile = storage_path('app/audit_logs_meta.json');
            file_put_contents($metaFile, json_encode([
                'cleared_at' => $highWaterMark,
                'cleared_by' => auth()->user()?->name ?? 'Super Admin'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'All audit logs cleared successfully.'
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear audit logs: ' . $e->getMessage()
            ], 500);
        }
    }

    public function rolesPermissions(Request $request)
    {
        $defaultMatrix = [
            ['module' => 'Students', 'view' => true, 'create' => true, 'edit' => true, 'delete' => true, 'export' => true],
            ['module' => 'Exams', 'view' => true, 'create' => true, 'edit' => true, 'delete' => true, 'export' => true],
            ['module' => 'Question Bank', 'view' => true, 'create' => true, 'edit' => true, 'delete' => true, 'export' => true],
            ['module' => 'Skills & Groups', 'view' => true, 'create' => true, 'edit' => true, 'delete' => true, 'export' => false],
            ['module' => 'Results', 'view' => true, 'create' => false, 'edit' => false, 'delete' => true, 'export' => true],
            ['module' => 'Analytics', 'view' => true, 'create' => false, 'edit' => false, 'delete' => false, 'export' => false],
            ['module' => 'Audit Logs', 'view' => true, 'create' => false, 'edit' => false, 'delete' => false, 'export' => false],
            ['module' => 'System Settings', 'view' => true, 'create' => false, 'edit' => true, 'delete' => false, 'export' => false],
        ];

        $permsFile = storage_path('app/permissions.json');
        $matrix = $defaultMatrix;
        if (file_exists($permsFile)) {
            $saved = json_decode(file_get_contents($permsFile), true);
            if (is_array($saved) && !empty($saved)) {
                $matrix = $saved;
            }
        }

        // Capabilities map to ensure modules without export never report export: true
        $noExportModules = ['skills & groups', 'analytics', 'audit logs', 'system settings'];
        foreach ($matrix as &$row) {
            $m = strtolower(trim($row['module'] ?? ''));
            if (in_array($m, $noExportModules)) {
                $row['export'] = false;
            }
        }
        unset($row);

        return response()->json([
            'roles' => [
                ['name' => 'Super Admin', 'description' => 'Full administrative access to all system components, roles, and settings', 'usersCount' => Admin::whereIn('Role', ['Super Admin', 'SuperAdmin'])->count()],
                ['name' => 'Admin', 'description' => 'Academic and student operations, exam builder, and result evaluation', 'usersCount' => Admin::where('Role', 'Admin')->count()],
                ['name' => 'Student', 'description' => 'Examinee access to take exams and review individual scores', 'usersCount' => Student::count()]
            ],
            'permissions' => $matrix
        ]);
    }

    public function saveRolesPermissions(Request $request)
    {
        $permissions = $request->input('permissions', []);
        if (!empty($permissions) && is_array($permissions)) {
            $noExportModules = ['skills & groups', 'analytics', 'audit logs', 'system settings'];
            foreach ($permissions as &$row) {
                $m = strtolower(trim($row['module'] ?? ''));
                if (in_array($m, $noExportModules)) {
                    $row['export'] = false;
                }
            }
            unset($row);

            $dir = storage_path('app');
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            file_put_contents(storage_path('app/permissions.json'), json_encode($permissions, JSON_PRETTY_PRINT));
        }

        return response()->json(['message' => 'Roles & permissions matrix updated successfully!']);
    }

    public static function getSystemSettings(): array
    {
        $defaults = [
            'institutionName' => 'RPITSSR',
            'portalTitle' => 'RPITSSR',
            'portalSubtitle' => 'EXAM SYSTEM',
            'academicYear' => '2026-2027',
            'timezone' => 'Asia/Phnom_Penh',
            'defaultLanguage' => 'kh',
            'sessionTimeoutMinutes' => 60,
            'allowRegistration' => true,
            'forceStrongPassword' => true,
            'antiCheatPause' => true,
            'autosaveIntervalSeconds' => 3,
            'autoSubmitOnTimeout' => true,
            'maxExamAttempts' => 1,
            'phpVersion' => phpversion(),
            'laravelVersion' => app()->version(),
            'databaseDriver' => config('database.default'),
            'serverTime' => now()->toDateTimeString()
        ];

        $settingsFile = storage_path('app/settings.json');
        if (file_exists($settingsFile)) {
            $saved = json_decode(file_get_contents($settingsFile), true);
            if (is_array($saved)) {
                $defaults = array_merge($defaults, $saved);
            }
        }

        if (!empty($defaults['institutionName'])) {
            $defaults['portalTitle'] = $defaults['portalTitle'] ?? $defaults['institutionName'];
        }

        $defaults['allowRegistration'] = filter_var($defaults['allowRegistration'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $defaults['forceStrongPassword'] = filter_var($defaults['forceStrongPassword'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $defaults['antiCheatPause'] = filter_var($defaults['antiCheatPause'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $defaults['autoSubmitOnTimeout'] = filter_var($defaults['autoSubmitOnTimeout'] ?? true, FILTER_VALIDATE_BOOLEAN);

        $defaults['phpVersion'] = phpversion();
        $defaults['laravelVersion'] = app()->version();
        $defaults['databaseDriver'] = config('database.default');
        $defaults['serverTime'] = now()->toDateTimeString();

        return $defaults;
    }

    public function publicSettings()
    {
        $settings = self::getSystemSettings();
        $sessions = ExamSession::where('Status', 'Active')->orderBy('ExamDate', 'asc')->orderBy('StartTime', 'asc')->get();
        return response()->json([
            'settings' => $settings,
            'sessions' => $sessions,
        ]);
    }

    public function systemSettings(Request $request)
    {
        if (!self::checkAdminPermission($request->user(), 'System Settings', 'view')) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to view system settings.'], 403);
        }
        $settings = self::getSystemSettings();
        return response()->json(['settings' => $settings]);
    }

    public function saveSystemSettings(Request $request)
    {
        if (!self::checkAdminPermission($request->user(), 'System Settings', 'edit')) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to modify system settings.'], 403);
        }

        $data = $request->validate([
            'institutionName' => 'nullable|string|max:255',
            'portalTitle' => 'nullable|string|max:255',
            'portalSubtitle' => 'nullable|string|max:255',
            'academicYear' => 'nullable|string|max:100',
            'defaultLanguage' => 'nullable|string|in:kh,en',
            'sessionTimeoutMinutes' => 'nullable|integer|min:5|max:1440',
            'allowRegistration' => 'nullable|boolean',
            'forceStrongPassword' => 'nullable|boolean',
            'antiCheatPause' => 'nullable|boolean',
            'autosaveIntervalSeconds' => 'nullable|integer|min:1|max:60',
            'autoSubmitOnTimeout' => 'nullable|boolean',
            'maxExamAttempts' => 'nullable|integer|min:1',
        ]);

        $dir = storage_path('app');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $settingsFile = storage_path('app/settings.json');
        $existing = file_exists($settingsFile) ? (json_decode(file_get_contents($settingsFile), true) ?: []) : [];

        if (!empty($data['institutionName']) && empty($data['portalTitle'])) {
            $data['portalTitle'] = $data['institutionName'];
        }

        $merged = array_merge($existing, $data);
        file_put_contents($settingsFile, json_encode($merged, JSON_PRETTY_PRINT));

        return response()->json(['message' => 'System settings updated successfully!', 'settings' => self::getSystemSettings()]);
    }

    public static function getScheduleDaysYearsData(): array
    {
        $filePath = storage_path('app/schedule_days_years.json');
        if (file_exists($filePath)) {
            $data = json_decode(file_get_contents($filePath), true);
            if (is_array($data) && isset($data['examDays']) && isset($data['academicYears'])) {
                foreach ($data['academicYears'] as &$ay) {
                    if (is_array($ay)) {
                        $val = trim((string)($ay['year'] ?? $ay['name'] ?? ''));
                        $ay['year'] = $val;
                        $ay['name'] = $val;
                    }
                }
                unset($ay);
                return $data;
            }
        }

        $default = [
            'examDays' => [
                [
                    'id' => 'day1',
                    'name' => 'Day 1',
                ],
                [
                    'id' => 'day2',
                    'name' => 'Day 2',
                ]
            ],
            'academicYears' => [
                [
                    'id' => '1',
                    'year' => '2025-2026',
                    'name' => '2025-2026',
                    'isDefault' => false,
                ],
                [
                    'id' => '2',
                    'year' => '2026-2027',
                    'name' => '2026-2027',
                    'isDefault' => true,
                ],
                [
                    'id' => '3',
                    'year' => '2027-2028',
                    'name' => '2027-2028',
                    'isDefault' => false,
                ]
            ]
        ];

        $dir = storage_path('app');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        file_put_contents($filePath, json_encode($default, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $default;
    }

    public static function resolveExamDayName(?string $rawDay, array $configuredDays): ?string
    {
        if (empty($rawDay)) {
            return null;
        }

        $raw = trim($rawDay);
        if (in_array(strtolower($raw), ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])) {
            return null;
        }

        if (empty($configuredDays)) {
            return $raw;
        }

        // 1. Exact match by name (case-insensitive)
        foreach ($configuredDays as $d) {
            $cName = trim((string)($d['name'] ?? ''));
            if ($cName !== '' && strcasecmp($cName, $raw) === 0) {
                return $cName;
            }
        }

        // 2. Exact match by id
        foreach ($configuredDays as $d) {
            $cId = trim((string)($d['id'] ?? ''));
            if ($cId !== '' && strcasecmp($cId, $raw) === 0) {
                return trim((string)($d['name'] ?? ''));
            }
        }

        // 3. Extract day number (Khmer numerals or Arabic digits)
        $khmerDigits = ['០' => '0', '១' => '1', '២' => '2', '៣' => '3', '៤' => '4', '៥' => '5', '៦' => '6', '៧' => '7', '៨' => '8', '៩' => '9'];
        $converted = strtr($raw, $khmerDigits);
        if (preg_match('/(?:day|ថ្ងៃទី|ថ្ងៃ)?\s*(\d+)/iu', $converted, $m)) {
            $dayNum = (int)$m[1];
            foreach ($configuredDays as $idx => $d) {
                $cId = trim((string)($d['id'] ?? ''));
                $cName = trim((string)($d['name'] ?? ''));
                $cConverted = strtr($cName, $khmerDigits);
                if (preg_match('/(?:day|ថ្ងៃទី|ថ្ងៃ)?\s*(\d+)/iu', $cConverted, $cm)) {
                    if ((int)$cm[1] === $dayNum) {
                        return $cName;
                    }
                }
                if ($cId === (string)$dayNum || $cId === 'day' . $dayNum || ($idx + 1) === $dayNum) {
                    return $cName;
                }
            }
        }

        return $raw;
    }

    public function getScheduleDaysYears(Request $request)
    {
        $data = self::getScheduleDaysYearsData();
        return response()->json($data);
    }

    public function saveScheduleDaysYears(Request $request)
    {
        if (!self::checkAdminPermission($request->user(), 'Exam Sessions', 'edit') &&
            !self::checkAdminPermission($request->user(), 'Exam Sessions', 'create')) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $examDays = $request->input('examDays', []);
        $rawAcademicYears = $request->input('academicYears', []);

        $academicYears = [];
        if (is_array($rawAcademicYears)) {
            foreach ($rawAcademicYears as $ay) {
                if (is_array($ay)) {
                    $val = trim((string)($ay['year'] ?? $ay['name'] ?? ''));
                    $ay['year'] = $val;
                    $ay['name'] = $val;
                    $academicYears[] = $ay;
                }
            }
        }

        $payload = [
            'examDays' => is_array($examDays) ? $examDays : [],
            'academicYears' => $academicYears,
        ];

        $dir = storage_path('app');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $filePath = storage_path('app/schedule_days_years.json');
        file_put_contents($filePath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        foreach ($payload['academicYears'] as $ay) {
            if (!empty($ay['isDefault']) && !empty($ay['year'])) {
                $settingsFile = storage_path('app/settings.json');
                $existing = file_exists($settingsFile) ? (json_decode(file_get_contents($settingsFile), true) ?: []) : [];
                $existing['academicYear'] = $ay['year'];
                file_put_contents($settingsFile, json_encode($existing, JSON_PRETTY_PRINT));
                break;
            }
        }

        return response()->json([
            'message' => 'Schedule Days & Academic Years saved successfully!',
            'data' => $payload
        ]);
    }

    public function students(Request $request)
    {
        if (!self::checkAdminPermission($request->user(), 'Students', 'view')) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to view students.'], 403);
        }

        try {
            $daysYears = self::getScheduleDaysYearsData();
            $configuredExamDays = $daysYears['examDays'] ?? [];

            $submissionCounts = [];
            $studentSubmissions = [];
            try {
                $submissionCounts = DB::table('tblstudentsubmission as ss')
                    ->whereNotNull('ss.CompletedAt')
                    ->groupBy('ss.StudentId')
                    ->select('ss.StudentId', DB::raw('COUNT(ss.SubmissionId) as count'))
                    ->pluck('count', 'ss.StudentId')
                    ->toArray();

                $studentSubmissions = DB::table('tblstudentsubmission as ss')
                    ->join('tbltest as t', 'ss.TestId', '=', 't.TestId')
                    ->select('ss.StudentId', 't.TestId', 't.TestName', 'ss.Score', 'ss.CompletedAt')
                    ->get()
                    ->groupBy('StudentId');
            } catch (\Throwable $e) {
                \Log::warning('Failed to calculate submission counts: ' . $e->getMessage());
            }

            // 1. Query Students strictly from tblstudent
            $students = DB::table('tblstudent as s')
                ->leftJoin('tblexamsession as es', 's.SessionId', '=', 'es.SessionId')
                ->select(
                    's.StudentId as id',
                    's.StudentId as studentId',
                    's.StudentCode as studentCode',
                    's.FirstName as firstName',
                    's.LastName as lastName',
                    's.Phone as phone',
                    's.Gender as gender',
                    's.ProfileImage as photo',
                    's.SessionId as sessionId',
                    's.ExamDay as examDay',
                    's.AcademicYear as academicYear',
                    'es.SessionName as sessionName',
                    'es.Days as days',
                    'es.Years as years',
                    'es.ExamDate as examDate',
                    'es.StartTime as startTime',
                    'es.EndTime as endTime',
                    'es.Description as sessionDescription',
                    's.created_at as createdAt'
                )
                ->orderBy('s.StudentId', 'desc')
                ->get()
                ->map(function ($s) use ($submissionCounts, $studentSubmissions, $configuredExamDays) {
                    $examCount = $submissionCounts[$s->id] ?? 0;

                    $taken = $studentSubmissions[$s->id] ?? collect();
                    $takenExams = $taken->map(fn($t) => [
                        'testId' => $t->TestId,
                        'testName' => $t->TestName,
                        'score' => $t->Score,
                        'completedAt' => $t->CompletedAt,
                    ])->values()->all();
                    $takenExamNames = $taken->pluck('TestName')->filter()->unique()->values()->all();

                    $fullName = trim(($s->firstName ?? '') . ' ' . ($s->lastName ?? ''));
                    $displayCode = $s->studentCode ?: ('SR' . date('Y') . str_pad((string)$s->id, 5, '0', STR_PAD_LEFT));

                    return [
                        'id' => $s->id,
                        'studentId' => $s->id,
                        'studentCode' => $displayCode,
                        'name' => $fullName ?: $displayCode,
                        'first_name' => $s->firstName,
                        'last_name' => $s->lastName,
                        'firstName' => $s->firstName,
                        'lastName' => $s->lastName,
                        'email' => $displayCode,
                        'username' => $displayCode,
                        'phone' => $s->phone,
                        'photo' => $s->photo ?? null,
                        'profileImage' => $s->photo ?? null,
                        'role' => 'Student',
                        'status' => 'Active',
                        'gender' => $s->gender,
                        'sessionId' => $s->sessionId,
                        'sessionName' => $s->sessionName ?? 'Unassigned Shift',
                        'examDay' => self::resolveExamDayName($s->examDay ?? null, $configuredExamDays),
                        'academicYear' => $s->academicYear ?? null,
                        'days' => self::resolveExamDayName($s->days ?? null, $configuredExamDays),
                        'years' => $s->years ?? null,
                        'examDate' => $s->examDate,
                        'startTime' => $s->startTime,
                        'endTime' => $s->endTime,
                        'sessionDescription' => $s->sessionDescription,
                        'hasTakenExam' => $examCount > 0,
                        'examCount' => $examCount,
                        'takenExams' => $takenExams,
                        'takenExamNames' => $takenExamNames,
                    ];
                })
                ->values();

            // 2. Query Admins strictly from tbladmin
            $adminTable = 'tbladmin';
            $adminIdCol = 'AdminId';

            $admins = DB::table($adminTable)
                ->orderBy($adminIdCol, 'desc')
                ->get()
                ->map(function ($a) use ($adminIdCol) {
                    $fullName = trim(($a->FirstName ?? '') . ' ' . ($a->LastName ?? ''));
                    return [
                        'id' => $a->{$adminIdCol},
                        'name' => $fullName ?: $a->Username,
                        'first_name' => $a->FirstName,
                        'last_name' => $a->LastName,
                        'firstName' => $a->FirstName,
                        'lastName' => $a->LastName,
                        'email' => $a->Username,
                        'username' => $a->Username,
                        'phone' => $a->Phone,
                        'role' => $a->Role ?? 'Admin',
                        'status' => $a->Status ?? 'Active',
                        'photo' => $a->ProfileImage ?? null,
                        'profileImage' => $a->ProfileImage ?? null,
                    ];
                })
                ->values();

            $exams = collect();
            try {
                $exams = DB::table('tbltest')
                    ->orderBy('TestName')
                    ->select('TestId', 'TestName')
                    ->get();
            } catch (\Throwable $e) {
                $exams = collect();
            }

            $sessionsList = ExamSession::orderBy('ExamDate', 'asc')->orderBy('StartTime', 'asc')->get();
            $daysYears = self::getScheduleDaysYearsData();

            return response()->json([
                'students' => $students,
                'admins' => $admins,
                'exams' => $exams,
                'sessions' => $sessionsList,
                'examDays' => $daysYears['examDays'] ?? [],
                'academicYears' => $daysYears['academicYears'] ?? [],
            ]);
        } catch (\Throwable $e) {
            \Log::error('AdminController::students exception: ' . $e->getMessage());
            return response()->json([
                'message' => 'មានបញ្ហាតភ្ជាប់មូលដ្ឋានទិន្នន័យ (Database connection error).',
                'students' => [],
                'admins' => [],
                'exams' => [],
                'sessions' => [],
            ], 500);
        }
    }

    public function examSessions(Request $request)
    {
        if (!self::checkAdminPermission($request->user(), 'Skills & Groups', 'view')) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to view exam sessions.'], 403);
        }

        $sessions = ExamSession::withCount('students')->orderBy('SessionId', 'desc')->get();
        return response()->json([
            'sessions' => $sessions,
        ]);
    }

    public function addExamSession(Request $request)
    {
        if (!self::checkAdminPermission($request->user(), 'Exam Sessions', 'create')) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $data = $request->validate([
            'sessionName' => ['required', 'string', 'max:255'],
            'examDate' => ['nullable', 'date'],
            'days' => ['nullable', 'string', 'max:100'],
            'years' => ['nullable', 'string', 'max:100'],
            'startTime' => ['nullable'],
            'endTime' => ['nullable'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:Active,Inactive'],
        ]);

        $session = ExamSession::create([
            'SessionName' => $data['sessionName'],
            'ExamDate' => $data['examDate'] ?? null,
            'Days' => $data['days'] ?? null,
            'Years' => $data['years'] ?? null,
            'StartTime' => $data['startTime'] ?? null,
            'EndTime' => $data['endTime'] ?? null,
            'Description' => $data['description'] ?? null,
            'Status' => $data['status'] ?? 'Active',
        ]);

        return response()->json(['session' => $session], 201);
    }

    public function updateExamSession(Request $request, $id)
    {
        if (!self::checkAdminPermission($request->user(), 'Exam Sessions', 'edit')) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $data = $request->validate([
            'sessionName' => ['required', 'string', 'max:255'],
            'examDate' => ['nullable', 'date'],
            'days' => ['nullable', 'string', 'max:100'],
            'years' => ['nullable', 'string', 'max:100'],
            'startTime' => ['nullable'],
            'endTime' => ['nullable'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:Active,Inactive'],
        ]);

        $session = ExamSession::findOrFail($id);
        $session->update([
            'SessionName' => $data['sessionName'],
            'ExamDate' => $data['examDate'] ?? null,
            'Days' => $data['days'] ?? null,
            'Years' => $data['years'] ?? null,
            'StartTime' => $data['startTime'] ?? null,
            'EndTime' => $data['endTime'] ?? null,
            'Description' => $data['description'] ?? null,
            'Status' => $data['status'] ?? 'Active',
        ]);

        return response()->json(['session' => $session]);
    }

    public function deleteExamSession(Request $request, $id)
    {
        if (!self::checkAdminPermission($request->user(), 'Exam Sessions', 'delete')) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $session = ExamSession::findOrFail($id);
        $session->delete();
        return response()->json(['message' => 'Exam session deleted.']);
    }

    public static function getSkillsGroupsData(): array
    {
        $filePath = storage_path('app/skills_groups_durations.json');
        if (file_exists($filePath)) {
            $data = json_decode(file_get_contents($filePath), true);
            if (is_array($data) && isset($data['skills']) && isset($data['groups']) && isset($data['durations'])) {
                return $data;
            }
        }

        $default = [
            'skills' => [
                ['id' => '1', 'name' => 'Graphic design'],
                ['id' => '2', 'name' => 'SFE Programming'],
                ['id' => '3', 'name' => 'សេវាកម្មកុំព្យូទ័រ'],
            ],
            'groups' => [
                ['id' => '1', 'name' => 'ក្រាហ្វិកដោយកុំព្យូទ័រ (AI)'],
                ['id' => '2', 'name' => 'ក្រុម ៤'],
                ['id' => '3', 'name' => 'ក្រុម A'],
                ['id' => '4', 'name' => 'ក្រុម B'],
                ['id' => '5', 'name' => 'ជំនាន់ ៤'],
            ],
            'durations' => [
                ['id' => '1', 'name' => '4 ខែ (4 Months)'],
                ['id' => '2', 'name' => '6 ខែ (6 Months)'],
                ['id' => '3', 'name' => '8 ខែ (8 Months)'],
            ],
        ];

        $dir = storage_path('app');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        file_put_contents($filePath, json_encode($default, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $default;
    }

    public function skillsGroups(Request $request)
    {
        if (!self::checkAdminPermission($request->user(), 'Skills & Groups', 'view')) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to view skills & groups.'], 403);
        }

        $data = self::getSkillsGroupsData();
        $sessions = ExamSession::orderBy('ExamDate', 'asc')->orderBy('StartTime', 'asc')->get();

        return response()->json([
            'skills' => $data['skills'],
            'groups' => $data['groups'],
            'durations' => $data['durations'],
            'sessions' => $sessions,
        ]);
    }

    public function saveSkillsGroups(Request $request)
    {
        $skills = $request->input('skills', []);
        $groups = $request->input('groups', []);
        $durations = $request->input('durations', []);

        $payload = [
            'skills' => is_array($skills) ? $skills : [],
            'groups' => is_array($groups) ? $groups : [],
            'durations' => is_array($durations) ? $durations : [],
        ];

        $dir = storage_path('app');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        file_put_contents(storage_path('app/skills_groups_durations.json'), json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return response()->json([
            'message' => 'Skills, groups, and durations saved successfully!',
            'data' => $payload
        ]);
    }

    public function tests(Request $request)
    {
        try {
            $tests = DB::table('tbltest as t')
                ->leftJoin('tbladmin as a', 't.CreatedByUserId', '=', 'a.AdminId')
                ->leftJoin('tblexamsession as es', 't.SessionId', '=', 'es.SessionId')
                ->select(
                    't.TestId as id',
                    't.TestName as name',
                    't.SessionId as sessionId',
                    't.ExamDay as examDay',
                    't.AcademicYear as academicYear',
                    DB::raw("COALESCE(es.SessionName, '') as sessionName"),
                    'es.ExamDate as examDate',
                    'es.StartTime as startTime',
                    'es.EndTime as endTime',
                    DB::raw("COALESCE(es.Description, '') as venue"),
                    't.DurationMinutes as durationMinutes',
                    't.TotalMarks as totalMarks',
                    't.Status as status',
                    't.ScheduledAt as scheduledAt',
                    't.FinishedAt as finishedAt',
                    't.created_at as createdAt',
                    DB::raw("COALESCE(TRIM(CONCAT(a.FirstName, ' ', a.LastName)), a.Username, 'Admin') as createdBy"),
                    DB::raw('(SELECT COUNT(*) FROM tblquestion WHERE tblquestion.TestId = t.TestId) as questionCount')
                )
                ->orderBy('t.TestId', 'desc')
                ->get()
                ->map(function ($t) {
                    $status = $t->status;
                    if ($status === 'Published') {
                        $end = null;
                        if ($t->finishedAt) {
                            $end = \Carbon\Carbon::parse($t->finishedAt);
                        } elseif ($t->scheduledAt) {
                            $end = \Carbon\Carbon::parse($t->scheduledAt)->addMinutes($t->durationMinutes);
                        }

                        if ($end && now()->greaterThan($end)) {
                            $status = 'Finished';
                        }
                    }
                    $t->status = $status;
                    return $t;
                });

            return response()->json(['tests' => $tests]);
        } catch (\Throwable $e) {
            \Log::error('AdminController::tests exception: ' . $e->getMessage());
            return response()->json([
                'message' => 'មានបញ្ហាតភ្ជាប់មូលដ្ឋានទិន្នន័យ (Database connection error).',
                'tests' => []
            ], 500);
        }
    }

    public static function autoFinalizeOverdueSubmissions(): void
    {
        try {
            $overdueSubmissions = StudentSubmission::whereNull('CompletedAt')
                ->with(['test.questions.answers', 'details'])
                ->get();

            foreach ($overdueSubmissions as $sub) {
                $t = $sub->test;
                if (!$t) continue;

                $durMin = (int) max(1, round((float) $t->DurationMinutes));
                $started = $sub->StartedAt ? \Carbon\Carbon::parse($sub->StartedAt) : ($sub->created_at ? \Carbon\Carbon::parse($sub->created_at) : now());
                $deadline = $started->copy()->addMinutes($durMin);

                if ($t->FinishedAt) {
                    $finAt = \Carbon\Carbon::parse($t->FinishedAt);
                    if ($finAt->lt($deadline)) {
                        $deadline = $finAt;
                    }
                }

                if (now()->greaterThanOrEqualTo($deadline)) {
                    $questions = $t->questions ?: collect();
                    $totalQuestions = $questions->count();
                    $totalMarks = $t->TotalMarks ?: 100;
                    $details = $sub->details ?: collect();
                    $correctCount = 0;

                    foreach ($questions as $q) {
                        $detail = $details->firstWhere('QuestionId', $q->QuestionId);
                        if ($detail && $detail->SelectedAnswerId) {
                            $ans = $q->answers->firstWhere('AnswerId', $detail->SelectedAnswerId);
                            $isCorrect = $ans ? (bool) $ans->IsCorrect : false;
                            $detail->update(['IsCorrect' => $isCorrect]);
                            if ($isCorrect) {
                                $correctCount++;
                            }
                        }
                    }

                    $pointsPerQuestion = $totalQuestions > 0 ? ($totalMarks / $totalQuestions) : 0;
                    $finalScore = round($correctCount * $pointsPerQuestion, 2);

                    $sub->update([
                        'CompletedAt' => $deadline->lt(now()) ? $deadline : now(),
                        'Score' => $finalScore,
                        'TotalCorrect' => $correctCount,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Error auto-finalizing overdue submissions: ' . $e->getMessage());
        }
    }

    public function results(Request $request)
    {
        if (!self::checkAdminPermission($request->user(), 'Results', 'view')) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to view results.'], 403);
        }

        self::autoFinalizeOverdueSubmissions();

        $nameSql = DB::connection()->getDriverName() === 'sqlite'
            ? "(s.FirstName || ' ' || s.LastName) as studentName"
            : "CONCAT(s.FirstName, ' ', s.LastName) as studentName";

        $daysYears = self::getScheduleDaysYearsData();
        $configuredExamDays = $daysYears['examDays'] ?? [];

        $results = DB::table('tblstudentsubmission as ss')
            ->join('tblstudent as s', 'ss.StudentId', '=', 's.StudentId')
            ->join('tbltest as t', 'ss.TestId', '=', 't.TestId')
            ->leftJoin('tblexamsession as es', 's.SessionId', '=', 'es.SessionId')
            ->select(
                'ss.SubmissionId as id',
                's.StudentId as studentId',
                's.StudentCode as studentCode',
                's.ExamDay as studentExamDay',
                's.AcademicYear as studentAcademicYear',
                DB::raw($nameSql),
                't.TestId as testId',
                't.TestName as testName',
                't.ExamDay as testExamDay',
                't.AcademicYear as testAcademicYear',
                't.TotalMarks as totalMarks',
                't.PassScore as passScore',
                'ss.TotalCorrect as totalCorrect',
                'ss.Score as score',
                'ss.StartedAt as startedAt',
                'ss.CompletedAt as completedAt',
                'es.SessionId as sessionId',
                'es.SessionName as sessionName',
                'es.Days as days',
                'es.Days as sessionDays',
                'es.Years as years',
                'es.ExamDate as examDate',
                'es.StartTime as startTime',
                'es.EndTime as endTime'
            )
            ->orderByRaw('COALESCE(ss.CompletedAt, ss.StartedAt, ss.created_at) DESC')
            ->get()
            ->map(function ($r) use ($configuredExamDays) {
                $accuracy = $r->totalMarks > 0
                    ? round(($r->score / $r->totalMarks) * 100, 1)
                    : 0;

                $dateObj = !empty($r->examDate) ? \Carbon\Carbon::parse($r->examDate) : (!empty($r->completedAt) ? \Carbon\Carbon::parse($r->completedAt) : (!empty($r->startedAt) ? \Carbon\Carbon::parse($r->startedAt) : now()));
                
                $studentExamDay = trim((string)($r->studentExamDay ?? ''));
                $testExamDay = trim((string)($r->testExamDay ?? ''));
                $sessionDays = trim((string)($r->sessionDays ?? $r->days ?? ''));
                $rawDay = $studentExamDay ?: ($testExamDay ?: $sessionDays);
                $examDay = self::resolveExamDayName($rawDay, $configuredExamDays);
                
                $studentAy = trim((string)($r->studentAcademicYear ?? ''));
                $testAy = trim((string)($r->testAcademicYear ?? ''));
                $sessionYears = trim((string)($r->years ?? ''));
                $academicYear = $studentAy ?: ($testAy ?: ($sessionYears ?: $dateObj->format('Y')));

                return array_merge((array) $r, [
                    'accuracy' => $accuracy,
                    'examDay' => $examDay,
                    'days' => $examDay,
                    'years' => $academicYear,
                    'academicYear' => $academicYear,
                    'sessionName' => $r->sessionName ?: 'គ្រប់វេនទាំងអស់ (General Shift)',
                    'groupName' => $r->sessionName ?? 'General Shift',
                    'skillName' => $r->sessionName ?? 'Scholarship Exam',
                ]);
            });

        $sessions = ExamSession::orderBy('ExamDate', 'asc')->get();
        $tests = Test::orderBy('TestName')->get(['TestId', 'TestName']);

        return response()->json([
            'results' => $results,
            'sessions' => $sessions,
            'tests' => $tests,
            'academicYears' => $daysYears['academicYears'] ?? [],
        ]);
    }

    public function deleteSubmission(Request $request, $id)
    {
        if (!self::checkAdminPermission($request->user(), 'Results', 'delete')) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to delete results.'], 403);
        }

        DB::table('tblstudentsubmission')->where('SubmissionId', $id)->delete();
        return response()->json(['message' => 'Result deleted successfully']);
    }

    public function updateStudent(Request $request, $id)
    {
        if (!self::checkAdminPermission($request->user(), 'Students', 'edit')) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to edit users.'], 403);
        }

        $reqRole = $request->input('role');
        $isReqAdmin = in_array($reqRole, ['Admin', 'Super Admin', 'SuperAdmin']) || $request->has('username');
        $isReqStudent = ($reqRole === 'Student') || $request->has('studentCode') || $request->has('gender');

        $student = null;
        $admin = null;

        if ($isReqAdmin) {
            $admin = Admin::find($id);
        } elseif ($isReqStudent) {
            $student = Student::find($id);
        } else {
            // Fallback: check Admin first if username provided or role, otherwise Student
            $student = Student::find($id);
            if (!$student) {
                $admin = Admin::find($id);
            }
        }

        if (!$student && !$admin) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $isStudent = ($student !== null);

        $rules = [
            'firstName' => ['required', 'string', 'max:255'],
            'lastName' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'newPassword' => ['nullable', 'string', 'min:6'],
            'role' => ['nullable', 'string', 'in:Student,Admin,Super Admin,SuperAdmin'],
        ];

        if ($isStudent) {
            $rules['gender'] = ['required', 'string'];
            $rules['sessionId'] = ['nullable'];
            $rules['studentCode'] = ['nullable', 'string', 'max:50'];
        } else {
            $rules['username'] = ['required', 'string', 'regex:/^\S+$/', 'unique:tbladmin,Username,' . $admin->AdminId . ',AdminId'];
        }

        $data = $request->validate($rules, [
            'username.regex' => 'Username មិនអាចមានដកឃ្លាទេ (Username cannot contain spaces).',
            'gender.required' => 'The gender field is required for students.',
        ]);

        $photoPath = $this->processUploadedPhoto($request->input('photo'), $request->file('photo'));

        if ($isStudent) {
            $studentUpdate = [
                'FirstName' => $data['firstName'],
                'LastName' => $data['lastName'],
                'Phone' => $data['phone'] ?? '',
                'Gender' => $data['gender'] ?? 'Male',
            ];
            if ($request->has('sessionId')) {
                $studentUpdate['SessionId'] = $request->input('sessionId') ?: null;
            }
            if ($request->has('examDay')) {
                $studentUpdate['ExamDay'] = $request->input('examDay') ?: null;
            }
            if ($request->has('academicYear')) {
                $studentUpdate['AcademicYear'] = $request->input('academicYear') ?: null;
            }
            if (!empty($request->input('studentCode'))) {
                $studentUpdate['StudentCode'] = $request->input('studentCode');
            }
            if ($photoPath) {
                $studentUpdate['ProfileImage'] = $photoPath;
            }
            $student->update($studentUpdate);

            $session = $student->SessionId ? ExamSession::find($student->SessionId) : null;

            return response()->json([
                'message' => 'Student updated.',
                'student' => [
                    'id' => $student->StudentId,
                    'name' => $data['firstName'] . ' ' . $data['lastName'],
                    'studentCode' => $student->StudentCode,
                    'phone' => $data['phone'] ?? '',
                    'gender' => $student->Gender,
                    'role' => 'Student',
                    'sessionId' => $student->SessionId,
                    'sessionName' => $session?->SessionName ?? 'Unassigned Shift',
                    'examDay' => $student->ExamDay,
                    'academicYear' => $student->AcademicYear,
                ]
            ]);
        } else {
            $currentUser = auth()->user();
            $isSelectingSuperAdmin = in_array($request->input('role'), ['Super Admin', 'SuperAdmin']);
            $currentRole = $currentUser ? ($currentUser->Role ?? $currentUser->role ?? '') : '';
            $isCurrentSuperAdmin = in_array($currentRole, ['Super Admin', 'SuperAdmin']);

            if ($isSelectingSuperAdmin && !$isCurrentSuperAdmin) {
                return response()->json(['message' => 'Unauthorized. Only Super Admins can assign Super Admin role.'], 403);
            }

            $adminUpdate = [
                'FirstName' => $data['firstName'],
                'LastName' => $data['lastName'],
                'Phone' => $data['phone'] ?? '',
                'Username' => $data['username'],
            ];
            if (!empty($data['role'])) {
                $adminUpdate['Role'] = $data['role'];
            }
            if (!empty($data['newPassword'])) {
                $adminUpdate['Password'] = \Illuminate\Support\Facades\Hash::make($data['newPassword']);
            }
            if ($photoPath) {
                $adminUpdate['ProfileImage'] = $photoPath;
            }
            $admin->update($adminUpdate);

            return response()->json([
                'message' => 'Administrator updated successfully.',
                'student' => [
                    'id' => $admin->AdminId,
                    'name' => $data['firstName'] . ' ' . $data['lastName'],
                    'username' => $admin->Username,
                    'phone' => $data['phone'] ?? '',
                    'role' => $admin->Role,
                    'photo' => $admin->ProfileImage
                ]
            ]);
        }
    }

    public function deleteStudent(Request $request, $id)
    {
        if (!self::checkAdminPermission($request->user(), 'Students', 'delete')) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to delete.'], 403);
        }

        $reqRole = $request->input('role');
        if (in_array($reqRole, ['Admin', 'Super Admin', 'SuperAdmin'])) {
            $deleted = Admin::where('AdminId', $id)->delete();
            if (!$deleted) {
                return response()->json(['message' => 'Record not found.'], 404);
            }
            return response()->json(['message' => 'Deleted successfully']);
        } elseif ($reqRole === 'Student') {
            $deleted = Student::where('StudentId', $id)->delete();
            if (!$deleted) {
                return response()->json(['message' => 'Record not found.'], 404);
            }
            return response()->json(['message' => 'Deleted successfully']);
        }

        $deletedStudent = Student::where('StudentId', $id)->delete();
        $deletedAdmin = $deletedStudent ? false : Admin::where('AdminId', $id)->delete();

        if (!$deletedStudent && !$deletedAdmin) {
            return response()->json(['message' => 'Record not found.'], 404);
        }

        return response()->json(['message' => 'Deleted successfully']);
    }

    public function addStudent(Request $request)
    {
        if (!self::checkAdminPermission($request->user(), 'Students', 'create')) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to add users.'], 403);
        }

        $currentUser = auth()->user();
        $isSelectingSuperAdmin = in_array($request->input('role'), ['Super Admin', 'SuperAdmin']);
        $isCurrentSuperAdmin = $currentUser && in_array($currentUser->role, ['Super Admin', 'SuperAdmin']);

        if ($isSelectingSuperAdmin && !$isCurrentSuperAdmin) {
            return response()->json(['message' => 'Unauthorized. Only Super Admins can create new Super Admins.'], 403);
        }

        $isStudent = $request->input('role', 'Student') === 'Student';

        $rules = [
            'firstName' => ['required', 'string', 'max:255'],
            'lastName' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'role' => ['required', 'string', 'in:Student,Admin,Super Admin,SuperAdmin'],
        ];

        if ($isStudent) {
            $rules['gender'] = ['required', 'string'];
            $rules['sessionId'] = ['nullable'];
            $rules['studentCode'] = ['nullable', 'string'];
            $rules['examDay'] = ['nullable', 'string', 'max:100'];
            $rules['academicYear'] = ['nullable', 'string', 'max:100'];
        } else {
            $rules['username'] = ['required', 'string', 'regex:/^\S+$/', 'unique:tbladmin,Username'];
            $rules['password'] = ['required', 'string', 'min:6'];
        }

        $data = $request->validate($rules, [
            'username.regex' => 'Username មិនអាចមានដកឃ្លាទេ (Username cannot contain spaces).',
            'gender.required' => 'The gender field is required for students.',
        ]);
        $role = $request->input('role', 'Student');
        $photoPath = $this->processUploadedPhoto($request->input('photo'), $request->file('photo'));

        if ($isStudent) {
            $year = date('Y');
            $studentCode = $request->input('studentCode');
            if (empty($studentCode)) {
                for ($i = 0; $i < 100; $i++) {
                    $randCode = 'SR' . $year . str_pad((string)mt_rand(10000, 99999), 5, '0', STR_PAD_LEFT);
                    if (!Student::where('StudentCode', $randCode)->exists()) {
                        $studentCode = $randCode;
                        break;
                    }
                }
                if (empty($studentCode)) {
                    $studentCode = 'SR' . $year . str_pad((string)((Student::max('StudentId') ?? 0) + 1), 5, '0', STR_PAD_LEFT);
                }
            }

            Student::create([
                'StudentCode' => $studentCode,
                'SessionId' => !empty($data['sessionId']) ? $data['sessionId'] : null,
                'FirstName' => $data['firstName'],
                'LastName' => $data['lastName'],
                'Gender' => $data['gender'],
                'Phone' => $data['phone'] ?? '',
                'ExamDay' => $request->input('examDay') ?: null,
                'AcademicYear' => $request->input('academicYear') ?: null,
                'ProfileImage' => $photoPath,
            ]);
        } else {
            Admin::create([
                'Username' => $data['username'],
                'Password' => \Illuminate\Support\Facades\Hash::make($data['password']),
                'Role' => $role,
                'Status' => 'Active',
                'FirstName' => $data['firstName'],
                'LastName' => $data['lastName'],
                'Phone' => $data['phone'] ?? '',
                'ProfileImage' => $photoPath,
            ]);
        }

        return response()->json(['message' => "$role created.", 'studentCode' => $studentCode ?? null]);
    }

    public function importStudents(Request $request)
    {
        if (!self::checkAdminPermission($request->user(), 'Students', 'create')) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to import students.'], 403);
        }

        $studentsData = $request->input('students', []);
        if (empty($studentsData) || !is_array($studentsData)) {
            return response()->json(['message' => 'No student data provided.'], 422);
        }

        $sessions = ExamSession::all()->keyBy('SessionName');
        $sessionIds = ExamSession::all()->pluck('SessionId')->toArray();

        $imported = 0;
        $year = date('Y');

        DB::beginTransaction();
        try {
            foreach ($studentsData as $row) {
                $firstName = trim($row['firstName'] ?? $row['FirstName'] ?? '');
                $lastName = trim($row['lastName'] ?? $row['LastName'] ?? '');
                if (!$firstName && !$lastName) {
                    continue;
                }

                $genderRaw = trim($row['gender'] ?? $row['Gender'] ?? 'Male');
                if (in_array(strtolower($genderRaw), ['ស្រី', 'female', 'f', 'woman'])) {
                    $gender = 'Female';
                } elseif (in_array(strtolower($genderRaw), ['ប្រុស', 'male', 'm', 'man'])) {
                    $gender = 'Male';
                } else {
                    $gender = 'Male';
                }

                $phone = trim($row['phone'] ?? $row['Phone'] ?? '');
                $examDay = trim($row['examDay'] ?? $row['ExamDay'] ?? $row['exam_day'] ?? '');
                $academicYear = trim($row['academicYear'] ?? $row['AcademicYear'] ?? $row['academic_year'] ?? '');

                // Match session
                $sessionId = null;
                if (!empty($row['sessionId']) && in_array((int)$row['sessionId'], $sessionIds)) {
                    $sessionId = (int)$row['sessionId'];
                } elseif (!empty($row['sessionName'])) {
                    $sName = trim($row['sessionName']);
                    if (isset($sessions[$sName])) {
                        $sessionId = $sessions[$sName]->SessionId;
                    } else {
                        $match = $sessions->first(fn($s) => stripos($s->SessionName, $sName) !== false);
                        if ($match) $sessionId = $match->SessionId;
                    }
                }

                // Student Code
                $code = trim($row['studentCode'] ?? $row['StudentCode'] ?? '');
                if (!empty($code)) {
                    $existing = Student::where('StudentCode', $code)->first();
                    if ($existing) {
                        $updateData = [
                            'FirstName' => $firstName,
                            'LastName' => $lastName,
                            'Gender' => $gender,
                            'Phone' => $phone ?: $existing->Phone,
                            'SessionId' => $sessionId ?: $existing->SessionId,
                        ];
                        if ($examDay) $updateData['ExamDay'] = $examDay;
                        if ($academicYear) $updateData['AcademicYear'] = $academicYear;
                        $existing->update($updateData);
                        $imported++;
                        continue;
                    }
                } else {
                    for ($k = 0; $k < 50; $k++) {
                        $randCode = 'SR' . $year . str_pad((string)mt_rand(10000, 99999), 5, '0', STR_PAD_LEFT);
                        if (!Student::where('StudentCode', $randCode)->exists()) {
                            $code = $randCode;
                            break;
                        }
                    }
                    if (!$code) {
                        $code = 'SR' . $year . str_pad((string)((Student::max('StudentId') ?? 0) + 1), 5, '0', STR_PAD_LEFT);
                    }
                }

                Student::create([
                    'StudentCode' => $code,
                    'SessionId' => $sessionId,
                    'FirstName' => $firstName,
                    'LastName' => $lastName,
                    'Gender' => $gender,
                    'Phone' => $phone,
                    'ExamDay' => $examDay ?: null,
                    'AcademicYear' => $academicYear ?: null,
                ]);

                $imported++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "បាននាំចូលបេក្ខជនចំនួន {$imported} នាក់ដោយជោគជ័យ (Successfully imported {$imported} candidates).",
                'count' => $imported
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'ការនាំចូលបរាជ័យ៖ ' . $e->getMessage()
            ], 500);
        }
    }

    public function liveMonitor(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        self::autoFinalizeOverdueSubmissions();

        try {
            $hasInterruptions = \Illuminate\Support\Facades\Schema::hasColumn('tblstudentsubmission', 'Interruptions')
                || \Illuminate\Support\Facades\Schema::hasColumn('tblStudentSubmission', 'Interruptions');

            $selectFields = [
                'ss.SubmissionId as submissionId',
                'ss.StudentId as studentId',
                's.StudentCode as studentCode',
                's.FirstName as firstName',
                's.LastName as lastName',
                's.Gender as gender',
                's.Phone as phone',
                'es.SessionId as sessionId',
                'es.SessionName as sessionName',
                'ss.TestId as testId',
                't.TestName as testName',
                't.DurationMinutes as durationMinutes',
                't.TotalMarks as totalMarks',
                'ss.Score as score',
                'ss.StartedAt as startedAt',
                'ss.CompletedAt as completedAt',
                DB::raw('(SELECT COUNT(*) FROM tblquestion WHERE tblquestion.TestId = t.TestId) as totalQuestions'),
                DB::raw('(SELECT COUNT(*) FROM tblsubmissiondetail WHERE tblsubmissiondetail.SubmissionId = ss.SubmissionId AND tblsubmissiondetail.SelectedAnswerId IS NOT NULL) as answeredCount')
            ];

            if ($hasInterruptions) {
                $selectFields[] = 'ss.Interruptions as interruptions';
            } else {
                $selectFields[] = DB::raw('0 as interruptions');
            }

            $todayCompletedCount = DB::table('tblstudentsubmission')
                ->whereDate('CompletedAt', now()->toDateString())
                ->count();

            $submissions = DB::table('tblstudentsubmission as ss')
                ->join('tblstudent as s', 'ss.StudentId', '=', 's.StudentId')
                ->leftJoin('tblexamsession as es', 's.SessionId', '=', 'es.SessionId')
                ->join('tbltest as t', 'ss.TestId', '=', 't.TestId')
                ->select($selectFields)
                ->whereNull('ss.CompletedAt')
                ->where('ss.StartedAt', '>=', now()->subHours(12))
                ->orderBy('ss.StartedAt', 'desc')
                ->get()
                ->map(function ($r) {
                    $studentName = trim($r->firstName . ' ' . $r->lastName);
                    $isCompleted = !empty($r->completedAt);
                    $progress = $r->totalQuestions > 0 ? round(($r->answeredCount / $r->totalQuestions) * 100) : 0;
                    
                    $started = $r->startedAt ? \Carbon\Carbon::parse($r->startedAt) : now();
                    $endedTimestamp = $r->completedAt ? \Carbon\Carbon::parse($r->completedAt)->getTimestamp() : now()->getTimestamp();
                    $elapsedMinutes = (int) max(0, round(($endedTimestamp - $started->getTimestamp()) / 60));
                    $remainingMinutes = max(0, $r->durationMinutes - $elapsedMinutes);

                    return [
                        'submissionId' => $r->submissionId,
                        'studentId' => $r->studentId,
                        'studentCode' => $r->studentCode,
                        'studentName' => $studentName ?: ($r->studentCode ?? 'Candidate'),
                        'gender' => $r->gender,
                        'shift' => $r->sessionName ?? 'Morning Shift',
                        'phone' => $r->phone,
                        'sessionId' => $r->sessionId,
                        'sessionName' => $r->sessionName ?? 'General Shift',
                        'testId' => $r->testId,
                        'testName' => $r->testName,
                        'durationMinutes' => $r->durationMinutes,
                        'totalMarks' => $r->totalMarks,
                        'score' => $r->score,
                        'totalQuestions' => (int) $r->totalQuestions,
                        'answeredCount' => (int) $r->answeredCount,
                        'progress' => $progress,
                        'interruptions' => (int) ($r->interruptions ?? 0),
                        'startedAt' => $r->startedAt,
                        'completedAt' => $r->completedAt,
                        'elapsedMinutes' => $elapsedMinutes,
                        'remainingMinutes' => $remainingMinutes,
                        'status' => $isCompleted ? 'Completed' : ($remainingMinutes <= 0 ? 'Overdue' : 'In Progress'),
                    ];
                })
                ->filter(function ($item) {
                    // Only show actively ongoing sessions currently in progress
                    return $item['status'] === 'In Progress';
                })
                ->values();

            $sessions = ExamSession::orderBy('ExamDate', 'asc')->get(['SessionId', 'SessionName']);
            $sessions = ExamSession::orderBy('ExamDate', 'asc')->get(['SessionId', 'SessionName']);
            $tests = Test::where('Status', 'Published')->orderBy('TestName')->get(['TestId', 'TestName']);

            return response()->json([
                'examinees' => $submissions,
                'activeCount' => $submissions->count(),
                'todayCompletedCount' => $todayCompletedCount,
                'completedTodayCount' => $todayCompletedCount,
                'sessions' => $sessions,
                'skills' => $sessions,
                'groups' => $sessions,
                'tests' => $tests,
                'serverTime' => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            \Log::error('liveMonitor error: ' . $e->getMessage());
            return response()->json([
                'examinees' => [],
                'activeCount' => 0,
                'todayCompletedCount' => 0,
                'completedTodayCount' => 0,
                'sessions' => ExamSession::orderBy('ExamDate', 'asc')->get(['SessionId', 'SessionName']),
                'skills' => [],
                'groups' => [],
                'tests' => Test::where('Status', 'Published')->orderBy('TestName')->get(['TestId', 'TestName']),
                'serverTime' => now()->toIso8601String(),
            ]);
        }
    }

    public function forceSubmit(Request $request, $submissionId)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $submission = \App\Models\StudentSubmission::with(['test.questions.answers', 'details'])->findOrFail($submissionId);

        if ($submission->CompletedAt) {
            return response()->json([
                'success' => true,
                'message' => 'Submission is already completed.',
                'score' => $submission->Score,
                'totalCorrect' => $submission->TotalCorrect,
            ]);
        }

        $test = $submission->test;
        $totalMarks = $test ? $test->TotalMarks : 100;
        $questions = $test ? $test->questions : collect();
        $totalQuestions = $questions->count();

        $details = $submission->details;
        $correctCount = 0;

        foreach ($questions as $q) {
            $detail = $details->firstWhere('QuestionId', $q->QuestionId);
            if ($detail && $detail->SelectedAnswerId) {
                $ans = $q->answers->firstWhere('AnswerId', $detail->SelectedAnswerId);
                $isCorrect = $ans ? (bool) $ans->IsCorrect : false;
                $detail->update(['IsCorrect' => $isCorrect]);
                if ($isCorrect) {
                    $correctCount++;
                }
            }
        }

        $pointsPerQuestion = $totalQuestions > 0 ? ($totalMarks / $totalQuestions) : 0;
        $finalScore = round($correctCount * $pointsPerQuestion, 2);

        $submission->update([
            'CompletedAt' => now(),
            'Score' => $finalScore,
            'TotalCorrect' => $correctCount,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Submission forcefully submitted and evaluated successfully.',
            'score' => $finalScore,
            'totalCorrect' => $correctCount,
        ]);
    }

    private function processUploadedPhoto($photoInput, $uploadedFile = null): ?string
    {
        if (empty($photoInput) && empty($uploadedFile)) {
            return null;
        }

        if (!empty($photoInput) && is_string($photoInput) && str_starts_with($photoInput, '/uploads/')) {
            return $photoInput;
        }

        $uploadDir = public_path('uploads/profiles');
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }

        if ($uploadedFile && $uploadedFile->isValid()) {
            $filename = time() . '_' . uniqid() . '.' . $uploadedFile->getClientOriginalExtension();
            @$uploadedFile->move($uploadDir, $filename);
            return '/uploads/profiles/' . $filename;
        }

        if (!empty($photoInput) && is_string($photoInput) && str_starts_with($photoInput, 'data:image/')) {
            $parts = explode(',', $photoInput);
            if (count($parts) === 2) {
                $data = base64_decode($parts[1]);
                $ext = 'jpg';
                if (str_contains($parts[0], 'png')) $ext = 'png';
                if (str_contains($parts[0], 'webp')) $ext = 'webp';
                $filename = time() . '_' . uniqid() . '.' . $ext;
                @file_put_contents($uploadDir . '/' . $filename, $data);
                return '/uploads/profiles/' . $filename;
            }
        }

        return null;
    }

    private function parseDurationMonths($input): int
    {
        if (empty($input)) return 1;
        if (is_numeric($input)) return (int)$input;
        $str = (string)$input;
        if (preg_match('/(\d+)\s*(ឆ្នាំ|year)/iu', $str, $m)) {
            return (int)$m[1] * 12;
        }
        if (preg_match('/(\d+)/', $str, $m)) {
            return (int)$m[1];
        }
        return 1;
    }
}

