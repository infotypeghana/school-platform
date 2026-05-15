<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CurriculumStrand;
use App\Models\CurriculumSubStrand;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CurriculumController extends Controller
{
    // ── Strands ───────────────────────────────────────────────────────────────

    public function index(): View
    {
        $strands = CurriculumStrand::with(['subject', 'subStrands'])
            ->orderBy('subject_id')
            ->orderBy('order_index')
            ->get();

        $subjects = Subject::orderBy('name')->get();

        return view('admin.curriculum.index', compact('strands', 'subjects'));
    }

    public function storeStrand(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject_id'  => ['required', 'exists:subjects,id'],
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'order_index' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['tenant_id'] = app('currentTenant')?->id;

        CurriculumStrand::create($data);

        return redirect()->route('admin.curriculum.index')
            ->with('success', "Strand \"{$data['name']}\" created.");
    }

    public function updateStrand(Request $request, int $id): RedirectResponse
    {
        $strand = CurriculumStrand::findOrFail($id);

        $data = $request->validate([
            'subject_id'  => ['required', 'exists:subjects,id'],
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'order_index' => ['nullable', 'integer', 'min:0'],
        ]);

        $strand->update($data);

        return redirect()->route('admin.curriculum.index')
            ->with('success', "Strand updated.");
    }

    public function destroyStrand(int $id): RedirectResponse
    {
        $strand = CurriculumStrand::withCount('subStrands')->findOrFail($id);

        if ($strand->sub_strands_count > 0) {
            return back()->with('error', 'Delete all sub-strands under this strand first.');
        }

        $strand->delete();

        return redirect()->route('admin.curriculum.index')
            ->with('success', 'Strand deleted.');
    }

    // ── Sub-strands ───────────────────────────────────────────────────────────

    public function storeSubStrand(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'strand_id'   => ['required', 'exists:curriculum_strands,id'],
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'order_index' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['tenant_id'] = app('currentTenant')?->id;

        CurriculumSubStrand::create($data);

        return redirect()->route('admin.curriculum.index')
            ->with('success', "Sub-strand \"{$data['name']}\" created.");
    }

    public function updateSubStrand(Request $request, int $id): RedirectResponse
    {
        $sub = CurriculumSubStrand::findOrFail($id);

        $data = $request->validate([
            'strand_id'   => ['required', 'exists:curriculum_strands,id'],
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'order_index' => ['nullable', 'integer', 'min:0'],
        ]);

        $sub->update($data);

        return redirect()->route('admin.curriculum.index')
            ->with('success', 'Sub-strand updated.');
    }

    public function destroySubStrand(int $id): RedirectResponse
    {
        CurriculumSubStrand::findOrFail($id)->delete();

        return redirect()->route('admin.curriculum.index')
            ->with('success', 'Sub-strand deleted.');
    }
}
