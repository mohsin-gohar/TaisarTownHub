<?php

namespace App\Support;

use App\Models\BusinessRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shared, read-only ownership evidence resolver for the legacy
 * `business_records` store.
 *
 * The canonical owner is the `agency_id` COLUMN (written by
 * BusinessRecord::put()); older rows may also carry a payload claim. Evidence
 * is only accepted when it points at a real agency through a typed twin or a
 * stamped reference. Nothing here guesses or writes an owner: ambiguous and
 * evidence-free rows stay null so a human-approved mapping is still required.
 */
final class LegacyTenancyResolver
{
    /** Payload keys referencing a dealer/agent whose typed row carries the owner. */
    private const DEALER_REF_KEYS = [
        'dealerId', 'dealer_id', 'assignedDealerId', 'soldByDealerId', 'agentId', 'agent_id',
        // Legacy videos misuse `agencyId` for a dealer id (e.g. "deal-1").
        'agencyId', 'agency_id',
    ];

    /** Payload keys referencing a property whose typed row carries the owner. */
    private const PROPERTY_REF_KEYS = ['propertyId', 'property_id', 'propertyID'];

    /** collection => [typed table, id column]. */
    private const TYPED_TWINS = [
        'properties' => ['properties', 'id'],
        'dealers' => ['dealers', 'id'],
        'inquiries' => ['inquiries', 'id'],
        'transactions' => ['transactions', 'id'],
        'projects' => ['projects', 'id'],
        'videos' => ['agency_videos', 'id'],
        'media' => ['media_files', 'id'],
    ];

    /** @var array<string,string|null> resolved owner column per typed table */
    private array $typedOwnerColumns = [];

    /** @var array<string,true>|null */
    private ?array $agencyIds = null;

    /** @var array<string,array<int,string>>|null */
    private ?array $agencyByName = null;

    /** @var array<string,string>|null */
    private ?array $dealerAgencies = null;

    /** @var array<string,string>|null */
    private ?array $propertyAgencies = null;

    /** @var array<string,string>|null */
    private ?array $inquiryAgencies = null;

    /** @var array<string,string>|null */
    private ?array $transactionAgencies = null;

    /**
     * Canonical owner of the row: the agency_id column first, then a payload
     * claim that actually names an existing agency.
     */
    public function claimedOwner(BusinessRecord $row): ?string
    {
        // A populated canonical column is authoritative. If it points to
        // a missing agency, do not silently fall back to a payload claim.
        if ($row->agency_id !== null && $row->agency_id !== '') {
            return $this->isAgency((string) $row->agency_id)
                ? (string) $row->agency_id
                : null;
        }

        $payload = is_array($row->payload) ? $row->payload : [];
        $claimed = $payload['agencyId'] ?? $payload['agency_id'] ?? null;

        return ($claimed && $this->isAgency((string) $claimed)) ? (string) $claimed : null;
    }

    /** A payload claim that is present but is NOT a valid agency id. */
    public function bogusClaim(BusinessRecord $row): ?string
    {
        if ($this->claimedOwner($row) !== null) {
            return null;
        }

        // A non-null canonical column that points at no agency is an orphaned
        // owner reference, not an unowned row that can be auto-stamped.
        if ($row->agency_id !== null && $row->agency_id !== '') {
            return (string) $row->agency_id;
        }

        $payload = is_array($row->payload) ? $row->payload : [];
        $claimed = $payload['agencyId'] ?? $payload['agency_id'] ?? null;

        return $claimed ? (string) $claimed : null;
    }

