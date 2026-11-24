<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Reproducible local/demo staff accounts.
 *
 * A fresh checkout previously had no code path that created the five staff
 * accounts used by the demo guide and the Playwright role suite, so the admin
 * portal was unreachable after `migrate --seed`. This seeder only runs outside
 * production and never changes an existing account's password (so re-seeding
 * cannot silently reset a developer's own changes).
 *
 * Override the documented local passwords with DEMO_STAFF_PASSWORD only when
 * you understand that it applies to every demo account.
 */
class DemoStaffSeeder extends Seeder
{
    private const ACCOUNTS = [
        ['Super Admin', 'superadmin@taisartownhub.com', 'Super@Admin2026!', 'super_admin'],
        // Agency owners are agency-scoped members, never platform administrators.
        ['Agency Owner', 'admin@taisartownhub.com', 'Admin@Taisar2026', 'dealer'],
        ['Agency Manager', 'manager@taisartownhub.com', 'Manager@2026', 'manager'],
        ['Dealer', 'dealer@taisartownhub.com', 'Dealer@2026', 'dealer'],
        ['Operator', 'operator@taisartownhub.com', 'Operator@2026', 'operator'],
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DemoStaffSeeder must never run in production.');
        }

        $override = env('DEMO_STAFF_PASSWORD');

        foreach (self::ACCOUNTS as [$name, $email, $password, $role]) {
            $user = User::where('email', $email)->first();

            if ($user !== null) {
                // Keep the existing credential; only make sure the demo account
                // is usable (active, verified, assigned its documented role).
                $dirty = [];
                if ($user->status !== 'active') {
                    $dirty['status'] = 'active';
                }
                if ($user->role !== $role) {
                    $dirty['role'] = $role;
                }
                if ($user->email_verified_at === null) {
                    $dirty['email_verified_at'] = now();
                }
                if ($dirty !== []) {
                    $user->forceFill($dirty)->save();
                }

                continue;
            }

            $user = new User();
            $user->forceFill([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($override ?: $password),
                'role' => $role,
                'status' => 'active',
                'email_verified_at' => now(),
            ])->save();
        }
    }
}
