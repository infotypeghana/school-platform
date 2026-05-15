<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStudentRequest;
use App\Http\Requests\Admin\UpdateStudentRequest;
use App\Models\SchoolClass;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function index(Request $request): View
    {
        $query = Student::with('schoolClass')
            ->when($request->search, fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('first_name', 'like', "%{$request->search}%")
                  ->orWhere('last_name',  'like', "%{$request->search}%")
                  ->orWhere('admission_number', 'like', "%{$request->search}%");
            }))
            ->when($request->class_id, fn ($q) => $q->where('school_class_id', $request->class_id))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->orderBy('first_name');

        $students = $query->paginate(25)->withQueryString();
        $classes  = SchoolClass::orderBy('name')->get();

        return view('admin.students.index', compact('students', 'classes'));
    }

    public function create(): View
    {
        $classes = SchoolClass::orderBy('name')->get();
        return view('admin.students.create', compact('classes'));
    }

    public function store(StoreStudentRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['photo']);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('students/photos', 'public');
        }

        Student::create($data);

        return redirect()->route('admin.students')
            ->with('success', 'Student added successfully.');
    }

    public function show(Student $student): View
    {
        $this->authorize('view', $student);
        $student->load([
            'schoolClass',
            'assessments.subject',
            'fees',
            'attendances',
            'promotions.fromClass',
            'promotions.toClass',
            'promotions.academicYear',
            'promotions.promotedBy',
        ]);

        $attendances = $student->attendances;
        $total       = $attendances->count();
        $attendanceSummary = [
            'present' => $attendances->where('status', 'present')->count(),
            'absent'  => $attendances->where('status', 'absent')->count(),
            'late'    => $attendances->where('status', 'late')->count(),
            'excused' => $attendances->where('status', 'excused')->count(),
            'total'   => $total,
            'rate'    => $total > 0
                ? round(($attendances->whereIn('status', ['present', 'late'])->count() / $total) * 100)
                : null,
        ];

        return view('admin.students.show', compact('student', 'attendanceSummary'));
    }

    public function edit(Student $student): View
    {
        $this->authorize('update', $student);
        $classes = SchoolClass::orderBy('name')->get();
        return view('admin.students.edit', compact('student', 'classes'));
    }

    public function update(UpdateStudentRequest $request, Student $student): RedirectResponse
    {
        $this->authorize('update', $student);
        $data = $request->safe()->except(['photo', 'remove_photo']);

        // Remove existing photo if requested
        if ($request->boolean('remove_photo') && $student->photo) {
            Storage::disk('public')->delete($student->photo);
            $data['photo'] = null;
        }

        // Store new photo if uploaded
        if ($request->hasFile('photo')) {
            if ($student->photo) {
                Storage::disk('public')->delete($student->photo);
            }
            $data['photo'] = $request->file('photo')->store('students/photos', 'public');
        }

        $student->update($data);

        return redirect()->route('admin.students')
            ->with('success', 'Student updated successfully.');
    }

    public function transcript(Student $student)
    {
        $this->authorize('view', $student);
        $student->load([
            'schoolClass',
            'assessments.subject',
            'assessments.term.academicYear',
        ]);

        $tenant = app('currentTenant');

        // Group assessments by term, ordered chronologically
        $byTerm = $student->assessments
            ->sortBy([['term.academic_year_id', 'asc'], ['term_id', 'asc'], ['subject.name', 'asc']])
            ->groupBy('term_id');

        $pdf = Pdf::loadView('admin.students.transcript', compact('student', 'byTerm', 'tenant'))
            ->setPaper('a4', 'portrait');

        $filename = 'transcript-' . str_replace(' ', '-', strtolower($student->full_name)) . '.pdf';

        return $pdf->download($filename);
    }

    public function destroy(Student $student): RedirectResponse
    {
        $this->authorize('delete', $student);

        if ($student->photo) {
            Storage::disk('public')->delete($student->photo);
        }

        $student->delete();
        return redirect()->route('admin.students')
            ->with('success', 'Student removed.');
    }
}
