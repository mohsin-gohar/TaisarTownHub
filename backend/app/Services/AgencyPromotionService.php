<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\AgencyPromotion;
use App\Models\PromotionPackage;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Featured-agency placement rules.
 *
 * Eligibility, slot limits, ordering and expiry are all decided here so the
 * public read API and the admin write API cannot drift apart.
 */
class AgencyPromotionService
{
    public const DEFAULT_MAX_SLOTS = 10;
    public const ABSOLUTE_MAX_SLOTS = 100;

    public function maxSlots(): int
    {
        $configured = (int) SystemSetting::get('promotions.max_slots', self::DEFAULT_MAX_SLOTS);

        return max(1, min(self::ABSOLUTE_MAX_SLOTS, $configured ?: self::DEFAULT_MAX_SLOTS));
    }

    public function defaultDurationDays(): int
    {
        $days = (int) SystemSetting::get('promotions.default_duration_days', 30);

        return max(1, min(3650, $days ?: 30));
    }

    public function settings(): array
    {
        return [
            'max_slots' => $this->maxSlots(),
            'default_duration_days' => $this->defaultDurationDays(),
            'slot_presets' => [10, 20, 30],
            'absolute_max_slots' => self::ABSOLUTE_MAX_SLOTS,
        ];
    }

    /** Public homepage roster — never includes suspended/unverified agencies. */
    public function publicFeatured(?int $limit = null): array
    {
        $limit = max(1, min($limit ?? $this->maxSlots(), $this->maxSlots()));

        $rows = AgencyPromotion::query()
            ->live()
            ->with(['agency' => function ($query) {
                $query->where('status', 'active')
                    ->where('verification_status', 'verified')
                    ->withCount(['properties as public_listings_count' => function ($properties) {
                        $properties->publiclyVisible();
                    }]);
            }])
            ->orderBy('position')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return $rows
            ->filter(fn (AgencyPromotion $promotion) => $promotion->agency !== null)
            ->values()
            ->map(fn (AgencyPromotion $promotion, int $index) => [
                'id' => $promotion->agency->id,
                'name' => $promotion->agency->name,
                'slug' => $promotion->agency->slug,
                'logo_url' => $promotion->agency->logo_url,
                'cover_url' => $promotion->agency->cover_url,
                'description' => $promotion->agency->description,
                'city' => $promotion->agency->city,
                'office_address' => $promotion->agency->office_address,
                'phone' => $promotion->agency->phone,
                'whatsapp' => $promotion->agency->whatsapp,
                'verification_status' => $promotion->agency->verification_status,
                'sale_listing_count' => (int) ($promotion->agency->public_listings_count ?? 0),
                'position' => $index + 1,
                'badge' => $promotion->publicBadge(),
                'is_paid' => $promotion->isPaid(),
                'featured_since' => $promotion->starts_at?->toIso8601String(),
                'featured_until' => $promotion->ends_at?->toIso8601String(),
            ])
            ->all();
    }

    public function stats(): array
    {
        $now = now();
        $liveCount = AgencyPromotion::query()->live()->count();
        $occupiedCount = AgencyPromotion::query()->current()->count();
        $scheduled = AgencyPromotion::query()
            ->where('status', 'active')
            ->whereNotNull('starts_at')
            ->where('starts_at', '>', $now)
            ->count();
        $expiring = AgencyPromotion::query()
            ->live()
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', $now->copy()->addDays(7))
            ->count();

        return [
            'live' => $liveCount,
            'scheduled' => $scheduled,
            'expiring_7d' => $expiring,
            'max_slots' => $this->maxSlots(),
            'available_slots' => max(0, $this->maxSlots() - $occupiedCount),
            'free_launch' => true,
            'total_placements' => AgencyPromotion::query()->count(),
        ];
    }

