<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
use App\Models\Admission;
use App\Models\Fee;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $tenant = app('currentTenant');
        $term   = AcademicTerm::current();

        // ── Cached headline stats (5 min TTL, busted on tenant change) ────────
        $cacheKey = "dashboard:{$tenant->id}:" . ($term?->id ?? 'no-term');

        $stats = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($term) {
            return [
                'totalStudents'     => Student::where('status', 'active')->count(),
                'totalTeachers'     => Teacher::where('status', 'active')->count(),
                'totalClasses'      => SchoolClass::count(),
                'feesCollected'     => $term
                    ? Fee::where('term_id', $term->id)->sum('amount_paid')
                    : Fee::sum('amount_paid'),
                'pendingAdmissions' => Admission::where('status', 'pending')->count(),
            ];
        });

        // ── Term progress (not cached — always show real-time days) ───────────
        $termProgress = null;
        $daysLeft     = null;

        if ($term) {
            $totalDays    = max(1, $term->start_date->diffInDays($term->end_date));
            $daysPassed   = (int) $term->start_date->diffInDays(now());
            $termProgress = min(100, (int) round(($daysPassed / $totalDays) * 100));
            $daysLeft     = max(0, (int) now()->diffInDays($term->end_date, false));
        }

        // ── Onboarding checklist (shown until all steps complete) ────────────
        $onboarding = null;
        if ($stats['totalClasses'] === 0 || $stats['totalStudents'] === 0 || $stats['totalTeachers'] === 0) {
            $onboarding = [
                ['label' => 'School created',      'done' => true],
                ['label' => 'Add school classes',  'done' => $stats['totalClasses'] > 0,  'route' => 'admin.classes'],
                ['label' => 'Add teachers',        'done' => $stats['totalTeachers'] > 0, 'route' => 'admin.teachers'],
                ['label' => 'Enrol first student', 'done' => $stats['totalStudents'] > 0, 'route' => 'admin.students'],
            ];
        }

        return view('admin.dashboard', [
            'term'              => $term,
            'totalStudents'     => $stats['totalStudents'],
            'totalTeachers'     => $stats['totalTeachers'],
            'totalClasses'      => $stats['totalClasses'],
            'feesCollected'     => $stats['feesCollected'],
            'pendingAdmissions' => $stats['pendingAdmissions'],
            'termProgress'      => $termProgress,
            'daysLeft'          => $daysLeft,
            'onboarding'        => $onboarding,
        ]);
    }
}
