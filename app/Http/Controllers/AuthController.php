<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Skill;
use App\Models\Student;
use App\Models\Admin;
use App\Models\ExamSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $settings = AdminController::getSystemSettings();
        $allowReg = filter_var($settings['allowRegistration'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (!$allowReg) {
            return response()->json([
                'message' => 'ការចុះឈ្មោះបង្កើតគណនីដោយខ្លួនឯងត្រូវបានបិទជាបណ្ដោះអាសន្នដោយ Administrator (Self-registration is currently disabled).'
            ], 403);
        }

        $data = $request->validate([
            'firstName' => ['required', 'string', 'max:255'],
            'lastName' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'gender' => ['required', 'string', 'max:50'],
            'sessionId' => ['nullable'],
        ], [
            'firstName.required' => 'សូមបំពេញនាមខ្លួន (First Name is required).',
            'lastName.required' => 'សូមបំពេញគោត្តនាម (Last Name is required).',
            'phone.required' => 'សូមបំពេញលេខទូរស័ព្ទ (Phone is required).',
            'gender.required' => 'សូមជ្រើសរើសភេទ (Gender is required).',
        ]);

        // Generate Random & Unique Student ID
        $studentCode = $this->generateStudentCode(date('Y'));

        $student = Student::create([
            'StudentCode' => $studentCode,
            'SessionId' => !empty($data['sessionId']) ? $data['sessionId'] : null,
            'FirstName' => $data['firstName'],
            'LastName' => $data['lastName'],
            'Gender' => $data['gender'],
            'Phone' => $data['phone'],
        ]);

        return response()->json([
            'message' => 'Registration successful. Your Student ID is ' . $studentCode . '. Please sign in to take exams.',
            'studentCode' => $studentCode,
            'student' => [
                'studentCode' => $studentCode,
                'firstName' => $student->FirstName,
                'lastName' => $student->LastName,
                'gender' => $student->Gender,
                'phone' => $student->Phone,
            ],
        ], 201);
    }

    public static function recordLoginAudit(
        ?int $userId,
        string $username,
        string $role,
        ?string $displayName,
        string $status,
        ?string $details = null,
        ?Request $request = null
    ) {
        try {
            $ip = $request ? $request->ip() : request()->ip();
            $userAgent = $request ? $request->userAgent() : request()->userAgent();

            $dir = storage_path('app');
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $file = storage_path('app/login_logs.json');
            $logs = file_exists($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];

            array_unshift($logs, [
                'id' => 'auth-' . uniqid(),
                'userId' => $userId,
                'username' => $username,
                'role' => $role,
                'displayName' => $displayName ?: $username,
                'ipAddress' => $ip,
                'userAgent' => $userAgent,
                'status' => $status,
                'details' => $details,
                'date' => now()->toDateTimeString()
            ]);

            // Keep latest 300 logs
            $logs = array_slice($logs, 0, 300);
            file_put_contents($file, json_encode($logs, JSON_PRETTY_PRINT));
        } catch (\Throwable $e) {
            \Log::warning('Could not write login_logs.json: ' . $e->getMessage());
        }
    }

    public function checkIdentifier(Request $request)
    {
        $raw = $request->input('identifier') ?? $request->input('username') ?? '';
        $identifier = trim((string)$raw);
        if (mb_strlen($identifier) < 2) {
            return response()->json([
                'status' => 'ok',
                'requiresPassword' => false,
            ]);
        }

        // Student pattern (SR, RTC, STD prefixes or pure numeric ID) does not require password.
        // Never query the database here to prevent account/username enumeration (F-01 / Pentest Remediation).
        $isStudentPattern = preg_match('/^(?:rtc|sr|std)[\-_]?\d+/i', $identifier)
            || preg_match('/^(?:rtc|sr)/i', $identifier)
            || ctype_digit($identifier);

        if ($isStudentPattern) {
            return response()->json([
                'status' => 'ok',
                'requiresPassword' => false,
            ]);
        }

        // All non-student identifiers (standard usernames) require password uniformly,
        // without leaking whether the account exists in the database.
        return response()->json([
            'status' => 'ok',
            'requiresPassword' => true,
        ]);
    }

    public function login(Request $request)
    {
        $rawIdentifier = $request->input('identifier') ?? $request->input('username');
        if (!is_string($rawIdentifier) && !is_numeric($rawIdentifier) && !is_null($rawIdentifier)) {
            return response()->json([
                'message' => 'Invalid identifier format.'
            ], 422);
        }

        $rawPassword = $request->input('password');
        if (!is_string($rawPassword) && !is_null($rawPassword)) {
            return response()->json([
                'message' => 'Invalid password format.'
            ], 422);
        }

        $identifier = trim((string)($rawIdentifier ?? ''));
        $password = $rawPassword !== null ? (string)$rawPassword : null;
        $lang = $request->input('lang') === 'en' ? 'en' : 'kh';

        if ($identifier === '') {
            return response()->json([
                'message' => $lang === 'en' ? 'Please enter Student ID or Username.' : 'សូមបញ្ចូល Student ID ឬ Username'
            ], 422);
        }

        try {
            // 1. Try Admin / Super Admin Login (strictly from tbladmin, supports aliases admin / superadmin)
            $cleanId = strtolower($identifier);
            $adminUser = Admin::whereRaw('LOWER(Username) = ?', [$cleanId])
                ->orWhere(function ($q) use ($cleanId) {
                    if ($cleanId === 'admin') {
                        $q->where('Role', 'Admin')->orWhereRaw('LOWER(Username) = ?', ['admindom']);
                    } elseif ($cleanId === 'superadmin' || $cleanId === 'super admin') {
                        $q->where('Role', 'SuperAdmin')->orWhereRaw('LOWER(Username) = ?', ['domadmin']);
                    }
                })
                ->first();

            if ($adminUser) {
                if (empty($password)) {
                    return response()->json([
                        'message' => $lang === 'en' ? 'Password is required for Admin login.' : 'សូមបញ្ចូល Password សម្រាប់គណនី Admin'
                    ], 422);
                }

                if (!Hash::check($password, $adminUser->Password)) {
                    self::recordLoginAudit(
                        userId: $adminUser->AdminId,
                        username: $identifier,
                        role: $adminUser->Role,
                        displayName: $adminUser->name,
                        status: 'Failed',
                        details: 'Invalid password attempt for admin account',
                        request: $request
                    );
                    return response()->json([
                        'message' => $lang === 'en' ? 'Invalid identifier or password.' : 'ឈ្មោះគណនី ឬពាក្យសម្ងាត់មិនត្រឹមត្រូវ'
                    ], 422);
                }

                if ($adminUser->Status !== 'Active') {
                    return response()->json([
                        'message' => $lang === 'en' ? 'Account is suspended.' : 'គណនីនេះត្រូវបានផ្អាកជាបណ្ដោះអាសន្ន'
                    ], 403);
                }

                Auth::login($adminUser);

                $displayName = trim(($adminUser->FirstName ?? '') . ' ' . ($adminUser->LastName ?? '')) ?: $adminUser->Username;

                self::recordLoginAudit(
                    userId: $adminUser->AdminId,
                    username: $adminUser->Username,
                    role: $adminUser->Role,
                    displayName: $displayName,
                    status: 'Success',
                    details: "Admin logged in from IP {$request->ip()}",
                    request: $request
                );

                return response()->json([
                    'message' => 'Login successful.',
                    'role' => $adminUser->Role,
                    'user' => [
                        'id' => $adminUser->AdminId,
                        'name' => $displayName,
                        'username' => $adminUser->Username,
                        'email' => $adminUser->Username,
                        'role' => $adminUser->Role,
                        'status' => $adminUser->Status,
                        'profile_image' => $adminUser->ProfileImage,
                    ],
                    'redirect' => '/admin/dashboard',
                ]);
            }

            // 2. Student lookup strictly by assigned StudentCode (case-insensitive)
            // Disallow matching numeric auto-increment primary keys to prevent account enumeration/takeover (Pentest Finding #1)
            $student = Student::with(['session'])
                ->whereRaw('LOWER(StudentCode) = ?', [strtolower($identifier)])
                ->first();

            if ($student) {
                // If student has a password in database
                if (!empty($student->Password)) {
                    if (empty($password) || !Hash::check($password, $student->Password)) {
                        self::recordLoginAudit(
                            userId: $student->StudentId,
                            username: $student->StudentCode,
                            role: 'Student',
                            displayName: $student->name,
                            status: 'Failed',
                            details: 'Invalid password attempt for student account',
                            request: $request
                        );
                        return response()->json([
                            'message' => $lang === 'en' ? 'Invalid identifier or password.' : 'ឈ្មោះគណនី ឬពាក្យសម្ងាត់មិនត្រឹមត្រូវ'
                        ], 422);
                    }
                } elseif (!empty($password) && trim((string)$password) !== '') {
                    // Reject unexpected password with uniform error message to prevent account enumeration (Pentest Finding #2)
                    return response()->json([
                        'message' => $lang === 'en' ? 'Invalid identifier or password.' : 'ឈ្មោះគណនី ឬពាក្យសម្ងាត់មិនត្រឹមត្រូវ'
                    ], 422);
                }

                Auth::login($student);

                $displayName = trim(($student->FirstName ?? '') . ' ' . ($student->LastName ?? '')) ?: ($student->StudentCode ?? ('Candidate #' . $student->StudentId));

                self::recordLoginAudit(
                    userId: $student->StudentId,
                    username: $student->StudentCode ?? (string)$student->StudentId,
                    role: 'Student',
                    displayName: $displayName,
                    status: 'Success',
                    details: "Candidate login with ID: " . ($student->StudentCode ?? $student->StudentId) . " from IP {$request->ip()}",
                    request: $request
                );

                return response()->json([
                    'message' => 'Login successful.',
                    'loginType' => 'student',
                    'role' => 'Student',
                    'user' => [
                        'id' => $student->StudentId,
                        'studentId' => $student->StudentCode ?? (string)$student->StudentId,
                        'name' => $displayName,
                        'role' => 'Student',
                        'status' => 'Active',
                        'profile_image' => null,
                    ],
                    'redirect' => '/student',
                ]);
            }

            // Neither admin nor student found
            return response()->json([
                'message' => $lang === 'en' ? 'Invalid identifier or password.' : 'ឈ្មោះគណនី ឬពាក្យសម្ងាត់មិនត្រឹមត្រូវ'
            ], 422);

        } catch (\Throwable $e) {
            \Log::error('Login database exception: ' . $e->getMessage());
            return response()->json([
                'message' => $lang === 'en' ? 'Database connection error. Please try again.' : 'មានបញ្ហាតភ្ជាប់មូលដ្ឋានទិន្នន័យ សូមព្យាយាមម្តងទៀត'
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            self::recordLoginAudit(
                userId: $user->id,
                username: $user->name,
                role: $user->role,
                displayName: $user->name,
                status: 'Logged Out',
                details: 'Session ended by user logout',
                request: $request
            );
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out.']);
    }

    public function profile(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($user instanceof Student) {
            $student = $user->loadMissing(['session']);
        } else {
            $student = null;
        }

        $payload = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email ?? $user->Username ?? $user->StudentCode ?? '',
                'role' => $user->role,
                'status' => $user->status,
                'profileImage' => $user->profile_image,
            ],
        ];

        if ($student) {
            $displayCode = $student->StudentCode ?: ('SR' . date('Y') . str_pad((string)$student->StudentId, 5, '0', STR_PAD_LEFT));

            $academicYear = trim((string)($student->AcademicYear ?? ''));
            if (!$academicYear && $student->session?->Years) {
                $academicYear = trim((string)$student->session->Years);
            }
            if (!$academicYear) {
                $settings = \App\Http\Controllers\AdminController::getSystemSettings();
                $academicYear = $settings['academicYear'] ?? '2026-2027';
            }

            $examDay = $student->ExamDay ?: ($student->session?->Days ?: null);

            $payload['student'] = [
                'id' => $student->StudentId,
                'studentCode' => $displayCode,
                'name' => trim($student->FirstName . ' ' . $student->LastName),
                'firstName' => $student->FirstName,
                'lastName' => $student->LastName,
                'email' => $student->StudentCode ?? '',
                'phone' => $student->Phone,
                'gender' => $student->Gender,
                'sessionId' => $student->SessionId,
                'sessionName' => $student->session?->SessionName ?? 'Unassigned Shift',
                'examDay' => $examDay,
                'academicYear' => $academicYear,
                'session' => $student->session ? [
                    'id' => $student->session->SessionId,
                    'name' => $student->session->SessionName,
                    'examDate' => $student->session->ExamDate,
                    'startTime' => $student->session->StartTime,
                    'endTime' => $student->session->EndTime,
                    'days' => $student->session->Days,
                    'years' => $student->session->Years,
                    'description' => $student->session->Description,
                ] : null,
            ];

            $completedTestIds = DB::table('tblstudentsubmission')
                ->where('StudentId', $student->StudentId)
                ->whereNotNull('CompletedAt')
                ->pluck('TestId')
                ->toArray();

            $testsQuery = DB::table('tbltest as t')
                ->leftJoin('tblexamsession as es', 't.SessionId', '=', 'es.SessionId')
                ->where('t.Status', 'Published')
                ->whereNotIn('t.TestId', $completedTestIds);

            if ($student->SessionId) {
                $testsQuery->where(function ($q) use ($student) {
                    $q->where('t.SessionId', $student->SessionId)
                      ->orWhereNull('t.SessionId');
                });
            }

            $payload['tests'] = $testsQuery
                ->select(
                    't.TestId as id',
                    't.TestName as name',
                    't.SessionId as sessionId',
                    'es.SessionName as sessionName',
                    'es.ExamDate as examDate',
                    'es.StartTime as startTime',
                    'es.EndTime as endTime',
                    'es.Days as days',
                    'es.Years as years',
                    't.ExamDay as examDay',
                    't.AcademicYear as academicYear',
                    't.DurationMinutes as durationMinutes',
                    't.TotalMarks as totalMarks',
                    't.PassScore as passScore',
                    't.QuestionLimit as questionLimit',
                    't.RandomizeQuestions as randomizeQuestions',
                    't.RandomizeAnswers as randomizeAnswers',
                    't.ScheduledAt as scheduledAt',
                    't.FinishedAt as finishedAt',
                    't.Status as status'
                )
                ->orderBy('t.TestId', 'desc')
                ->get()
                ->map(function ($t) {
                    $status = $t->status;
                    $isUpcoming = false;
                    $isFinished = false;

                    if ($t->scheduledAt) {
                        $start = \Carbon\Carbon::parse($t->scheduledAt);
                        if (now()->lessThan($start)) {
                            $isUpcoming = true;
                        }
                    }

                    $end = null;
                    if ($t->finishedAt) {
                        $end = \Carbon\Carbon::parse($t->finishedAt);
                    } elseif ($t->scheduledAt) {
                        $end = \Carbon\Carbon::parse($t->scheduledAt)->addMinutes($t->durationMinutes);
                    }

                    if ($end && now()->greaterThan($end)) {
                        $isFinished = true;
                    }

                    return [
                        'id' => $t->id,
                        'name' => $t->name,
                        'sessionId' => $t->sessionId,
                        'sessionName' => $t->sessionName ?? 'គ្រប់វេនទាំងអស់',
                        'examDate' => $t->examDate,
                        'startTime' => $t->startTime,
                        'endTime' => $t->endTime,
                        'days' => $t->days,
                        'years' => $t->years,
                        'durationMinutes' => $t->durationMinutes,
                        'totalMarks' => $t->totalMarks,
                        'passScore' => $t->passScore ?? 50,
                        'status' => $t->status,
                        'isUpcoming' => $isUpcoming,
                        'isFinished' => $isFinished,
                    ];
                })
                ->values();
        } else {
            $admin = ($user instanceof Admin) ? $user : Admin::find($user->id ?? $user->AdminId);
            if ($admin) {
                $fullName = trim($admin->FirstName . ' ' . $admin->LastName);
                if ($fullName) {
                    $payload['user']['name'] = $fullName;
                }
            }
        }

        // Restrict admin permissions and internal system settings to administrators only (Pentest Finding #3)
        if (!$student) {
            $permsFile = storage_path('app/permissions.json');
            $permissions = [];
            if (file_exists($permsFile)) {
                $permissions = json_decode(file_get_contents($permsFile), true) ?: [];
            }
            $payload['permissions'] = $permissions;
            $payload['settings'] = AdminController::getSystemSettings();
        } else {
            $payload['permissions'] = [];
            $settings = AdminController::getSystemSettings();
            $payload['settings'] = [
                'institution' => $settings['institution'] ?? 'RTC',
                'academicYear' => $settings['academicYear'] ?? '2026-2027',
            ];
        }

        return response()->json($payload);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $data = $request->validate([
            'firstName' => ['required', 'string', 'max:255'],
            'lastName' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'shift' => ['nullable', 'string', 'max:50'],
        ]);

        $student = ($user instanceof Student) ? $user : Student::find($user->StudentId ?? $user->id);

        if ($student) {
            $studentUpdate = [
                'FirstName' => $data['firstName'],
                'LastName'  => $data['lastName'],
                'Phone'     => $data['phone'],
            ];
            if (!empty($data['shift'])) {
                $studentUpdate['StudyShift'] = $data['shift'];
            }
            $student->update($studentUpdate);

            return response()->json([
                'message' => 'ព័ត៌មានផ្ទាល់ខ្លួនត្រូវបានកែប្រែដោយជោគជ័យ (Profile updated successfully).',
                'user' => [
                    'id' => $student->StudentId,
                    'name' => trim($student->FirstName . ' ' . $student->LastName),
                    'phone' => $student->Phone,
                    'shift' => $student->StudyShift,
                ]
            ]);
        } else {
            $admin = ($user instanceof Admin) ? $user : Admin::find($user->AdminId ?? $user->id);
            if ($admin) {
                $admin->update([
                    'FirstName' => $data['firstName'],
                    'LastName'  => $data['lastName'],
                    'Phone'     => $data['phone'],
                ]);
            }

            return response()->json([
                'message' => 'ព័ត៌មានផ្ទាល់ខ្លួនត្រូវបានកែប្រែដោយជោគជ័យ (Profile updated successfully).',
                'user' => [
                    'id' => $admin ? $admin->AdminId : $user->id,
                    'name' => $admin ? trim($admin->FirstName . ' ' . $admin->LastName) : $user->name,
                    'phone' => $admin ? $admin->Phone : null,
                ]
            ]);
        }
    }

    public function verifyPhone(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'phone'    => ['required', 'string'],
        ]);

        $username   = trim($data['username']);
        $phoneInput = preg_replace('/[^0-9]/', '', $data['phone']);

        if (!$phoneInput) {
            return response()->json(['message' => 'សូមបញ្ចូលលេខទូរស័ព្ទឲ្យបានត្រឹមត្រូវ (Please enter a valid phone number).'], 422);
        }

        // Find Admin or Student
        $admin = Admin::whereRaw('LOWER(Username) = ?', [strtolower($username)])->first();
        $student = $admin ? null : Student::whereRaw('LOWER(StudentCode) = ?', [strtolower($username)])->first();

        $matched = false;

        if ($student) {
            $studentPhone = preg_replace('/[^0-9]/', '', $student->Phone ?? '');
            if ($studentPhone && (str_ends_with($studentPhone, $phoneInput) || str_ends_with($phoneInput, $studentPhone))) {
                $matched = true;
            }
        }

        if ($admin) {
            $adminPhone = preg_replace('/[^0-9]/', '', $admin->Phone ?? '');
            if ($adminPhone && (str_ends_with($adminPhone, $phoneInput) || str_ends_with($phoneInput, $adminPhone))) {
                $matched = true;
            }
        }

        if (!$matched) {
            // Uniform response prevents user enumeration (Finding F & G)
            return response()->json(['message' => 'ព័ត៌មានមិនត្រឹមត្រូវ សូមពិនិត្យឈ្មោះគណនី និងលេខទូរស័ព្ទម្តងទៀត (Account or phone number does not match registered profile).'], 422);
        }

        // Generate cryptographically secure single-use reset token valid for 10 minutes
        $resetToken = bin2hex(random_bytes(32));
        try {
            \Illuminate\Support\Facades\Cache::store('database')->put('pw_reset_' . $resetToken, [
                'admin_id' => $admin ? $admin->AdminId : null,
                'student_id' => $student ? $student->StudentId : null,
            ], 600);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Cache::put('pw_reset_' . $resetToken, [
                'admin_id' => $admin ? $admin->AdminId : null,
                'student_id' => $student ? $student->StudentId : null,
            ], 600);
        }

        return response()->json([
            'message' => 'ការផ្ទៀងផ្ទាត់ជោគជ័យ! (Identity verified successfully)',
            'reset_token' => $resetToken,
        ]);
    }

    public function verifyIdentity(Request $request)
    {
        return response()->json([
            'message' => 'មុខងារកំណត់ពាក្យសម្ងាត់ឡើងវិញត្រូវបានបិទ (Password reset is disabled).'
        ], 403);
    }

    public function resetPassword(Request $request)
    {
        return response()->json([
            'message' => 'មុខងារកំណត់ពាក្យសម្ងាត់ឡើងវិញត្រូវបានបិទ (Password reset is disabled).'
        ], 403);
    }

    public function forgotPassword(Request $request)
    {
        return response()->json([
            'message' => 'មុខងារកំណត់ពាក្យសម្ងាត់ឡើងវិញត្រូវបានបិទ (Password reset is disabled).'
        ], 403);
    }
    public function uploadProfileImage(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'], // Max 5MB
        ]);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $extension = strtolower($file->getClientOriginalExtension());
            $sourcePath = $file->getRealPath();

            // Load image into GD memory buffer
            $image = null;
            switch ($extension) {
                case 'jpeg':
                case 'jpg':
                    $image = @imagecreatefromjpeg($sourcePath);
                    break;
                case 'png':
                    $image = @imagecreatefrompng($sourcePath);
                    if ($image) imagepalettetotruecolor($image);
                    break;
                case 'webp':
                    $image = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : null;
                    break;
                case 'gif':
                    $image = @imagecreatefromgif($sourcePath);
                    break;
            }

            if ($image) {
                // Resize to max 600px for optimal storage and high resolution
                $width = imagesx($image);
                $height = imagesy($image);
                $maxDim = 600;

                if ($width > $maxDim || $height > $maxDim) {
                    $ratio = $width / $height;
                    if ($ratio > 1) {
                        $newWidth = $maxDim;
                        $newHeight = (int)($maxDim / $ratio);
                    } else {
                        $newWidth = (int)($maxDim * $ratio);
                        $newHeight = $maxDim;
                    }

                    $newImage = imagecreatetruecolor($newWidth, $newHeight);
                    imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                    imagedestroy($image);
                    $image = $newImage;
                }

                ob_start();
                imagejpeg($image, null, 75);
                $imageData = ob_get_clean();
                imagedestroy($image);

                $base64Image = 'data:image/jpeg;base64,' . base64_encode($imageData);
            } else {
                $rawContent = file_get_contents($sourcePath);
                $base64Image = 'data:' . ($file->getMimeType() ?: 'image/jpeg') . ';base64,' . base64_encode($rawContent);
            }

            $user->ProfileImage = $base64Image;
            $user->save();

            return response()->json([
                'message' => 'Profile image uploaded and optimized.',
                'profileImage' => $base64Image,
            ]);
        }

        return response()->json(['message' => 'No image provided.'], 400);
    }

    public function changePassword(Request $request)
    {
        $user = $request->user();
        if (!$user)
            return response()->json(['message' => 'Unauthenticated.'], 401);

        $request->validate([
            'currentPassword' => ['required', 'string'],
            'newPassword' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $currentHashed = $user->Password ?? $user->password;
        if (!$currentHashed || !Hash::check($request->currentPassword, $currentHashed)) {
            return response()->json(['message' => 'Current password is incorrect.'], 422);
        }

        $user->Password = Hash::make($request->newPassword);
        $user->save();

        return response()->json(['message' => 'Password changed successfully.']);
    }

    private function generateStudentCode(?string $year = null): string
    {
        $yearStr = !empty($year) ? trim($year) : date('Y');
        for ($i = 0; $i < 100; $i++) {
            // Cryptographically secure 6-character random suffix (>16.7 million space per year)
            $randomHex = strtoupper(bin2hex(random_bytes(3)));
            $candidateCode = 'SR' . $yearStr . $randomHex;
            $exists = Student::where('StudentCode', $candidateCode)->exists();
            if (!$exists) {
                return $candidateCode;
            }
        }
        return 'SR' . $yearStr . strtoupper(bin2hex(random_bytes(4)));
    }

    private function processUploadedPhoto($photoInput, $uploadedFile = null): ?string
    {
        if (empty($photoInput) && empty($uploadedFile)) {
            return null;
        }

        if (!empty($photoInput) && is_string($photoInput)) {
            if (str_starts_with($photoInput, 'data:image/') || str_starts_with($photoInput, '/uploads/')) {
                return $photoInput;
            }
        }

        if ($uploadedFile && $uploadedFile->isValid()) {
            $content = @file_get_contents($uploadedFile->getRealPath());
            if ($content !== false) {
                $mime = $uploadedFile->getMimeType() ?: 'image/jpeg';
                return 'data:' . $mime . ';base64,' . base64_encode($content);
            }
        }

        return null;
    }

    private function parseDurationMonths($input): int
    {
        if (empty($input)) return 4;
        if (is_numeric($input)) return (int)$input;
        $str = (string)$input;
        if (preg_match('/(\d+)\s*(ឆ្នាំ|year)/iu', $str, $m)) {
            return (int)$m[1] * 12;
        }
        if (preg_match('/(\d+)/', $str, $m)) {
            return (int)$m[1];
        }
        return 4;
    }
}
