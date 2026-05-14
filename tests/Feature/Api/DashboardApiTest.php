<?php

namespace Tests\Feature\Api;

use App\Models\Announcement;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;

/**
 * API: GET /api/v1/dashboard
 */
class DashboardApiTest extends ApiTestCase
{
    public function test_dashboard_requires_auth(): void
    {
        $this->getJson('/api/v1/dashboard')->assertUnauthorized();
    }

    public function test_dashboard_returns_stats_structure(): void
    {
        $response = $this->apiAs('getJson', '/api/v1/dashboard');

        $response->assertOk()
            ->assertJsonStructure([
                'stats' => [
                    'total_students',
                    'total_teachers',
                    'total_classes',
                    'present_today',
                    'absent_today',
                    'attendance_rate',
                ],
                'recent_announcements',
                'generated_at',
            ]);
    }

    public function test_dashboard_counts_are_tenant_scoped(): void
    {
        $class = SchoolClass::factory()->create(['tenant_id' => $this->tenant->id]);
        Student::factory()->count(3)->create(['tenant_id' => $this->tenant->id, 'school_class_id' => $class->id, 'status' => 'active']);
        Teacher::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);

        $response = $this->apiAs('getJson', '/api/v1/dashboard');

        $response->assertOk();
        $this->assertEquals(3, $response->json('stats.total_students'));
        $this->assertEquals(2, $response->json('stats.total_teachers'));
        $this->assertEquals(1, $response->json('stats.total_classes'));
    }

    public function test_dashboard_shows_recent_announcements(): void
    {
        Announcement::factory()->create([
            'tenant_id'    => $this->tenant->id,
            'title'        => 'Welcome Back',
            'published_at' => now()->subDay(),
            'expires_at'   => now()->addWeek(),
            'audience'     => 'all',
        ]);

        $response = $this->apiAs('getJson', '/api/v1/dashboard');

        $titles = collect($response->json('recent_announcements'))->pluck('title');
        $this->assertTrue($titles->contains('Welcome Back'));
    }
}