    /**
     * @return array<string,string> agency id => human-readable evidence chain
     */
    public function candidates(BusinessRecord $row): array
    {
        $payload = is_array($row->payload) ? $row->payload : [];
        $found = [];
        $add = function (?string $agency, string $evidence) use (&$found): void {
            if ($agency && $this->isAgency($agency)) {
                $found[$agency] ??= $evidence;
            }
        };

        // 1) Typed twin already stamped.
        $twin = self::TYPED_TWINS[$row->collection] ?? null;
        if ($twin) {
            [$table, $column] = $twin;
            $ownerColumn = $this->typedOwnerColumn($table);
            $typedAgency = match ($table) {
                'properties' => $this->propertyAgencies()[$row->record_id] ?? null,
                'inquiries' => $this->inquiryAgencies()[$row->record_id] ?? null,
                'transactions' => $this->transactionAgencies()[$row->record_id] ?? null,
                'dealers' => $this->dealerAgencies()[$row->record_id] ?? null,
                default => $ownerColumn
                    ? DB::table($table)->where($column, $row->record_id)->value($ownerColumn)
                    : null,
            };
            $ownerLabel = $ownerColumn ?? 'owner';
            $add($typedAgency ? (string) $typedAgency : null, "typed twin {$table}.{$row->record_id}.{$ownerLabel} = {$typedAgency}");
        }

        // 2) Dealer/agent references (including legacy videos' agencyId).
        foreach (self::DEALER_REF_KEYS as $key) {
            $ref = $payload[$key] ?? null;
            if ($ref && isset($this->dealerAgencies()[(string) $ref])) {
                $agency = $this->dealerAgencies()[(string) $ref];
                $add($agency, "payload.{$key} = {$ref} -> dealers.{$ref}.agency_id = {$agency}");
            }
        }

        // 3) Property references (files/plots point at their listing).
        foreach (self::PROPERTY_REF_KEYS as $key) {
            $ref = $payload[$key] ?? null;
            if ($ref && isset($this->propertyAgencies()[(string) $ref])) {
                $agency = $this->propertyAgencies()[(string) $ref];
                $add($agency, "payload.{$key} = {$ref} -> properties.{$ref}.agency_id = {$agency}");
            }
        }
        if (isset($this->propertyAgencies()[$row->record_id])) {
            $agency = $this->propertyAgencies()[$row->record_id];
            $add($agency, "record_id {$row->record_id} -> properties.{$row->record_id}.agency_id = {$agency}");
        }

        // 4) Parent deal for payments/commissions.
        if (in_array($row->collection, ['payments', 'commissions'], true)) {
            $parent = $payload['transactionId'] ?? $payload['transaction_id'] ?? null;
            if ($parent && isset($this->transactionAgencies()[(string) $parent])) {
                $agency = $this->transactionAgencies()[(string) $parent];
                $add($agency, "parent transaction {$parent}.agency_id = {$agency}");
            }
        }

        // 5) Exact agency name (videos carry a human-readable agencyName).
        $name = $payload['agencyName'] ?? $payload['agency_name'] ?? null;
        if ($name) {
            // Agency names are not guaranteed unique. Preserve every exact
            // match so duplicate names become an explicit ambiguous result.
            foreach ($this->agencyByName()[(string) $name] ?? [] as $agency) {
                $add($agency, "payload.agencyName = {$name} -> agencies.id = {$agency}");
            }
        }

        return $found;
    }

    /** Single unambiguous agency, or null when there is no proof. */
    public function resolve(BusinessRecord $row): ?string
    {
        $candidates = $this->candidates($row);

        return count($candidates) === 1 ? array_key_first($candidates) : null;
    }

    public function isAgency(string $id): bool
    {
        return isset($this->agencyIds()[$id]);
    }

    /** @return array<string,true> */
    private function agencyIds(): array
    {
        return $this->agencyIds ??= array_fill_keys(DB::table('agencies')->pluck('id')->all(), true);
    }

    /** @return array<string,array<int,string>> */
    private function agencyByName(): array
    {
        if ($this->agencyByName === null) {
            $this->agencyByName = [];
            foreach (DB::table('agencies')->get(['id', 'name']) as $agency) {
                $this->agencyByName[(string) $agency->name][] = (string) $agency->id;
            }
        }

        return $this->agencyByName;
    }

    /** @return array<string,string> */
    private function dealerAgencies(): array
    {
        return $this->dealerAgencies ??= DB::table('dealers')->whereNotNull('agency_id')->pluck('agency_id', 'id')->all();
    }

    /** @return array<string,string> */
    private function propertyAgencies(): array
    {
        return $this->propertyAgencies ??= DB::table('properties')->whereNotNull('agency_id')->pluck('agency_id', 'id')->all();
    }

    /** @return array<string,string> */
    private function inquiryAgencies(): array
    {
        return $this->inquiryAgencies ??= DB::table('inquiries')->whereNotNull('agency_id')->pluck('agency_id', 'id')->all();
    }

    /** @return array<string,string> */
    private function transactionAgencies(): array
    {
        return $this->transactionAgencies ??= DB::table('transactions')->whereNotNull('agency_id')->pluck('agency_id', 'id')->all();
    }

    /**
     * Resolve the canonical owner column for a typed twin. Most tables use
     * `agency_id`; legacy `agency_videos` stores the owner in `agencyId`, and
     * some twins (e.g. projects) carry no agency column at all. Schema-aware
     * lookup avoids hard-coded assumptions and MySQL "unknown column" failures.
     */
    private function typedOwnerColumn(string $table): ?string
    {
        if (array_key_exists($table, $this->typedOwnerColumns)) {
            return $this->typedOwnerColumns[$table];
        }

        try {
            $columns = Schema::getColumnListing($table);
        } catch (\Throwable) {
            return $this->typedOwnerColumns[$table] = null;
        }

        foreach (['agency_id', 'agencyId'] as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $this->typedOwnerColumns[$table] = $candidate;
            }
        }

        return $this->typedOwnerColumns[$table] = null;
    }
}
