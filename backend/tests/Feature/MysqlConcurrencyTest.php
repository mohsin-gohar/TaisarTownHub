<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AgencyInvitation;
use App\Models\AgencyMember;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * True two-process concurrency checks for the locked invitation claim and
 * deal-number allocation. On SQLite the worker processes cannot share the
 * in-memory database, so those cases fall back to sequential assertions;
 * on MySQL 8 they run as genuinely parallel HTTP requests.
 */
class MysqlConcurrencyTest extends TestCase
{
    private bool $mysql = false;

    /** @var array<string,list<string>> rows created by this test, cleaned in tearDown */
    private array $created = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->mysql = DB::connection()->getDriverName() === 'mysql';

        // Do NOT call migrate:fresh here: dropping the schema mid-suite breaks
        // RefreshDatabase's one-time migration bookkeeping for later tests.
        // Migrate only if this test class is run entirely on its own.
        if (! \Illuminate\Support\Facades\Schema::hasTable('users')) {
            Artisan::call('migrate', ['--force' => true]);
        }
        $this->created = [];
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->created) as $table => $ids) {
            foreach ($ids as $id) {
                DB::table($table)->where('id', $id)->delete();
            }
        }
        // Rows minted by the workers (tokens, accepted memberships, deals).
        foreach ($this->created['users'] ?? [] as $userId) {
            DB::table('personal_access_tokens')
                ->where('tokenable_type', User::class)->where('tokenable_id', $userId)->delete();
            DB::table('agency_members')->where('user_id', $userId)->delete();
        }
        foreach ($this->created['properties'] ?? [] as $propertyId) {
            DB::table('transactions')->where('propertyId', $propertyId)->delete();
        }
        parent::tearDown();
    }

    private function track(string $table, string $id): void
    {
        $this->created[$table][] = $id;
    }

    private function asGuest(): void
    {
        $this->app['auth']->forgetGuards();
    }

    private function authHeaders(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'];
    }

    private function makeUser(string $email, array $attrs = []): User
    {
        return User::create(array_merge([
            'name' => 'Concurrency User',
            'email' => $email,
            'password' => Hash::make('Secret123!'),
            'role' => 'dealer',
            'status' => 'active',
        ], $attrs));
    }

    private function makeAgency(): Agency
    {
        $agency = new Agency([
            'name' => 'Concurrency Agency',
            'slug' => 'concurrency-' . Str::random(6),
            'status' => 'active',
            'verification_status' => 'verified',
        ]);
        $agency->id = 'agy_' . Str::random(10);
        $agency->save();

        return $agency;
    }

    private function runProcess(string $uri, array $payload, string $token): Process
    {
        $worker = base_path('tests/Support/concurrency_request.php');
        // Array form lets Symfony escape arguments correctly on Windows.
        $process = new Process(
            [PHP_BINARY, $worker, 'POST', $uri, json_encode($payload), $token],
            base_path(),
            null,
            null,
            120
        );
        $process->start();

        return $process;
    }

    /** @return array{0:int,1:array|null} */
    private function workerResult(Process $process): array
    {
        $process->wait();
        $output = $process->getOutput();
        $start = strpos($output, '{');
        $end = strrpos($output, '}');
        $decoded = ($start !== false && $end !== false)
            ? json_decode(substr($output, $start, $end - $start + 1), true)
            : null;
        if (!is_array($decoded)) {
            $this->fail('Concurrency worker failed: ' . $output . ' STDERR=' . $process->getErrorOutput());
        }

        return [(int) ($decoded['status'] ?? 0), $decoded['body'] ?? null];
    }

    public function test_invitation_cannot_be_claimed_twice_concurrently(): void
    {
        $ownerRole = new Role([
            'scope' => 'agency', 'key' => 'agency_owner', 'name' => 'Owner',
            'is_system' => true, 'permissions' => ['agency.view', 'agency.manage_staff'],
        ]);
        $ownerRole->id = 'role_agency_owner';
        $ownerRole->save();
        $this->track('roles', $ownerRole->id);

        $agency = $this->makeAgency();
        $this->track('agencies', $agency->id);
        $owner = $this->makeUser('owner-conc@audit.test');
        $this->track('users', (string) $owner->id);
        $member = AgencyMember::create([
            'agency_id' => $agency->id, 'user_id' => $owner->id,
            'role_id' => $ownerRole->id, 'status' => 'active',
        ]);
        $this->track('agency_members', (string) $member->id);

        $invitee = $this->makeUser('invitee-conc@audit.test');
        $this->track('users', (string) $invitee->id);
        $inviteeToken = $invitee->createToken('existing-session')->plainTextToken;
        $plain = Str::random(48);
        $invitation = AgencyInvitation::create([
            'agency_id' => $agency->id,
            'email' => $invitee->email,
            'role_id' => $ownerRole->id,
            'token_hash' => hash('sha256', $plain),
            'invited_by' => $owner->id,
            'status' => 'pending',
            'expires_at' => now()->addDay(),
        ]);
        $this->track('agency_invitations', (string) $invitation->id);

        $payload = ['token' => $plain];

        if ($this->mysql) {
            $a = $this->runProcess('/api/agency/invitations/accept', $payload, $inviteeToken);
            $b = $this->runProcess('/api/agency/invitations/accept', $payload, $inviteeToken);
            $statuses = [$this->workerResult($a)[0], $this->workerResult($b)[0]];
        } else {
            $this->asGuest();
            $first = $this->postJson('/api/agency/invitations/accept', $payload,
                $this->authHeaders($inviteeToken))->getStatusCode();
            $this->asGuest();
            $second = $this->postJson('/api/agency/invitations/accept', $payload,
                $this->authHeaders($inviteeToken))->getStatusCode();
            $statuses = [$first, $second];
        }

        sort($statuses);
        $this->assertSame([200, 422], $statuses, 'exactly one concurrent acceptance may succeed');
        $this->assertSame(1, AgencyMember::where('agency_id', $agency->id)
            ->where('user_id', $invitee->id)->count());
        $this->assertSame(1, AgencyInvitation::where('token_hash', hash('sha256', $plain))
            ->where('status', 'accepted')->count());
        $this->assertSame(2, $invitee->tokens()->count(), 'one original + one accepted-session token');
    }

    public function test_concurrent_invoice_allocations_get_unique_numbers(): void
    {
        $agency = $this->makeAgency();
        $this->track('agencies', $agency->id);

        $admins = [];
        foreach (['invoice-a-' . Str::random(8) . '@audit.test', 'invoice-b-' . Str::random(8) . '@audit.test'] as $email) {
            $admin = $this->makeUser($email, ['role' => 'super_admin']);
            $this->track('users', (string) $admin->id);
            $admins[] = $admin->createToken('invoice-session')->plainTextToken;
        }

        $payload = [
            'agency_id' => $agency->id,
            'amount_pkr' => 10000,
            'tax_pkr' => 0,
            'notes' => 'Concurrent invoice sequence probe',
        ];

        $results = [];
        if ($this->mysql) {
            $processes = [];
            foreach ($admins as $token) {
                $processes[] = $this->runProcess('/api/admin/subscription-invoices', $payload, $token);
            }
            foreach ($processes as $process) {
                $results[] = $this->workerResult($process);
            }
        } else {
            foreach ($admins as $token) {
                $this->asGuest();
                $response = $this->postJson('/api/admin/subscription-invoices', $payload, $this->authHeaders($token));
                $results[] = [$response->getStatusCode(), $response->json()];
            }
        }

        foreach ($results as [$status, $body]) {
            $this->assertSame(201, $status, 'every concurrent invoice creation must succeed');
            $this->assertNotEmpty($body['invoice']['invoice_number'] ?? null);
            if (!empty($body['invoice']['id'])) {
                $this->track('subscription_invoices', (string) $body['invoice']['id']);
            }
        }

        $numbers = array_map(
            fn (array $result) => $result[1]['invoice']['invoice_number'] ?? null,
            $results
        );
        $this->assertCount(2, array_unique($numbers), 'invoice numbers must be unique under concurrency');
    }

    public function test_concurrent_deal_allocations_get_unique_numbers(): void
    {
        $agency = $this->makeAgency();
        $this->track('agencies', $agency->id);
        $role = new Role([
            'scope' => 'agency', 'key' => 'dealer', 'name' => 'Dealer',
            'is_system' => false, 'permissions' => ['create_transactions', 'view_transactions'],
        ]);
        $role->id = 'role_' . Str::random(10);
        $role->save();
        $this->track('roles', $role->id);

        $users = [];
        foreach (['deal-a@audit.test', 'deal-b@audit.test'] as $email) {
            $user = $this->makeUser($email);
            $this->track('users', (string) $user->id);
            $member = AgencyMember::create([
                'agency_id' => $agency->id, 'user_id' => $user->id,
                'role_id' => $role->id, 'status' => 'active',
            ]);
            $this->track('agency_members', (string) $member->id);
            $users[] = [$user, $user->createToken('deal-session')->plainTextToken];
        }

        $property = new Property([
            'title' => 'Concurrency Plot', 'pricePKR' => 5000000,
            'phase' => '1', 'sector' => 'A', 'sizeSqYards' => 120,
            'type' => 'Plot', 'status' => 'Available',
            'approval_status' => 'approved', 'visibility' => 'public',
        ]);
        $property->id = 'prop_' . Str::random(10);
        $property->agency_id = $agency->id;
        $property->save();
        $this->track('properties', $property->id);

        $payload = [
            'propertyId' => $property->id,
            'propertyTitle' => 'Concurrency Plot',
            'sellerName' => 'Seller',
            'buyerName' => 'Buyer',
            'transactionType' => 'Sale',
            'agreementAmountPKR' => 4000000,
        ];

        $results = [];
        if ($this->mysql) {
            $processes = [];
            foreach ($users as [, $token]) {
                $processes[] = $this->runProcess('/api/transactions', $payload, $token);
            }
            foreach ($processes as $process) {
                $results[] = $this->workerResult($process);
            }
        } else {
            foreach ($users as [, $token]) {
                $this->asGuest();
                $response = $this->postJson('/api/transactions', $payload, $this->authHeaders($token));
                $results[] = [$response->getStatusCode(), $response->json()];
            }
        }

        foreach ($results as [$status, $body]) {
            $this->assertSame(201, $status, 'every concurrent create must succeed');
            $this->assertNotEmpty($body['dealNumber'] ?? null);
        }
        $numbers = array_column(array_column($results, 1), 'dealNumber');
        $this->assertCount(2, array_unique($numbers), 'legacy deal numbers must be unique under concurrency');

        // Exercise the agency finance endpoint too; it previously had a
        // separate allocator and failed intermittently under parallel writes.
        $financeResults = [];
        if ($this->mysql) {
            $processes = [];
            foreach ($users as [, $token]) {
                $processes[] = $this->runProcess('/api/agency/transactions', $payload, $token);
            }
            foreach ($processes as $process) {
                $financeResults[] = $this->workerResult($process);
            }
        } else {
            foreach ($users as [, $token]) {
                $this->asGuest();
                $response = $this->postJson('/api/agency/transactions', $payload, $this->authHeaders($token));
                $financeResults[] = [$response->getStatusCode(), $response->json()];
            }
        }

        foreach ($financeResults as [$status, $body]) {
            $this->assertSame(201, $status, 'every concurrent finance create must succeed');
            $this->assertNotEmpty($body['transaction']['dealNumber'] ?? null);
        }
        $financeNumbers = array_map(
            fn (array $result) => $result[1]['transaction']['dealNumber'] ?? null,
            $financeResults
        );
        $this->assertCount(2, array_unique($financeNumbers), 'finance deal numbers must be unique under concurrency');
        $this->assertSame(4, DB::table('transactions')->where('propertyId', $property->id)->count());
    }
}

