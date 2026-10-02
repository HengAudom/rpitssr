<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Student;
use App\Models\StudentSubmission;
use App\Models\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityHardeningRtcTest extends TestCase
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

    /**
     * 1. Test Security Headers are applied and X-Powered-By is removed.
     */
    public function test_security_headers_are_applied(): void
    {
        $response = $this->get('/api/public-settings');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $this->assertFalse($response->headers->has('X-Powered-By'));
    }

    /**
     * 2. Test Admin routes reject unauthenticated requests with 401 JSON.
     */
    public function test_admin_routes_reject_unauthenticated(): void
    {
        $endpoints = [
            ['GET', '/api/admin/dashboard'],
            ['GET', '/api/admin/students'],
            ['POST', '/api/admin/students'],
            ['GET', '/api/admin/skills-groups'],
            ['GET', '/api/admin/exam-sessions'],
            ['GET', '/api/admin/tests'],
            ['GET', '/api/admin/results'],
            ['GET', '/api/admin/live-monitor'],
            ['GET', '/api/admin/schedule-days-years'],
            ['POST', '/api/admin/schedule-days-years'],
        ];

        foreach ($endpoints as [$method, $uri]) {
            $response = $this->json($method, $uri);
            $response->assertStatus(401);
            $response->assertJson(['message' => 'Unauthenticated.']);
        }
    }

    /**
     * 3. Test Student session cannot access Admin routes (403 Forbidden).
     */
    public function test_student_session_cannot_access_admin_routes(): void
    {
        $student = Student::create([
            'StudentCode' => 'SR2026001',
            'FirstName' => 'Student',
            'LastName' => 'Test',
            'Gender' => 'Male',
            'Phone' => '012345678',
        ]);

        $response = $this->actingAs($student)->getJson('/api/admin/students');
        $response->assertStatus(403);
        $response->assertJson(['message' => 'Forbidden. Admin privileges required.']);
    }

    /**
     * 4. Test Super Admin routes reject normal Admin.
     */
    public function test_super_admin_routes_reject_normal_admin(): void
    {
        $admin = Admin::create([
            'Username' => 'normal_admin',
            'Password' => Hash::make('password123'),
            'Role' => 'Admin',
            'Status' => 'Active',
        ]);

        $superAdminEndpoints = [
            '/api/admin/audit-logs',
            '/api/admin/roles-permissions',
            '/api/admin/system-settings',
        ];

        foreach ($superAdminEndpoints as $endpoint) {
            $response = $this->actingAs($admin)->getJson($endpoint);
            $response->assertStatus(403);
            $response->assertJson(['message' => 'Forbidden. Super Admin privileges required.']);
        }
    }

    /**
     * 5. Test Super Admin can access super admin endpoints.
     */
    public function test_super_admin_can_access_super_admin_routes(): void
    {
        $superAdmin = Admin::create([
            'Username' => 'super_admin_user',
            'Password' => Hash::make('password123'),
            'Role' => 'Super Admin',
            'Status' => 'Active',
        ]);

        $response = $this->actingAs($superAdmin)->getJson('/api/admin/system-settings');
        $response->assertStatus(200);

        $responseLogs = $this->actingAs($superAdmin)->getJson('/api/admin/audit-logs');
        $responseLogs->assertStatus(200);
    }

    /**
     * 6. Test checkIdentifier does NOT leak user enumeration (only requiresPassword based on identifier format).
     */
    public function test_check_identifier_anti_enumeration(): void
    {
        Admin::create([
            'Username' => 'myadmin',
            'Password' => Hash::make('secret'),
            'Role' => 'Admin',
            'Status' => 'Active',
        ]);

        Student::create([
            'StudentCode' => 'SR2026888',
            'FirstName' => 'Candidate',
            'LastName' => 'Test',
        ]);

        // Shorter than 3 chars returns false
        $resShort = $this->postJson('/api/check-identifier', ['identifier' => 'ad']);
        $resShort->assertStatus(200);
        $resShort->assertJson(['requiresPassword' => false]);

        // Student pattern returns false without exists or role (both existing & non-existing)
        $resStudent = $this->postJson('/api/check-identifier', ['identifier' => 'SR2026888']);
        $resStudent->assertStatus(200);
        $resStudent->assertJson(['requiresPassword' => false]);
        $this->assertArrayNotHasKey('role', $resStudent->json());
        $this->assertArrayNotHasKey('exists', $resStudent->json());

        $resStudentNonExistent = $this->postJson('/api/check-identifier', ['identifier' => 'SR9999999']);
        $resStudentNonExistent->assertStatus(200);
        $resStudentNonExistent->assertJson(['requiresPassword' => false]);

        // Admin returns true without exists or role
        $resAdmin = $this->postJson('/api/check-identifier', ['identifier' => 'myadmin']);
        $resAdmin->assertStatus(200);
        $resAdmin->assertJson(['requiresPassword' => true]);
        $this->assertArrayNotHasKey('role', $resAdmin->json());
        $this->assertArrayNotHasKey('exists', $resAdmin->json());

        // Non-admin identifier returns false
        $resRandom = $this->postJson('/api/check-identifier', ['identifier' => 'random_unknown_user']);
        $resRandom->assertStatus(200);
        $resRandom->assertJson(['requiresPassword' => false]);
        $this->assertArrayNotHasKey('role', $resRandom->json());
        $this->assertArrayNotHasKey('exists', $resRandom->json());
    }

    /**
     * 6b. Test public-settings does not disclose internal exam schedules or configs (F-02).
     */
    public function test_public_settings_anti_disclosure(): void
    {
        $response = $this->getJson('/api/public-settings');
        $response->assertStatus(200);

        $json = $response->json();

        // Must not expose internal examination configs
        $this->assertArrayNotHasKey('antiCheatPause', $json['settings'] ?? []);
        $this->assertArrayNotHasKey('autosaveIntervalSeconds', $json['settings'] ?? []);
        $this->assertArrayNotHasKey('forceStrongPassword', $json['settings'] ?? []);

        // When allowRegistration is false (default), sessions must be empty
        $this->assertEmpty($json['sessions'] ?? []);
    }

    /**
     * 7. Test Student login with unexpected password rejected (Anti-bypass).
     */
    public function test_student_login_rejects_arbitrary_password(): void
    {
        Student::create([
            'StudentCode' => 'SR2026999',
            'FirstName' => 'Student',
            'LastName' => 'NoPass',
        ]);

        $res = $this->postJson('/api/login', [
            'identifier' => 'SR2026999',
            'password' => 'some_random_injected_password',
        ]);
        $res->assertStatus(422);
    }

    /**
     * 8. Test Exam endpoints require authentication and enforce student ownership.
     */
    public function test_exam_endpoints_enforce_student_ownership(): void
    {
        $studentA = Student::create([
            'StudentCode' => 'SR2026001',
            'FirstName' => 'Alice',
            'LastName' => 'A',
        ]);

        $studentB = Student::create([
            'StudentCode' => 'SR2026002',
            'FirstName' => 'Bob',
            'LastName' => 'B',
        ]);

        $test = Test::create([
            'TestName' => 'Exam Test',
            'DurationMinutes' => 60,
            'TotalMarks' => 100,
            'PassScore' => 50,
            'Status' => 'Published',
        ]);

        $submissionA = StudentSubmission::create([
            'StudentId' => $studentA->StudentId,
            'TestId' => $test->TestId,
            'StartedAt' => now(),
        ]);

        // Unauthenticated access rejected with 401
        $resUnauth = $this->getJson("/api/exam/{$submissionA->SubmissionId}/status");
        $resUnauth->assertStatus(401);

        // Student B trying to inspect Student A's submission is rejected with 403
        $resStudentB = $this->actingAs($studentB)->getJson("/api/exam/{$submissionA->SubmissionId}/status");
        $resStudentB->assertStatus(403);

        // Student A inspecting their own submission is allowed (200)
        $resStudentA = $this->actingAs($studentA)->getJson("/api/exam/{$submissionA->SubmissionId}/status");
        $resStudentA->assertStatus(200);
    }

    /**
     * 9. Test schedule days and academic years API functions properly.
     */
    public function test_schedule_days_and_years_endpoint(): void
    {
        $admin = Admin::create([
            'Username' => 'schedule_admin',
            'Password' => Hash::make('password123'),
            'Role' => 'Admin',
            'Status' => 'Active',
        ]);

        // GET schedule-days-years
        $getRes = $this->actingAs($admin)->getJson('/api/admin/schedule-days-years');
        $getRes->assertStatus(200);
        $getRes->assertJsonStructure(['examDays', 'academicYears']);

        // POST schedule-days-years
        $postRes = $this->actingAs($admin)->postJson('/api/admin/schedule-days-years', [
            'examDays' => [
                ['id' => 'day1', 'name' => 'Day 1'],
                ['id' => 'day2', 'name' => 'Day 2'],
            ],
            'academicYears' => [
                ['id' => 'y1', 'name' => '2025-2026'],
                ['id' => 'y2', 'name' => '2026-2027'],
            ],
        ]);
        $postRes->assertStatus(200);
        $postRes->assertJsonStructure(['message', 'data']);
    }
}
