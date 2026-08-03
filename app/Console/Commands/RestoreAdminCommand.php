<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantRoleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class RestoreAdminCommand extends Command
{
    protected $signature = 'ai:restore-admin';

    protected $description = 'Safely create or restore the default administrator account (admin@example.com / password).';

    public function handle(): int
    {
        $this->info('Restoring default administrator account (admin@example.com)...');

        /** @var User|null $user */
        $user = User::withTrashed()->where('email', 'admin@example.com')->first();

        if ($user && $user->trashed()) {
            $user->restore();
            $this->info('Restored soft-deleted administrator account.');
        }

        if (! $user) {
            $user = User::forceCreate([
                'name' => 'Administrator',
                'email' => 'admin@example.com',
                'password' => 'password',
                'is_admin' => true,
                'email_verified_at' => now(),
            ]);
            $this->info('Created default administrator user.');
        } else {
            $user->forceFill([
                'name' => $user->name ?: 'Administrator',
                'password' => 'password',
                'is_admin' => true,
                'email_verified_at' => $user->email_verified_at ?: now(),
            ])->save();
            $this->info('Updated password and admin status for admin@example.com.');
        }

        // Ensure at least one default tenant exists and attach admin
        $tenant = Tenant::first();
        if (! $tenant) {
            $tenant = Tenant::create([
                'name' => 'Demo Company',
                'slug' => 'demo-company',
                'is_active' => true,
            ]);
            $this->info('Created default tenant (Demo Company).');
        }

        if (! $user->belongsToTenant($tenant)) {
            $tenant->addUser($user, 'owner');
            $this->info("Assigned administrator to tenant: {$tenant->name}");
        }

        $roleService = new TenantRoleService;
        $roleService->setupDefaultRoles($tenant);
        $roleService->assignRole($user, 'admin', $tenant);

        $this->newLine();
        $this->info('====================================================');
        $this->info('   ADMINISTRATOR ACCOUNT RESTORED SUCCESSFULLY      ');
        $this->info('====================================================');
        $this->info('  Email:    admin@example.com');
        $this->info('  Password: password');
        $this->info('  Is Admin: YES');
        $this->info('  Tenant:   '.$tenant->name);
        $this->info('====================================================');
        $this->newLine();

        return self::SUCCESS;
    }
}
