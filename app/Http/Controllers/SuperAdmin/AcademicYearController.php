<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use Illuminate\Http\Request;

class AcademicYearController extends Controller
{
    public function index()
    {
        return view('superadmin.academic-years.index', [
            'years' => AcademicYear::with('terms')->latest()->get(),
        ]);
    }

    public function create()
    {
        return view('superadmin.academic-years.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate(['year_label' => 'required|string|unique:academic_years,year_label']);
        AcademicYear::create($data);
        return redirect()->route('superadmin.academic-years.index')->with('success', 'Academic year created.');
    }

    public function show(AcademicYear $academicYear)
    {
        return view('superadmin.academic-years.show', ['year' => $academicYear->load('terms')]);
    }

    public function edit(AcademicYear $academicYear)
    {
        return view('superadmin.academic-years.edit', ['year' => $academicYear]);
    }

    public function update(Request $request, AcademicYear $academicYear)
    {
        $academicYear->update($request->validate([
            'year_label' => 'required|string|unique:academic_years,year_label,' . $academicYear->id,
            'is_current' => 'boolean',
        ]));
        if ($request->boolean('is_current')) {
            AcademicYear::where('id', '!=', $academicYear->id)->update(['is_current' => false]);
            $academicYear->update(['is_current' => true]);
        }
        return back()->with('success', 'Academic year updated.');
    }

    public function destroy(AcademicYear $academicYear)
    {
        // Guard: refuse to delete a year that still has terms attached.
        // Silently deleting terms would orphan subscriptions, assessments, and fees
        // that reference those terms by FK.
        if ($academicYear->terms()->exists()) {
            return redirect()
                ->route('superadmin.academic-years.index')
                ->with('error', "Cannot delete '{$academicYear->year_label}' — it still has terms. Delete all its terms first.");
        }

        $academicYear->delete();
        return redirect()->route('superadmin.academic-years.index')
            ->with('success', "Academic year '{$academicYear->year_label}' deleted.");
    }
}
