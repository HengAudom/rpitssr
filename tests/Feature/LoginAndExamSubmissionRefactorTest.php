<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Answer;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\Student;
use App\Models\StudentSubmission;
use App\Models\SubmissionDetail;
use App\Models\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginAndExamSubmissionRefactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $permsFile = storage_path('app/permissions.json');
        if (file_exists($permsFile)) {
            @unlink($permsFile);
        }
    }

    protected function tearDown(): void
    {
        $permsFile = storage_path('app/permissions.json');
        if (file_exists($permsFile)) {
            @unlink($permsFile);
        }
        parent::tearDown();
    }

    /**
     * Test 1: Student login with StudentCode only (No password required).
     */
    public function test_student_can_login_with_student_code_only(): void
    {
        $session = ExamSession::create([
            'SessionName' => 'Morning 1',
            'ExamDate' => now()->toDateString(),
            'StartTime' => '08:00:00',
            'EndTime' => '10:00:00',
        ]);

        $student = Student::create([
            'StudentCode' => 'RTC-TEST-0001',
            'SessionId' => $session->SessionId,
            'FirstName' => 'Student',
            'LastName' => 'Test',
            'Gender' => 'Male',
            'Phone' => '012345678',
        ]);

        $response = $this->postJson('/api/login', [
            'identifier' => 'RTC-TEST-0001',
            'password' => '',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'role' => 'Student',
            'redirect' => '/student',
        ]);
    }

    /**
     * Test 2: Admin login with Username and Password.
     */
    public function test_admin_can_login_with_username_and_password(): void
    {
        $admin = Admin::create([
            'Username' => 'admin_test_user',
            'Password' => Hash::make('secret123'),
            'Role' => 'Admin',
            'Status' => 'Active',
            'FirstName' => 'Admin',
            'LastName' => 'User',
        ]);

        $response = $this->postJson('/api/login', [
            'identifier' => 'admin_test_user',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'role' => 'Admin',
            'redirect' => '/admin/dashboard',
        ]);
    }

    /**
     * Test 3: Admin login with wrong password fails.
     */
    public function test_admin_login_with_wrong_password_fails(): void
    {
        Admin::create([
            'Username' => 'admin_test_wrong',
            'Password' => Hash::make('secret123'),
            'Role' => 'Admin',
            'Status' => 'Active',
        ]);

        $response = $this->postJson('/api/login', [
            'identifier' => 'admin_test_wrong',
            'password' => 'wrongpass',
        ]);

        $this->assertTrue(in_array($response->status(), [401, 422]));
    }

    /**
     * Test 4: Exam submission calculates and saves score.
     */
    public function test_exam_submission_calculates_and_saves_score_but_hides_from_student_response(): void
    {
        $session = ExamSession::create([
            'SessionName' => 'General Session',
            'ExamDate' => now()->toDateString(),
            'StartTime' => '08:00:00',
            'EndTime' => '10:00:00',
        ]);

        $student = Student::create([
            'StudentCode' => 'RTC-EXAM-0001',
            'SessionId' => $session->SessionId,
            'FirstName' => 'Exam',
            'LastName' => 'Taker',
            'Gender' => 'Female',
            'Phone' => '012111222',
        ]);

        $admin = Admin::create([
            'Username' => 'admin_exam_creator',
            'Password' => Hash::make('pass'),
            'Role' => 'Admin',
            'Status' => 'Active',
        ]);

        $test = Test::create([
            'SessionId' => $session->SessionId,
            'CreatedByUserId' => $admin->AdminId,
            'TestName' => 'Scholarship Entrance Exam',
            'DurationMinutes' => 30,
            'TotalMarks' => 100,
            'Status' => 'Published',
        ]);

        $q1 = Question::create(['TestId' => $test->TestId, 'QuestionText' => '2+2=?']);
        $a1Correct = Answer::create(['QuestionId' => $q1->QuestionId, 'AnswerText' => '4', 'IsCorrect' => true]);
        $a1Wrong = Answer::create(['QuestionId' => $q1->QuestionId, 'AnswerText' => '5', 'IsCorrect' => false]);

        $q2 = Question::create(['TestId' => $test->TestId, 'QuestionText' => '3+3=?']);
        $a2Correct = Answer::create(['QuestionId' => $q2->QuestionId, 'AnswerText' => '6', 'IsCorrect' => true]);
        $a2Wrong = Answer::create(['QuestionId' => $q2->QuestionId, 'AnswerText' => '7', 'IsCorrect' => false]);

        $submission = StudentSubmission::create([
            'StudentId' => $student->StudentId,
            'TestId' => $test->TestId,
            'StartedAt' => now(),
        ]);

        SubmissionDetail::create([
            'SubmissionId' => $submission->SubmissionId,
            'QuestionId' => $q1->QuestionId,
            'SelectedAnswerId' => $a1Correct->AnswerId,
            'IsCorrect' => true,
        ]);

        SubmissionDetail::create([
            'SubmissionId' => $submission->SubmissionId,
            'QuestionId' => $q2->QuestionId,
            'SelectedAnswerId' => $a2Wrong->AnswerId,
            'IsCorrect' => false,
        ]);

        $response = $this->actingAs($student)->postJson("/api/exam/{$submission->SubmissionId}/complete", [
            'interruptions' => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'submitted' => true,
            'submissionId' => $submission->SubmissionId,
        ]);

        // Score must NOT be present in JSON response to candidate
        $response->assertJsonMissing(['score' => 50]);
        $response->assertJsonMissing(['totalCorrect' => 1]);
        $response->assertJsonMissing(['accuracy' => 50]);

        // Verify that Database correctly stored Score and TotalCorrect
        $freshSubmission = StudentSubmission::find($submission->SubmissionId);
        $this->assertEquals(1, $freshSubmission->TotalCorrect);
        $this->assertEquals(50.00, (float)$freshSubmission->Score);
        $this->assertNotNull($freshSubmission->CompletedAt);
    }

    /**
     * Test 5: Admin can fetch exam results list successfully.
     */
    public function test_admin_can_fetch_results_list(): void
    {
        $admin = Admin::create([
            'Username' => 'admin_results_viewer',
            'Password' => Hash::make('pass'),
            'Role' => 'Admin',
            'Status' => 'Active',
        ]);
        $res = $this->actingAs($admin)->getJson('/api/admin/results');
        $res->assertStatus(200);
        $res->assertJsonStructure([
            'results',
            'tests',
        ]);
    }

    /**
     * Test 6: Candidate cannot submit exam if any question is left unanswered.
     */
    public function test_exam_submission_fails_if_questions_unanswered(): void
    {
        $session = ExamSession::create([
            'SessionName' => 'Logic Session',
            'ExamDate' => now()->toDateString(),
            'StartTime' => '08:00:00',
            'EndTime' => '10:00:00',
        ]);

        $student = Student::create([
            'StudentCode' => 'RTC-2026-9999',
            'FirstName' => 'Test',
            'LastName' => 'Student',
            'Phone' => '012999888',
            'SessionId' => $session->SessionId,
        ]);

        $admin = Admin::create([
            'Username' => 'admin_mandatory_test',
            'Password' => Hash::make('pass'),
            'Role' => 'Admin',
            'Status' => 'Active',
        ]);

        $test = Test::create([
            'SessionId' => $session->SessionId,
            'TestName' => 'Mandatory Questions Test',
            'DurationMinutes' => 30,
            'TotalMarks' => 100,
            'Status' => 'Published',
            'CreatedByUserId' => $admin->AdminId,
        ]);

        $q1 = Question::create(['TestId' => $test->TestId, 'QuestionText' => 'Q1']);
        $a1 = Answer::create(['QuestionId' => $q1->QuestionId, 'AnswerText' => 'A1', 'IsCorrect' => true]);

        $q2 = Question::create(['TestId' => $test->TestId, 'QuestionText' => 'Q2']);
        Answer::create(['QuestionId' => $q2->QuestionId, 'AnswerText' => 'A2', 'IsCorrect' => true]);

        $submission = StudentSubmission::create([
            'StudentId' => $student->StudentId,
            'TestId' => $test->TestId,
            'StartedAt' => now(),
        ]);

        // Only answer Q1, leave Q2 unanswered
        SubmissionDetail::create([
            'SubmissionId' => $submission->SubmissionId,
            'QuestionId' => $q1->QuestionId,
            'SelectedAnswerId' => $a1->AnswerId,
            'IsCorrect' => true,
        ]);

        // Voluntary submission without answering Q2 must fail with 422
        $res = $this->actingAs($student)->postJson("/api/exam/{$submission->SubmissionId}/complete");
        $res->assertStatus(422);
        $res->assertJson([
            'success' => false,
            'unansweredCount' => 1,
        ]);
    }

    /**
     * Test 7: Admin can fetch submission detail.
     */
    public function test_admin_can_fetch_submission_detail(): void
    {
        $session = ExamSession::create([
            'SessionName' => 'Math Session',
            'ExamDate' => now()->toDateString(),
            'StartTime' => '08:00:00',
            'EndTime' => '10:00:00',
        ]);

        $admin = Admin::create([
            'Username' => 'admin_detail_viewer',
            'Password' => Hash::make('pass'),
            'Role' => 'Admin',
            'Status' => 'Active',
        ]);
        $student = Student::create([
            'StudentCode' => 'RTC-2026-0001',
            'FirstName' => 'Dara',
            'LastName' => 'Sok',
            'Phone' => '012999888',
            'SessionId' => $session->SessionId,
        ]);

        $test = Test::create([
            'SessionId' => $session->SessionId,
            'TestName' => 'Detail Test',
            'DurationMinutes' => 45,
            'TotalMarks' => 100,
            'Status' => 'Published',
            'CreatedByUserId' => $admin->AdminId,
        ]);

        $q = Question::create(['TestId' => $test->TestId, 'QuestionText' => 'Sample Q']);
        $ans = Answer::create(['QuestionId' => $q->QuestionId, 'AnswerText' => 'Sample Ans', 'IsCorrect' => true]);

        $submission = StudentSubmission::create([
            'StudentId' => $student->StudentId,
            'TestId' => $test->TestId,
            'Score' => 100,
            'TotalCorrect' => 1,
            'StartedAt' => now()->subMinutes(10),
            'CompletedAt' => now(),
        ]);

        SubmissionDetail::create([
            'SubmissionId' => $submission->SubmissionId,
            'QuestionId' => $q->QuestionId,
            'SelectedAnswerId' => $ans->AnswerId,
            'IsCorrect' => true,
        ]);

        $response = $this->actingAs($admin)->getJson("/api/admin/results/{$submission->SubmissionId}");
        $response->assertStatus(200);
        $response->assertJson([
            'submissionId' => $submission->SubmissionId,
            'studentId' => 'RTC-2026-0001',
            'testName' => 'Detail Test',
            'totalCorrect' => 1,
            'totalQuestions' => 1,
            'incorrect' => 0,
        ]);
    }

    /**
     * Test 8: Admin can use live monitor and force submit.
     */
    public function test_admin_can_use_live_monitor_and_force_submit(): void
    {
        $session = ExamSession::create([
            'SessionName' => 'Network Session',
            'ExamDate' => now()->toDateString(),
            'StartTime' => '08:00:00',
            'EndTime' => '10:00:00',
        ]);

        $admin = Admin::create([
            'Username' => 'admin_live_monitor',
            'Password' => Hash::make('pass'),
            'Role' => 'Admin',
            'Status' => 'Active',
        ]);
        $student = Student::create([
            'StudentCode' => 'RTC-2026-LIVE1',
            'FirstName' => 'Live',
            'LastName' => 'Candidate',
            'Gender' => 'Female',
            'Phone' => '012999888',
            'SessionId' => $session->SessionId,
        ]);

        $test = Test::create([
            'SessionId' => $session->SessionId,
            'TestName' => 'Live Monitor Test',
            'CreatedByUserId' => $admin->AdminId,
            'DurationMinutes' => 30,
            'TotalMarks' => 100,
            'Status' => 'Published',
        ]);

        $q = Question::create([
            'TestId' => $test->TestId,
            'QuestionText' => 'Sample Live Q',
            'Points' => 100,
        ]);

        $ans = Answer::create([
            'QuestionId' => $q->QuestionId,
            'AnswerText' => 'Correct Live Answer',
            'IsCorrect' => true,
        ]);

        $submission = StudentSubmission::create([
            'StudentId' => $student->StudentId,
            'TestId' => $test->TestId,
            'StartedAt' => now()->subMinutes(5),
            'CompletedAt' => null,
            'Interruptions' => 1,
        ]);

        SubmissionDetail::create([
            'SubmissionId' => $submission->SubmissionId,
            'QuestionId' => $q->QuestionId,
            'SelectedAnswerId' => $ans->AnswerId,
            'IsCorrect' => false,
        ]);

        // 1. Fetch live monitor data
        $response = $this->actingAs($admin)->getJson('/api/admin/live-monitor');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'examinees',
            'activeCount',
            'tests',
        ]);
        $this->assertGreaterThanOrEqual(1, count($response->json('examinees')));

        // 2. Force submit
        $forceRes = $this->actingAs($admin)->postJson("/api/admin/live-monitor/{$submission->SubmissionId}/force-submit");
        $forceRes->assertStatus(200);
        $forceRes->assertJson([
            'success' => true,
            'score' => 100,
            'totalCorrect' => 1,
        ]);

        $this->assertNotNull($submission->fresh()->CompletedAt);
    }

    /**
     * Test 9: Admin can upload and parse Word/Text documents into question text.
     */
    public function test_admin_can_parse_uploaded_document(): void
    {
        $admin = Admin::create([
            'Username' => 'admin_doc_parser',
            'Password' => Hash::make('pass'),
            'Role' => 'Admin',
            'Status' => 'Active',
        ]);

        $sampleText = "1. What is PHP?\nA. Server side language *\nB. Client side\nC. Browser\nD. Hardware";
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('sample_exam.txt', $sampleText);

        $response = $this->actingAs($admin)->postJson('/api/admin/tests/parse-doc', [
            'file' => $file,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'fileName' => 'sample_exam.txt',
        ]);
        $this->assertStringContainsString('What is PHP?', $response->json('text'));
    }

    /**
     * Test 10: Student can record focus loss/tab switch interruptions in real time.
     */
    public function test_student_can_record_tab_switches_in_realtime(): void
    {
        $session = ExamSession::create([
            'SessionName' => 'Coding Session',
            'ExamDate' => now()->toDateString(),
            'StartTime' => '08:00:00',
            'EndTime' => '10:00:00',
        ]);

        $student = Student::create([
            'StudentCode' => 'RTC-TAB-001',
            'FirstName' => 'Focus',
            'LastName' => 'Tester',
            'Gender' => 'Male',
            'Phone' => '012999000',
            'SessionId' => $session->SessionId,
        ]);

        $admin = Admin::create([
            'Username' => 'admin_tab_switch',
            'Password' => Hash::make('pass'),
            'Role' => 'Admin',
            'Status' => 'Active',
        ]);

        $test = Test::create([
            'SessionId' => $session->SessionId,
            'CreatedByUserId' => $admin->AdminId,
            'TestName' => 'Tab Switch Test Exam',
            'DurationMinutes' => 30,
            'TotalMarks' => 100,
            'Status' => 'Published',
        ]);

        $submission = StudentSubmission::create([
            'StudentId' => $student->StudentId,
            'TestId' => $test->TestId,
            'StartedAt' => now(),
            'Interruptions' => 0,
        ]);

        // Post interruption in real time
        $res = $this->actingAs($student)->postJson('/api/exam/interruption', [
            'submissionId' => $submission->SubmissionId,
            'interruptions' => 3,
        ]);

        $res->assertStatus(200);
        $res->assertJson([
            'success' => true,
            'interruptions' => 3,
        ]);

        $this->assertEquals(3, $submission->fresh()->Interruptions);

        // Check that Live Exam Monitor immediately sees 3 interruptions
        $monitorRes = $this->actingAs($admin)->getJson('/api/admin/live-monitor');
        $monitorRes->assertStatus(200);
        $examinee = collect($monitorRes->json('examinees'))->firstWhere('submissionId', $submission->SubmissionId);
        $this->assertNotNull($examinee);
        $this->assertEquals(3, $examinee['interruptions']);
    }

    public function test_admin_can_fetch_audit_logs(): void
    {
        $admin = Admin::create([
            'Username' => 'admin_audit_tester',
            'Password' => Hash::make('pass'),
            'Role' => 'Super Admin',
            'Status' => 'Active',
        ]);

        $res = $this->actingAs($admin)->getJson('/api/admin/audit-logs');
        $res->assertStatus(200);
        $res->assertJsonStructure([
            'logs' => [
                '*' => ['id', 'date', 'user', 'role', 'action', 'module', 'target', 'status', 'details']
            ]
        ]);
    }

    public function test_admin_can_be_updated_without_gender_field(): void
    {
        $superAdmin = Admin::create([
            'Username' => 'super_root',
            'Password' => Hash::make('super123'),
            'Role' => 'Super Admin',
            'Status' => 'Active',
            'FirstName' => 'Super',
            'LastName' => 'Admin',
            'Phone' => '012345678',
        ]);

        $targetAdmin = Admin::create([
            'Username' => 'standard_adm',
            'Password' => Hash::make('admin123'),
            'Role' => 'Admin',
            'Status' => 'Active',
            'FirstName' => 'OldFirst',
            'LastName' => 'OldLast',
            'Phone' => '098765432',
        ]);

        // Also create a student with identical ID to ensure no collisions
        $session = ExamSession::create([
            'SessionName' => 'Shift A',
            'ExamDate' => now()->toDateString(),
            'StartTime' => '08:00:00',
            'EndTime' => '10:00:00',
        ]);

        $studentWithSameId = Student::create([
            'StudentCode' => 'RTC-COLLISION-1',
            'SessionId' => $session->SessionId,
            'FirstName' => 'StudentFirst',
            'LastName' => 'StudentLast',
            'Gender' => 'Female',
            'Phone' => '011223344',
        ]);

        $updatePayload = [
            'role' => 'Admin',
            'firstName' => 'NewFirst',
            'lastName' => 'NewLast',
            'username' => 'standard_adm',
            'phone' => '012345678',
        ];

        $response = $this->actingAs($superAdmin)->putJson("/api/admin/students/{$targetAdmin->AdminId}", $updatePayload);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Administrator updated successfully.',
            'student' => [
                'id' => $targetAdmin->AdminId,
                'name' => 'NewFirst NewLast',
                'username' => 'standard_adm',
                'role' => 'Admin',
            ]
        ]);

        $this->assertEquals('NewFirst', $targetAdmin->fresh()->FirstName);
        $this->assertEquals('NewLast', $targetAdmin->fresh()->LastName);
    }

    /**
     * Test 18: Admin can verify identity and reset password via forgot password flow.
     */
    public function test_admin_can_verify_identity_and_reset_password(): void
    {
        $admin = Admin::create([
            'Username' => 'reset_admin',
            'Password' => Hash::make('oldpassword'),
            'Role' => 'Admin',
            'Status' => 'Active',
            'FirstName' => 'John',
            'LastName' => 'Doe',
            'Phone' => '012999888',
        ]);

        // Step 1: verify identity
        $verifyRes = $this->postJson('/api/password/verify-identity', [
            'username' => 'reset_admin',
            'phone' => '012999888',
        ]);

        $verifyRes->assertStatus(200);
        $verifyRes->assertJson([
            'displayName' => 'John Doe',
        ]);

        // Step 2: reset password
        $resetRes = $this->postJson('/api/password/reset', [
            'username' => 'reset_admin',
            'phone' => '012999888',
            'password' => 'newpassword123',
        ]);

        $resetRes->assertStatus(200);
        $this->assertTrue(Hash::check('newpassword123', $admin->fresh()->Password));
    }

    /**
     * Test 19: Authenticated admin can change their password.
     */
    public function test_authenticated_admin_can_change_password(): void
    {
        $admin = Admin::create([
            'Username' => 'change_pwd_admin',
            'Password' => Hash::make('initialpass123'),
            'Role' => 'Admin',
            'Status' => 'Active',
            'FirstName' => 'Jane',
            'LastName' => 'Smith',
            'Phone' => '011222333',
        ]);

        $res = $this->actingAs($admin)->postJson('/api/profile/change-password', [
            'currentPassword' => 'initialpass123',
            'newPassword' => 'brandnewpass456',
            'newPassword_confirmation' => 'brandnewpass456',
        ]);

        $res->assertStatus(200);
        $res->assertJson(['message' => 'Password changed successfully.']);
        $this->assertTrue(Hash::check('brandnewpass456', $admin->fresh()->Password));
    }

    /**
     * Test 20: Admin profile does not leak student data if IDs collide.
     */
    public function test_admin_profile_does_not_leak_student_data(): void
    {
        $student = Student::create([
            'StudentCode' => 'RTC-COLLIDE-1',
            'FirstName' => 'StudentOne',
            'LastName' => 'Candidate',
            'Gender' => 'Female',
            'Phone' => '099111222',
        ]);

        $admin = Admin::create([
            'Username' => 'admin_no_leak',
            'Password' => Hash::make('secret'),
            'Role' => 'Admin',
            'Status' => 'Active',
            'FirstName' => 'Super',
            'LastName' => 'Manager',
        ]);

        $res = $this->actingAs($admin)->getJson('/api/profile');
        $res->assertStatus(200);
        $this->assertArrayNotHasKey('student', $res->json());
    }
}

