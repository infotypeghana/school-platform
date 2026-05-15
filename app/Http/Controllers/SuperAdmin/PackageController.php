<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PackageController extends Controller
{
    // ── Index ─────────────────────────────────────────────────────────────────

    public function index(): View
    {
        $packages = SubscriptionPackage::orderBy('sort_order')->orderBy('id')->get();
        return view('superadmin.packages.index', compact('packages'));
    }

    // ── Create / Store ────────────────────────────────────────────────────────

    public function create(): View
    {
        return view('superadmin.packages.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = Str::slug($data['name']);

        SubscriptionPackage::create($data);

        return redirect()->route('superadmin.packages')
            ->with('success', 'Package "' . $data['name'] . '" created.');
    }

    // ── Edit / Update ─────────────────────────────────────────────────────────

    public function edit(SubscriptionPackage $package): View
    {
        return view('superadmin.packages.edit', compact('package'));
    }

    public function update(Request $request, SubscriptionPackage $package): RedirectResponse
    {
        $data = $this->validated($request, $package->id);
        $data['slug'] = Str::slug($data['name']);

        $package->update($data);

        return redirect()->route('superadmin.packages')
            ->with('success', 'Package "' . $package->name . '" updated.');
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function destroy(SubscriptionPackage $package): RedirectResponse
    {
        if ($package->subscriptions()->exists()) {
            return redirect()->route('superadmin.packages')
                ->with('error', 'Cannot delete "' . $package->name . '" — it has existing subscriptions attached.');
        }

        $name = $package->name;
        $package->delete();

        return redirect()->route('superadmin.packages')
            ->with('success', 'Package "' . $name . '" deleted.');
    }

    // ── Shared validation ─────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $nameUnique = 'unique:subscription_packages,name' . ($ignoreId ? ",{$ignoreId}" : '');

        $data = $request->validate([
            'name'              => ['required', 'string', 'max:80', $nameUnique],
            'description'       => ['nullable', 'string', 'max:500'],
            'price_per_student' => ['required', 'numeric', 'min:0.01', 'max:9999'],
            'min_students'      => ['required', 'integer', 'min:1', 'max:9999'],
            'billing_cycle'     => ['required', 'in:term,annual'],
            'features'          => ['nullable', 'string'],   // newline-separated list from textarea
            'is_active'         => ['nullable', 'boolean'],
            'sort_order'        => ['required', 'integer', 'min:0', 'max:999'],
        ]);

        // Convert the textarea newlines into a clean JSON array
        if (! empty($data['features'])) {
            $data['features'] = array_values(array_filter(
                array_map('trim', explode("\n", (string) $data['features']))
            ));
        } else {
            $data['features'] = null;
        }

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
