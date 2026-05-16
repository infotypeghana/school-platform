<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreTenantRequest;
use App\Http\Requests\SuperAdmin\UpdateTenantRequest;
use App\Models\Tenant;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    public function __construct(private SubscriptionService $subscriptionService) {}

    public function index()
    {
        $tenants = Tenant::withCount('subscriptions')->latest()->paginate(25);
        return view('superadmin.tenants.index', compact('tenants'));
    }

    public function create()
    {
        return view('superadmin.tenants.create');
    }

    public function store(StoreTenantRequest $request)
    {
        $data = $request->validated();

        $disk     = config('filesystems.media_disk', 'public');
        $logoPath = null;
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('logos', $disk);
        }

        $tenant = Tenant::create([
            'name'    => $data['name'],
            'email'   => $data['email'],
            'phone'   => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'logo'    => $logoPath,
            'uuid'    => Str::uuid(),
            'slug'    => Str::slug($data['name']) . '-' . Str::random(4),
            'status'  => 'trial',
        ]);

        $this->subscriptionService->createTrialSubscription($tenant);

        return redirect()->route('superadmin.tenants.show', $tenant)
            ->with('success', "School {$tenant->name} created with trial subscription.");
    }

    public function show(Tenant $tenant)
    {
        $tenant->load(['subscriptions.term.academicYear', 'payments']);
        return view('superadmin.tenants.show', compact('tenant'));
    }

    public function edit(Tenant $tenant)
    {
        return view('superadmin.tenants.edit', compact('tenant'));
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant)
    {
        $data = $request->validated();

        $disk = config('filesystems.media_disk', 'public');

        // Handle logo removal
        if ($request->boolean('remove_logo') && $tenant->logo) {
            Storage::disk($disk)->delete($tenant->logo);
            $data['logo'] = null;
        }

        // Handle logo upload
        if ($request->hasFile('logo')) {
            if ($tenant->logo) {
                Storage::disk($disk)->delete($tenant->logo);
            }
            $data['logo'] = $request->file('logo')->store('logos', $disk);
        }

        $tenant->update([
            'name'    => $data['name'],
            'phone'   => $data['phone'] ?? $tenant->phone,
            'address' => $data['address'] ?? $tenant->address,
            'status'  => $data['status'],
            'logo'    => array_key_exists('logo', $data) ? $data['logo'] : $tenant->logo,
        ]);

        return back()->with('success', 'Tenant updated.');
    }

    public function destroy(Tenant $tenant)
    {
        // Delete logo from storage before removing tenant
        if ($tenant->logo) {
            Storage::disk(config('filesystems.media_disk', 'public'))->delete($tenant->logo);
        }

        $tenant->delete();
        return redirect()->route('superadmin.tenants.index')->with('success', 'Tenant removed.');
    }
}
