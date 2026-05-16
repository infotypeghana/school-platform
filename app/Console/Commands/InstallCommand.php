<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class InstallCommand extends Command
{
    protected $signature = 'schoolms:install {--force : Re-run even if already installed}';

    protected $description = 'Interactive SchoolMS Ghana installation wizard';

    public function handle(): int
    {
        if ($this->isInstalled() && ! $this->option('force')) {
            $this->error('SchoolMS is already installed. Use --force to re-run.');
            return 1;
        }

        $this->printBanner();

        // ── Step 1: Environment check ────────────────────────────────────────
        $this->info('Step 1/6 — Checking server requirements...');
        if (! $this->checkRequirements()) {
            return 1;
        }

        // ── Step 2: Database connection ──────────────────────────────────────
        $this->info('Step 2/6 — Verifying database connection...');
        if (! $this->checkDatabase()) {
            return 1;
        }

        // ── Step 3: Run migrations ───────────────────────────────────────────
        $this->info('Step 3/6 — Running database migrations...');
        $this->call('migrate', ['--force' => true]);

        // ── Step 4: Application key ──────────────────────────────────────────
        if (empty(config('app.key'))) {
            $this->info('Step 4/6 — Generating application key...');
            $this->call('key:generate');
        } else {
            $this->info('Step 4/6 — Application key already set. Skipping.');
        }

        // ── Step 5: Super admin account ──────────────────────────────────────
        $this->info('Step 5/6 — Creating super admin account...');
        $this->createSuperAdmin();

        // ── Step 6: Cache & optimize ─────────────────────────────────────────
        $this->info('Step 6/6 — Optimizing for production...');
        $this->call('storage:link');
        $this->call('config:cache');
        $this->call('route:cache');
        $this->call('view:cache');

        $this->markInstalled();

        $this->newLine();
        $this->line('┌─────────────────────────────────────────────────┐');
        $this->line('│  ✓  SchoolMS Ghana installed successfully!       │');
        $this->line('│                                                  │');
        $this->line('│  Next steps:                                     │');
        $this->line('│  1. Start Horizon:  php artisan horizon          │');
        $this->line('│  2. Set up cron:    * * * * * artisan schedule   │');
        $this->line('│  3. Visit:          https://' . config('app.domain') . ' │');
        $this->line('└─────────────────────────────────────────────────┘');

        return 0;
    }

    private function printBanner(): void
    {
        $this->newLine();
        $this->line('  ╔═══════════════════════════════════════════╗');
        $this->line('  ║       SchoolMS Ghana — Installer          ║');
        $this->line('  ║   Multi-Tenant School Management SaaS     ║');
        $this->line('  ╚═══════════════════════════════════════════╝');
        $this->newLine();
    }

    private function checkRequirements(): bool
    {
        $pass = true;
        $checks = [
            'PHP >= 8.3'         => PHP_VERSION_ID >= 80300,
            'BCMath extension'   => extension_loaded('bcmath'),
            'Ctype extension'    => extension_loaded('ctype'),
            'GD extension'       => extension_loaded('gd'),
            'JSON extension'     => extension_loaded('json'),
            'Mbstring extension' => extension_loaded('mbstring'),
            'OpenSSL extension'  => extension_loaded('openssl'),
            'PDO extension'      => extension_loaded('pdo'),
            'Tokenizer extension'=> extension_loaded('tokenizer'),
            'XML extension'      => extension_loaded('xml'),
            'Zip extension'      => extension_loaded('zip'),
            'storage/ writable'  => is_writable(storage_path()),
            'bootstrap/ writable'=> is_writable(base_path('bootstrap/cache')),
        ];

        foreach ($checks as $label => $result) {
            if ($result) {
                $this->line("  <fg=green>✓</> {$label}");
            } else {
                $this->line("  <fg=red>✗</> {$label}");
                $pass = false;
            }
        }

        if (! $pass) {
            $this->error('Some requirements are not met. Please fix the above issues and try again.');
        }

        return $pass;
    }

    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();
            $this->line('  <fg=green>✓</> Database connection successful');
            return true;
        } catch (\Exception $e) {
            $this->error('Cannot connect to database: ' . $e->getMessage());
            $this->line('  Check DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD in your .env file.');
            return false;
        }
    }

    private function createSuperAdmin(): void
    {
        $email    = env('SUPER_ADMIN_EMAIL') ?: $this->ask('Super admin email', 'superadmin@' . config('app.domain'));
        $password = env('SUPER_ADMIN_PASSWORD') ?: $this->secret('Super admin password (min 12 chars)');

        while (strlen($password) < 12) {
            $this->error('Password must be at least 12 characters.');
            $password = $this->secret('Super admin password (min 12 chars)');
        }

        \App\Models\User::updateOrCreate(
            ['email' => $email],
            [
                'name'     => 'Super Admin',
                'email'    => $email,
                'password' => Hash::make($password),
                'role'     => 'super_admin',
                'tenant_id'=> null,
            ]
        );

        $this->line("  <fg=green>✓</> Super admin account created: {$email}");
    }

    private function isInstalled(): bool
    {
        return file_exists(storage_path('.installed'));
    }

    private function markInstalled(): void
    {
        file_put_contents(storage_path('.installed'), date('Y-m-d H:i:s'));
    }
}