    /**
     * @throws \DomainException when the agency is not eligible or no slot is free
     */
    public function promote(Agency $agency, array $data, User $actor): AgencyPromotion
    {
        $packageId = !empty($data['package_id']) ? (int) $data['package_id'] : null;

        $promotion = DB::transaction(function () use ($agency, $data, $actor, $packageId): AgencyPromotion {
            // All placement writers take this same row lock before counting
            // occupied slots. This prevents two concurrent admins from both
            // seeing the final slot as available.
            $this->lockSlotMutex();

            $lockedAgency = Agency::whereKey($agency->id)->lockForUpdate()->firstOrFail();
            $this->assertEligible($lockedAgency);

            if (AgencyPromotion::query()->current()->where('agency_id', $lockedAgency->id)->exists()) {
                throw new \DomainException('This agency already has a current featured placement. Edit or cancel it first.');
            }

            $package = $packageId !== null
                ? PromotionPackage::where('is_active', true)->lockForUpdate()->findOrFail($packageId)
                : null;

            $status = $data['status'] ?? 'active';
            if (!in_array($status, ['pending', 'active'], true)) {
                throw new \DomainException('A new featured placement must be pending or active.');
            }
            if ($status === 'active') {
                $this->assertSlotAvailable();
            }

            $startsAt = !empty($data['starts_at'])
                ? Carbon::parse($data['starts_at'])
                : now();
            $duration = (int) ($data['duration_days'] ?? $package?->duration_days ?? $this->defaultDurationDays());
            $endsAt = !empty($data['ends_at'])
                ? Carbon::parse($data['ends_at'])
                : $startsAt->copy()->addDays(max(1, $duration));

            if ($endsAt->lessThanOrEqualTo($startsAt)) {
                throw new \DomainException('The promotion end date must be after its start date.');
            }

            $requestedPosition = isset($data['position']) ? (int) $data['position'] : null;

            $created = AgencyPromotion::create([
                'agency_id' => $lockedAgency->id,
                'package_id' => $package?->id,
                'status' => $status,
                'position' => 0,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'price' => 0,
                'currency' => 'PKR',
                'payment_status' => 'waived',
                'payment_method' => null,
                'payment_reference' => null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
                'approved_by' => $actor->id,
            ]);

            $this->reorderCurrent($created->id, $requestedPosition);

            return $created->fresh();
        }, 3);

        $promotion->load('agency');
        if ($promotion->agency) {
            $this->notifyOwner(
                $promotion->agency,
                'Featured placement confirmed',
                "{$promotion->agency->name} is now scheduled in the homepage featured roster (position {$promotion->position})."
            );
        }

        return $promotion->load(['agency:id,name,slug', 'package']);
    }

    public function update(AgencyPromotion $promotion, array $data, User $actor): AgencyPromotion
    {
        return DB::transaction(function () use ($promotion, $data, $actor) {
            $this->lockSlotMutex();

            // Match promote() lock order: agency row before placement row.
            $lockedAgency = Agency::whereKey($promotion->agency_id)->lockForUpdate()->firstOrFail();
            $lockedPromotion = AgencyPromotion::whereKey($promotion->id)
                ->where('agency_id', $lockedAgency->id)
                ->lockForUpdate()
                ->firstOrFail();
            $status = $data['status'] ?? $lockedPromotion->status;

            if (in_array($status, ['active', 'pending'], true)) {
                $this->assertEligible($lockedAgency);
            }
            if ($status === 'active') {
                $duplicate = AgencyPromotion::query()
                    ->current()
                    ->where('agency_id', $lockedAgency->id)
                    ->where('id', '!=', $lockedPromotion->id)
                    ->exists();
                if ($duplicate) {
                    throw new \DomainException('This agency already has a current featured placement. Edit or cancel it first.');
                }
                $this->assertSlotAvailable($lockedPromotion->id);
            }

            $requestedPosition = array_key_exists('position', $data) && $data['position'] !== null
                ? (int) $data['position']
                : null;

            if (array_key_exists('starts_at', $data)) {
                $lockedPromotion->starts_at = $data['starts_at'] ? Carbon::parse($data['starts_at']) : null;
            }
            if (array_key_exists('ends_at', $data)) {
                $lockedPromotion->ends_at = $data['ends_at'] ? Carbon::parse($data['ends_at']) : null;
            }
            if ($lockedPromotion->starts_at && $lockedPromotion->ends_at
                && $lockedPromotion->ends_at->lessThanOrEqualTo($lockedPromotion->starts_at)) {
                throw new \DomainException('The promotion end date must be after its start date.');
            }

            // Free launch: billing metadata remains non-writable until the
            // billing release and no placement is presented as a paid advert.
            $lockedPromotion->price = 0;
            $lockedPromotion->currency = 'PKR';
            $lockedPromotion->payment_status = 'waived';
            $lockedPromotion->payment_method = null;
            $lockedPromotion->payment_reference = null;
            if (array_key_exists('notes', $data)) {
                $lockedPromotion->notes = $data['notes'];
            }

            $lockedPromotion->status = $status;
            $lockedPromotion->approved_by = $actor->id;
            $lockedPromotion->cancelled_at = $status === 'cancelled' ? now() : null;
            $lockedPromotion->save();

            $this->reorderCurrent($lockedPromotion->id, $requestedPosition);

            return $lockedPromotion->fresh()->load(['agency:id,name,slug', 'package']);
        }, 3);
    }

