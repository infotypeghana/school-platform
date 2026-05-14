<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSchoolClassRequest;
use App\Http\Requests\Admin\UpdateSchoolClassRequest;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SchoolClassController extends Controller
{
    public function index(): View
    {
        $classes = SchoolClass::with(['classTeacher', 'students' => fn ($q) => $q->where('status', 'active')])
            ->withCount(['students' => fn ($q) => $q->where('status', 'active'), 'subjects'])
            ->orderBy('name')
            ->get();

        return view('admin.classes.index', compact('classes'));
    }

    public function create(): View
    {
        $teachers = Teacher::where('status', 'active')->orderBy('first_name')->get();
        return view('admin.classes.create', compact('teachers'));
    }

    public function store(StoreSchoolClassRequest $request): RedirectResponse
    {
        $class = SchoolClass::create($request->safe()->except(['subjects']));

        // Handle subjects if provided
        if ($request->filled('subjects')) {
            foreach (array_filter(explode("\n", $request->subjects)) as $name) {
                $name = trim($name);
                if ($name) {
                    Subject::create([
                        'tenant_id'       => app('currentTenant')?->id,
                        'school_class_id' => $class->id,
                        'name'            => $name,
                        'is_core'         => false,
                    ]);
                }
            }
        }

        return redirect()->route('admin.classes')
            ->with('success', 'Class created successfully.');
    }

    public function show(SchoolClass $schoolClass): View
    {
        $schoolClass->load(['classTeacher', 'subjects', 'students' => fn ($q) => $q->where('status', 'active')->orderBy('first_name')]);
        return view('admin.classes.show', compact('schoolClass'));
    }

    public function edit(SchoolClass $schoolClass): View
    {
        $teachers = Teacher::where('status', 'active')->orderBy('first_name')->get();
        return view('admin.classes.edit', compact('schoolClass', 'teachers'));
    }

    public function update(UpdateSchoolClassRequest $request, SchoolClass $schoolClass): RedirectResponse
    {
        $data = $request->validated();

        $schoolClass->update($data);

        return redirect()->route('admin.classes')
            ->with('success', 'Class updated.');
    }

    public function destroy(SchoolClass $schoolClass): RedirectResponse
    {
        $schoolClass->delete();
        return redirect()->route('admin.classes')
            ->with('success', 'Class removed.');
    }

    /**
     * Manage subjects for a class.
     */
    public function subjects(SchoolClass $schoolClass): View
    {
        $schoolClass->load('subjects');
        return view('admin.classes.subjects', compact('schoolClass'));
    }

    public function storeSubject(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $request->validate([
            'name'    => 'required|string|max:150',
            'is_core' => 'boolean',
        ]);

        $schoolClass->subjects()->create([
            'tenant_id' => app('currentTenant')?->id,
            'name'      => $request->name,
            'is_core'   => $request->boolean('is_core'),
        ]);

        return back()->with('success', 'Subject added.');
    }

    public function destroySubject(SchoolClass $schoolClass, Subject $subject): RedirectResponse
    {
        // Guard: prevent URL manipulation deleting a subject from a different class.
        // Route model binding resolves $subject independently of $schoolClass.
        abort_if($subject->school_class_id !== $schoolClass->id, 404);

        $subject->delete();
        return back()->with('success', 'Subject removed.');
    }
}
