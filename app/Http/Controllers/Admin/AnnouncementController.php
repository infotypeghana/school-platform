<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\SchoolClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        $announcements = Announcement::with(['author', 'schoolClass'])
            ->when($request->audience, fn ($q) => $q->where('audience', $request->audience))
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.announcements.index', compact('announcements'));
    }

    public function create(): View
    {
        $classes      = SchoolClass::orderBy('name')->get();
        $announcement = null;
        return view('admin.announcements.form', compact('announcement', 'classes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title'           => 'required|string|max:200',
            'body'            => 'required|string',
            'audience'        => 'required|in:all,teachers,parents,class',
            'school_class_id' => 'nullable|required_if:audience,class|exists:school_classes,id',
            'is_pinned'       => 'nullable|boolean',
            'published_at'    => 'nullable|date',
            'expires_at'      => 'nullable|date|after:published_at',
        ]);

        $data['created_by']    = auth()->id();
        $data['is_pinned']     = $request->boolean('is_pinned');
        $data['published_at']  = $data['published_at'] ?? now();

        Announcement::create($data);

        return redirect()->route('admin.announcements.index')
            ->with('success', 'Announcement published.');
    }

    public function edit(Announcement $announcement): View
    {
        $classes = SchoolClass::orderBy('name')->get();
        return view('admin.announcements.form', compact('announcement', 'classes'));
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $data = $request->validate([
            'title'           => 'required|string|max:200',
            'body'            => 'required|string',
            'audience'        => 'required|in:all,teachers,parents,class',
            'school_class_id' => 'nullable|required_if:audience,class|exists:school_classes,id',
            'is_pinned'       => 'nullable|boolean',
            'published_at'    => 'nullable|date',
            'expires_at'      => 'nullable|date',
        ]);

        $data['is_pinned'] = $request->boolean('is_pinned');

        $announcement->update($data);

        return redirect()->route('admin.announcements.index')
            ->with('success', 'Announcement updated.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();
        return redirect()->route('admin.announcements.index')
            ->with('success', 'Announcement deleted.');
    }
}
