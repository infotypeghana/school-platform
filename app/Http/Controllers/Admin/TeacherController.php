<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTeacherRequest;
use App\Http\Requests\Admin\UpdateTeacherRequest;
use App\Models\Teacher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class TeacherController extends Controller
{
    public function index(Request $request): View
    {
        $teachers = Teacher::when($request->search, fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('first_name', 'like', "%{$request->search}%")
                  ->orWhere('last_name', 'like', "%{$request->search}%")
                  ->orWhere('staff_id',  'like', "%{$request->search}%");
            }))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->orderBy('first_name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.teachers.index', compact('teachers'));
    }

    public function create(): View
    {
        return view('admin.teachers.create');
    }

    public function store(StoreTeacherRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['photo']);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('teachers/photos', 'public');
        }

        Teacher::create($data);

        return redirect()->route('admin.teachers')
            ->with('success', 'Teacher added successfully.');
    }

    public function show(Teacher $teacher): View
    {
        $this->authorize('view', $teacher);
        $teacher->load(['schoolClasses.students', 'subjects.schoolClass']);
        return view('admin.teachers.show', compact('teacher'));
    }

    public function edit(Teacher $teacher): View
    {
        $this->authorize('update', $teacher);
        return view('admin.teachers.edit', compact('teacher'));
    }

    public function update(UpdateTeacherRequest $request, Teacher $teacher): RedirectResponse
    {
        $this->authorize('update', $teacher);
        $data = $request->safe()->except(['photo', 'remove_photo']);

        // Remove existing photo if requested
        if ($request->boolean('remove_photo') && $teacher->photo) {
            Storage::disk('public')->delete($teacher->photo);
            $data['photo'] = null;
        }

        // Store new photo if uploaded
        if ($request->hasFile('photo')) {
            if ($teacher->photo) {
                Storage::disk('public')->delete($teacher->photo);
            }
            $data['photo'] = $request->file('photo')->store('teachers/photos', 'public');
        }

        $teacher->update($data);

        return redirect()->route('admin.teachers')
            ->with('success', 'Teacher updated.');
    }

    public function destroy(Teacher $teacher): RedirectResponse
    {
        $this->authorize('delete', $teacher);

        if ($teacher->photo) {
            Storage::disk('public')->delete($teacher->photo);
        }

        $teacher->delete();
        return redirect()->route('admin.teachers')
            ->with('success', 'Teacher removed.');
    }

    // ── Teacher Portal Management ─────────────────────────────────────────────

    /**
     * Activate portal access and set an initial password.
     */
    public function portalActivate(Request $request, Teacher $teacher): RedirectResponse
    {
        $this->authorize('update', $teacher);

        $request->validate([
            'portal_password' => 'required|string|min:8|max:100',
        ]);

        $teacher->update([
            'portal_active'   => true,
            'portal_password' => Hash::make($request->portal_password),
        ]);

        return back()->with('success', "Portal access enabled for {$teacher->full_name}. Initial password set.");
    }

    /**
     * Deactivate portal access.
     */
    public function portalDeactivate(Teacher $teacher): RedirectResponse
    {
        $this->authorize('update', $teacher);
        $teacher->update(['portal_active' => false]);
        return back()->with('success', "Portal access disabled for {$teacher->full_name}.");
    }

    /**
     * Reset portal password.
     */
    public function portalResetPassword(Request $request, Teacher $teacher): RedirectResponse
    {
        $this->authorize('update', $teacher);

        $request->validate([
            'portal_password' => 'required|string|min:8|max:100',
        ]);

        $teacher->update([
            'portal_password' => Hash::make($request->portal_password),
        ]);

        return back()->with('success', "Password reset for {$teacher->full_name}.");
    }
}
