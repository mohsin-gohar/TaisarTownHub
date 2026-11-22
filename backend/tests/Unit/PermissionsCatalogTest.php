<?php

namespace Tests\Unit;

use App\Support\Permissions;
use PHPUnit\Framework\TestCase;

/**
 * The catalog is the single source of truth for permission keys. Every key a
 * role grant or alias resolution can emit must exist in CATALOG, otherwise the
 * key is granted/checkable but undiscoverable (no label, absent from allKeys(),
 * excluded from the admin "ALL" grant set) — the exact class of dangling-key
 * bug found in the 2026-11 independent audit.
 */
class PermissionsCatalogTest extends TestCase
{
    public function test_every_platform_role_grant_key_exists_in_catalog(): void
    {
        foreach (Permissions::PLATFORM_ROLE_GRANTS as $role => $keys) {
            if ($keys === null) {
                continue; // null = ALL catalog keys
            }

            foreach ($keys as $key) {
                $this->assertArrayHasKey(
                    $key,
                    Permissions::CATALOG,
                    "platform role '{$role}' grants '{$key}' which is missing from Permissions::CATALOG"
                );
            }
        }
    }

    public function test_every_agency_role_grant_key_exists_in_catalog(): void
    {
        foreach (Permissions::AGENCY_ROLE_GRANTS as $role => $keys) {
            foreach ($keys as $key) {
                $this->assertArrayHasKey(
                    $key,
                    Permissions::CATALOG,
                    "agency role '{$role}' grants '{$key}' which is missing from Permissions::CATALOG"
                );
            }
        }
    }

    public function test_every_resolved_agency_alias_exists_in_catalog(): void
    {
        foreach (Permissions::AGENCY_ROLE_GRANTS as $role => $keys) {
            foreach ($keys as $key) {
                foreach (Permissions::LEGACY_ALIASES[$key] ?? [] as $alias) {
                    $legacyKey = 'legacy.' . $alias;
                    $this->assertArrayHasKey(
                        $legacyKey,
                        Permissions::CATALOG,
                        "alias of '{$key}' for agency role '{$role}' resolves to '{$legacyKey}' which is missing from Permissions::CATALOG"
                    );
                }
            }
        }
    }

    public function test_legacy_group_keys_are_never_agency_assignable(): void
    {
        $assignable = Permissions::agencyAssignable();

        foreach (Permissions::CATALOG as $key => $meta) {
            if ($meta[1] === 'legacy') {
                $this->assertNotContains($key, $assignable, "legacy key {$key} must not be agency-assignable");
            }
        }
    }
}
