<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassController extends Controller
{
    /**
     * GET /api/v1/classes
     *
     * Returns all classes with student counts and class teacher.
     */
    public function index(Request $request): JsonResponse
    {
        $classes = SchoolClass::withCount(['students' => fn ($q) => $q->where('status', 'active')])
            ->with('classTeacher:id,first_name,last_name')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $classes->map(fn ($c) => [
                'id'             => $c->id,
                'name'           => $c->name,
                'section'        => $c->section,
                'full_name'      => $c->full_name,
                'level'          => $c->level,
                'student_count'  => $c->students_count,
                'class_teacher'  => $c->classTeacher ? [
                    'id'        => $c->classTeacher->id,
                    'full_name' => $c->classTeacher->first_name . ' ' . $c->classTeacher->last_name,
                ] : null,
            ]),
        ]);
    }

    /**
     * GET /api/v1/classes/{id}
     *
     * Returns class details with active students list.
     * Uses manual resolution (not route model binding) so HasTenantScope
     * is applied after the api.tenant middleware has bound currentTenant.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $class = SchoolClass::findOrFail($id);
        $class->load([
            'classTeacher:id,first_name,last_name,email',
            'students' => fn ($q) => $q->where('status', 'active')->orderBy('last_name'),
            'subjects.teacher:id,first_name,last_name',
        ]);

        return response()->json([
            'id'        => $class->id,
            'name'      => $class->name,
            'section'   => $class->section,
            'full_name' => $class->full_name,
            'level'     => $class->level,
            'class_teacher' => $class->classTeacher ? [
                'id'        => $class->classTeacher->id,
                'full_name' => $class->classTeacher->first_name . ' ' . $class->classTeacher->last_name,
                'email'     => $class->classTeacher->email,
            ] : null,
            'students' => $class->students->map(fn ($s) => [
                'id'               => $s->id,
                'admission_number' => $s->admission_number,
                'full_name'        => $s->full_name,
                'gender'           => $s->gender,
                'photo_url'        => $s->photo ? asset('storage/' . $s->photo) : null,
            ]),
            'subjects' => $class->subjects->map(fn ($sub) => [
                'id'      => $sub->id,
                'name'    => $sub->name,
                'teacher' => $sub->teacher ? [
                    'id'        => $sub->teacher->id,
                    'full_name' => $sub->teacher->first_name . ' ' . $sub->teacher->last_name,
                ] : null,
            ]),
        ]);
    }
}
