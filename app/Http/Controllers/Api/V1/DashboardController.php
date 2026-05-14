<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * GET /api/v1/dashboard
     *
     * Returns key stats for the mobile dashboard home screen.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $today = now()->toDateString();

        $totalStudents    = Student::where('status', 'active')->count();
        $totalTeachers    = Teacher::where('status', 'active')->count();
        $totalClasses     = SchoolClass::count();

        // Today's attendance summary
        $presentToday = Attendance::whereDate('date', $today)
            ->where('status', 'present')
            ->count();

        $absentToday = Attendance::whereDate('date', $today)
            ->where('status', 'absent')
            ->count();

        // Latest 3 active announcements
        $announcements = Announcement::active()
            ->whereIn('audience', ['all', 'teachers'])
            ->latest('published_at')
            ->limit(3)
            ->get(['id', 'title', 'published_at']);

        return response()->json([
            'stats' => [
                'total_students'  => $totalStudents,
                'total_teachers'  => $totalTeachers,
                'total_classes'   => $totalClasses,
                'present_today'   => $presentToday,
                'absent_today'    => $absentToday,
                'attendance_rate' => $totalStudents > 0
                    ? round(($presentToday / $totalStudents) * 100, 1)
                    : null,
            ],
            'recent_announcements' => $announcements,
            'generated_at' => now()->toIso8601String(),
        ]);
    }
}