    public function cancel(AgencyPromotion $promotion, User $actor, ?string $reason = null): AgencyPromotion
    {
        return $this->update($promotion, [
            'status' => 'cancelled',
            'notes' => $reason ?: $promotion->notes,
        ], $actor);
    }

    /** @param array<int, string|int> $orderedIds */
    public function reorder(array $orderedIds, User $actor): void
    {
        $ids = array_values(array_map('intval', $orderedIds));
        if (count($ids) !== count(array_unique($ids))) {
            throw new \DomainException('Duplicate placements in the requested order.');
        }

        DB::transaction(function () use ($ids) {
            $this->lockSlotMutex();

            if (count($ids) > $this->maxSlots()) {
                throw new \DomainException('The requested order exceeds the configured featured-slot limit.');
            }

            $rows = AgencyPromotion::query()
                ->current()
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $currentIds = $rows->keys()->map(fn ($id) => (int) $id)->all();

            if (count($currentIds) !== count($ids)
                || array_diff($currentIds, $ids) !== []
                || array_diff($ids, $currentIds) !== []) {
                throw new \DomainException('The order must include every current placement exactly once. Refresh and try again.');
            }

            foreach ($ids as $index => $id) {
                AgencyPromotion::whereKey($id)->update(['position' => $index + 1]);
            }
        }, 3);

        Log::info('Featured placement order updated', ['ids' => $ids, 'by' => $actor->id]);
    }

    public function expireDue(): int
    {
        $expired = DB::transaction(function () {
            $this->lockSlotMutex();

            $due = AgencyPromotion::query()
                ->expiredDue()
                ->lockForUpdate()
                ->with('agency')
                ->get();

            foreach ($due as $promotion) {
                $promotion->status = 'expired';
                $promotion->save();
            }

            $this->reorderCurrent();

            return $due;
        }, 3);

        foreach ($expired as $promotion) {
            if ($promotion->agency) {
                $this->notifyOwner(
                    $promotion->agency,
                    'Featured placement ended',
                    "The homepage featured placement for {$promotion->agency->name} has ended. Its listings and files remain available."
                );
            }
        }

        return $expired->count();
    }

    public function assertEligible(Agency $agency): void
    {
        if ($agency->status !== 'active' || $agency->verification_status !== 'verified') {
            throw new \DomainException('Only approved, verified and active agencies can receive a featured placement.');
        }
    }

    /**
     * A stable database row serializes every placement operation that can
     * consume or release a featured slot, including future-dated placements.
     */
    private function lockSlotMutex(): void
    {
        SystemSetting::query()->firstOrCreate(
            ['key' => 'promotions.max_slots'],
            [
                'value' => self::DEFAULT_MAX_SLOTS,
                'group' => 'promotions',
                'description' => 'Maximum concurrent homepage featured agency placements.',
            ]
        );

        SystemSetting::query()
            ->where('key', 'promotions.max_slots')
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function assertSlotAvailable(?int $ignorePromotionId = null): void
    {
        $query = AgencyPromotion::query()->current();
        if ($ignorePromotionId !== null) {
            $query->where('id', '!=', $ignorePromotionId);
        }
        if ($query->count() >= $this->maxSlots()) {
            throw new \DomainException('All featured slots are currently filled. Remove a placement or raise the slot limit first.');
        }
    }

    /**
     * Renumber current placements 1..N, optionally moving one row to a
     * requested slot. Unique, gap-free positions are what the public roster
     * and the reorder endpoint rely on.
     */
    private function reorderCurrent(?int $movingId = null, ?int $requestedPosition = null): void
    {
        $rows = AgencyPromotion::query()
            ->current()
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        if ($movingId !== null) {
            $moving = $rows->firstWhere('id', $movingId);
            if ($moving) {
                $rows = $rows->reject(fn (AgencyPromotion $row) => $row->id === $movingId)->values();
                $index = $requestedPosition === null
                    ? $rows->count()
                    : max(0, min($rows->count(), $requestedPosition - 1));
                $rows->splice($index, 0, [$moving]);
            }
        }

        foreach ($rows as $index => $row) {
            $position = $index + 1;
            if ((int) $row->position !== $position) {
                AgencyPromotion::whereKey($row->id)->update(['position' => $position]);
            }
        }
    }

    private function notifyOwner(Agency $agency, string $title, string $body): void
    {
        if (!$agency->owner_user_id) {
            return;
        }
        try {
            User::find($agency->owner_user_id)?->notify(new \App\Notifications\ListingUpdated($title, $body));
        } catch (\Throwable $e) {
            Log::warning('Featured placement notification failed', ['agency_id' => $agency->id, 'error' => $e->getMessage()]);
        }
    }
}
