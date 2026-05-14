<?php

namespace App\Http\Middleware;

use App\Models\Teacher;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTeacherPortalAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $teacherId = session('teacher_portal_id');

        if (! $teacherId) {
            return redirect()->route('teacher.portal.login')
                ->withErrors(['email' => 'Please log in to access the teacher portal.']);
        }

        $tenant  = app('currentTenant');
        $teacher = Teacher::where('id', $teacherId)
            ->where('tenant_id', $tenant?->id)
            ->where('portal_active', true)
            ->first();

        if (! $teacher) {
            session()->forget('teacher_portal_id');
            return redirect()->route('teacher.portal.login')
                ->withErrors(['email' => 'Your portal access has been revoked. Please contact the admin.']);
        }

        // Share teacher with all views
        view()->share('authTeacher', $teacher);

        return $next($request);
    }
}
