<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use Illuminate\Http\Request;

class AcademicTermController extends Controller
{
    public function index()
    {
        return view('superadmin.academic-terms.index', [
            'terms' => AcademicTerm::with('academicYear')->orderByDesc('start_date')->paginate(20),
        ]);
    }

    public function create()
    {
        return view('superadmin.academic-terms.create', [
            'years' => AcademicYear::orderByDesc('year_label')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'academic_year_id' => 'required|exists:academic_years,id',
            'term_number'      => 'required|in:1,2,3',
            'term_name'        => 'required|string',
            'start_date'       => 'required|date',
            'end_date'         => 'required|date|after:start_date',
            'is_current'       => 'boolean',
        ]);

        $term = AcademicTerm::create($data);

        if ($request->boolean('is_current')) {
            AcademicTerm::where('id', '!=', $term->id)->update(['is_current' => false]);
            $term->update(['is_current' => true]);
        }

        return redirect()->route('superadmin.academic-terms.index')->with('success', 'Term created.');
    }

    public function show(AcademicTerm $academicTerm)
    {
        return view('superadmin.academic-terms.show', ['term' => $academicTerm->load('academicYear')]);
    }

    public function edit(AcademicTerm $academicTerm)
    {
        return view('superadmin.academic-terms.edit', [
            'term'  => $academicTerm,
            'years' => AcademicYear::all(),
        ]);
    }

    public function update(Request $request, AcademicTerm $academicTerm)
    {
        $academicTerm->update($request->validate([
            'term_name'  => 'required|string',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after:start_date',
            'is_current' => 'boolean',
        ]));

        if ($request->boolean('is_current')) {
            AcademicTerm::where('id', '!=', $academicTerm->id)->update(['is_current' => false]);
            $academicTerm->update(['is_current' => true]);
        }

        return back()->with('success', 'Term updated.');
    }

    public function destroy(AcademicTerm $academicTerm)
    {
        // Guard: refuse to delete a term that has active subscriptions linked to it.
        // Deleting such a term would leave subscriptions with a null term_id, breaking
        // lifecycle calculations and renewal flows.
        $hasSubscriptions = \App\Models\Subscription::where('term_id', $academicTerm->id)->exists();
        if ($hasSubscriptions) {
            return redirect()
                ->route('superadmin.academic-terms.index')
                ->with('error', "Cannot delete '{$academicTerm->term_name}' — it has subscriptions linked to it. Archive or reassign those subscriptions first.");
        }

        $academicTerm->delete();
        return redirect()->route('superadmin.academic-terms.index')
            ->with('success', "Term '{$academicTerm->term_name}' deleted.");
    }
}
