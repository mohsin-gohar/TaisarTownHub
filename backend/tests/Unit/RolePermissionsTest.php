<?php

namespace Tests\Unit;

use App\Support\RolePermissions;
use PHPUnit\Framework\TestCase;

class RolePermissionsTest extends TestCase
{
    public function test_admin_cannot_manage_dealers_users_payouts_or_voids(): void
    {
        $this->assertFalse(RolePermissions::allows('admin', 'manage_dealers'));
        $this->assertFalse(RolePermissions::allows('admin', 'manage_users'));
        $this->assertFalse(RolePermissions::allows('admin', 'pay_commissions'));
        $this->assertFalse(RolePermissions::allows('admin', 'void_transactions'));
        $this->assertTrue(RolePermissions::allows('admin', 'view_commissions'));
        $this->assertTrue(RolePermissions::allows('super_admin', 'void_transactions'));
    }

    public function test_operator_uploads_but_never_verifies_and_unknown_roles_are_operators(): void
    {
        $this->assertTrue(RolePermissions::allows('operator', 'upload_documents'));
        $this->assertFalse(RolePermissions::allows('operator', 'verify_documents'));
        $this->assertFalse(RolePermissions::allows('operator', 'view_transactions'));
        $this->assertSame(
            RolePermissions::forRole('operator'),
            RolePermissions::forRole('not-a-real-role')
        );
    }

    public function test_dealer_is_scoped_to_own_deals_and_commission(): void
    {
        $this->assertTrue(RolePermissions::allows('dealer', 'view_my_deals'));
        $this->assertTrue(RolePermissions::allows('dealer', 'view_my_commission'));
        $this->assertFalse(RolePermissions::allows('dealer', 'view_transactions'));
        $this->assertFalse(RolePermissions::allows('dealer', 'view_commissions'));
        $this->assertFalse(RolePermissions::allows('dealer', 'manage_dealers'));
    }
}
