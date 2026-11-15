<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\EnterpriseTestCase;

/**
 * Agency overhead expenses — server-side tenant scoping, permission gates,
 * date validation and soft-delete retention for audit.
 */
class ExpensesTest extends EnterpriseTestCase
{
    public function test_expense_lifecycle_with_permission_gates(): void
    {
        [$accountant, $agency] = $this->agencyUser(['finance.view', 'finance.manage_payments'], 'accountant');

        // Creating requires finance.manage_payments.
        $created = $this->postJson('/api/agency/expenses', [
            'expense_date' => now()->toDateString(),
            'category' => 'office',
            'amount_pkr' => 12500,
            'description' => 'Monthly internet bill',
            'payment_method' => 'bank_transfer',
            'reference' => 'CHQ-88231',
        ], $this->authHeaders($this->tokenFor($accountant)))
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('expense.amount_pkr', 12500)
            ->assertJsonPath('expense.category', 'office');

        $expenseId = $created->json('expense.id');

        // A viewer (finance.view only) can list but not create or delete.
        [$viewer] = $this->agencyUser(['finance.view'], 'viewer', $agency);
        $list = $this->getJson('/api/agency/expenses', $this->authHeaders($this->tokenFor($viewer)))
            ->assertOk()
            ->assertJsonPath('totals.amount_pkr', 12500);
        $this->assertSame(1, count($list->json('expenses')));

        $this->postJson('/api/agency/expenses', [
            'expense_date' => now()->toDateString(),
            'amount_pkr' => 100,
        ], $this->authHeaders($this->tokenFor($viewer)))->assertStatus(403);

        $this->deleteJson('/api/agency/expenses/' . $expenseId, [], $this->authHeaders($this->tokenFor($viewer)))
            ->assertStatus(403);

        // A pure payment-clerk without finance.view cannot even list.
        [$clerk] = $this->agencyUser(['finance.manage_payments'], 'accountant', $agency);
        $this->getJson('/api/agency/expenses', $this->authHeaders($this->tokenFor($clerk)))
            ->assertStatus(403);

        // Creator deletes → soft delete (row survives for audit).
        $this->deleteJson('/api/agency/expenses/' . $expenseId, [], $this->authHeaders($this->tokenFor($accountant)))
            ->assertOk();
        $row = DB::table('expenses')->where('id', $expenseId)->first();
        $this->assertNotNull($row->deleted_at, 'expense rows are soft-deleted, never hard-destroyed');

        // …and the list no longer shows it.
        $this->getJson('/api/agency/expenses', $this->authHeaders($this->tokenFor($accountant)))
            ->assertOk()
            ->assertJsonPath('totals.amount_pkr', 0);
    }

    public function test_future_dates_and_invalid_categories_are_rejected(): void
    {
        [$user] = $this->agencyUser(['finance.view', 'finance.manage_payments'], 'accountant');
        $headers = $this->authHeaders($this->tokenFor($user));

        $this->postJson('/api/agency/expenses', [
            'expense_date' => now()->addDay()->toDateString(),
            'amount_pkr' => 500,
        ], $headers)->assertStatus(422);

        $this->postJson('/api/agency/expenses', [
            'expense_date' => now()->toDateString(),
            'category' => 'bribe',
            'amount_pkr' => 500,
        ], $headers)->assertStatus(422);

        $this->assertSame(0, DB::table('expenses')->count());
    }

    public function test_expenses_are_tenant_scoped_and_totals_sum_correctly(): void
    {
        [$userA, $agencyA] = $this->agencyUser(['finance.view', 'finance.manage_payments'], 'accountant');
        [$userB, $agencyB] = $this->agencyUser(['finance.view', 'finance.manage_payments'], 'accountant');

        $mk = function (User $user, int $amount, string $category): void {
            $this->postJson('/api/agency/expenses', [
                'expense_date' => now()->toDateString(),
                'category' => $category,
                'amount_pkr' => $amount,
            ], $this->authHeaders($this->tokenFor($user)))->assertStatus(201);
        };

        // tokenFor is inlined per request: the sanctum guard caches its user
        // within one test process, so every user switch needs a fresh resolve.
        $mk($userA, 4000, 'office');
        $mk($userA, 6000, 'marketing');
        $mk($userB, 999, 'salaries');

        // Agency A sees only its own rows and correct totals.
        $listA = $this->getJson('/api/agency/expenses', $this->authHeaders($this->tokenFor($userA)))->assertOk();
        $this->assertSame(2, count($listA->json('expenses')));
        $this->assertSame(10000, $listA->json('totals.amount_pkr'));

        // Category filter narrows rows and totals.
        $filtered = $this->getJson('/api/agency/expenses?category=marketing', $this->authHeaders($this->tokenFor($userA)))->assertOk();
        $this->assertSame(1, count($filtered->json('expenses')));
        $this->assertSame(6000, $filtered->json('totals.amount_pkr'));

        // Agency B cannot see or delete A's expense rows.
        $expenseAId = $listA->json('expenses.0.id');
        $this->getJson('/api/agency/expenses', $this->authHeaders($this->tokenFor($userB)))
            ->assertOk()
            ->assertJsonPath('totals.amount_pkr', 999);
        $this->deleteJson('/api/agency/expenses/' . $expenseAId, [], $this->authHeaders($this->tokenFor($userB)))
            ->assertStatus(404);
        $this->assertSame(2, DB::table('expenses')->where('agency_id', $agencyA->id)->whereNull('deleted_at')->count());
        $this->assertSame(1, DB::table('expenses')->where('agency_id', $agencyB->id)->whereNull('deleted_at')->count());
    }
}
