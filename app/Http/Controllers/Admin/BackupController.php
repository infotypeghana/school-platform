<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ExportSchoolDataJob;
use App\Models\SchoolExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    /**
     * GET /backup
     *
     * Show export history and trigger button.
     */
    public function index(): View
    {
        $tenant  = app('currentTenant');
        $exports = SchoolExport::where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('admin.backup.index', compact('exports'));
    }

    /**
     * POST /backup
     *
     * Queue a new full-school data export.
     */
    public function store(Request $request): RedirectResponse
    {
        $tenant = app('currentTenant');

        // Prevent duplicate pending/processing jobs
        $inFlight = SchoolExport::where('tenant_id', $tenant->id)
            ->whereIn('status', [SchoolExport::STATUS_PENDING, SchoolExport::STATUS_PROCESSING])
            ->exists();

        if ($inFlight) {
            return back()->with('info', 'An export is already in progress. Please wait for it to finish.');
        }

        $export = SchoolExport::create([
            'tenant_id'    => $tenant->id,
            'requested_by' => Auth::id(),
            'status'       => SchoolExport::STATUS_PENDING,
        ]);

        ExportSchoolDataJob::dispatch($export->id, $tenant->id);

        return back()->with('success', 'Export started — this may take a minute. Refresh the page to check the status.');
    }

    /**
     * GET /backup/{export}/download
     *
     * Stream the ready ZIP file to the browser.
     */
    public function download(Request $request, int $id): BinaryFileResponse|RedirectResponse
    {
        $tenant = app('currentTenant');
        $export = SchoolExport::where('tenant_id', $tenant->id)->findOrFail($id);

        if (! $export->isReady()) {
            return back()->with('error', 'Export is not ready yet.');
        }

        if ($export->isExpired()) {
            return back()->with('error', 'Export has expired. Please generate a new one.');
        }

        $path = Storage::disk('local')->path($export->file_path);

        if (! file_exists($path)) {
            return back()->with('error', 'Export file not found. Please generate a new one.');
        }

        $filename = basename($export->file_path);

        return response()->download($path, $filename, [
            'Content-Type' => 'application/zip',
        ]);
    }

    /**
     * DELETE /backup/{export}
     *
     * Delete an export record and its file.
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $tenant = app('currentTenant');
        $export = SchoolExport::where('tenant_id', $tenant->id)->findOrFail($id);

        if ($export->file_path && Storage::disk('local')->exists($export->file_path)) {
            Storage::disk('local')->delete($export->file_path);
        }

        $export->delete();

        return back()->with('success', 'Export deleted.');
    }
}
