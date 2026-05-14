<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = app('currentTenant')->id;

        $query = AuditLog::with('user')
            ->where('tenant_id', $tenantId)
            ->when($request->action, fn ($q) => $q->where('action', $request->action))
            ->when($request->model,  fn ($q) => $q->where('auditable_type', 'like', "%{$request->model}%"))
            ->when($request->user,   fn ($q) => $q->where('user_name', 'like', "%{$request->user}%"))
            ->when($request->search, fn ($q) => $q->where('auditable_label', 'like', "%{$request->search}%"))
            ->orderByDesc('created_at');

        $logs    = $query->paginate(40)->withQueryString();
        $actions = ['created', 'updated', 'deleted', 'login', 'export'];
        $models  = ['Student', 'Teacher', 'Fee', 'Assessment'];

        return view('admin.audit.index', compact('logs', 'actions', 'models'));
    }

    public function show(AuditLog $auditLog): View
    {
        // Ensure this log belongs to the current tenant — prevents cross-tenant access
        abort_if($auditLog->tenant_id !== app('currentTenant')->id, 404);

        return view('admin.audit.show', compact('auditLog'));
    }
}
