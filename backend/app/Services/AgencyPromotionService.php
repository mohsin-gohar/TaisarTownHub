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
            'available_slots' => max(0, $this->maxSlots() - $liveCount),
            'free_launch' => true,
            'total_placements' => AgencyPromotion::query()->count(),
        ];
    }

    /**
     * @throws \DomainException when the agency is not eligible or no slot is free
     */
    public function promote(Agency $agency, array $data, User $actor): AgencyPromotion
    {
        $this->assertEligible($agency);

        if (AgencyPromotion::query()->current()->where('agency_id', $agency->id)->exists()) {
            throw new \DomainException('This agency already has a current featured placement. Edit or cancel it first.');
        }

        $package = !empty($data['package_id'])
            ? PromotionPackage::where('is_active', true)->findOrFail($data['package_id'])
            : null;

        return DB::transaction(function () use ($agency, $data, $actor, $package) {
            $this->assertSlotAvailable();

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

            $price = 0; // Free launch: placement is never billed or sold.
            $paymentStatus = 'waived';
            $requestedPosition = isset($data['position']) ? (int) $data['position'] : null;

            $promotion = AgencyPromotion::create([
                'agency_id' => $agency->id,
                'package_id' => $package?->id,
                'status' => $data['status'] ?? 'active',
                // Renumbered immediately below; this is only a starting value.
                'position' => 0,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'price' => $price,
                'currency' => 'PKR',
                'payment_status' => $paymentStatus,
                'payment_method' => null,
                'payment_reference' => null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
                'approved_by' => $actor->id,
            ]);

            $this->reorderCurrent($promotion->id, $requestedPosition);
            $promotion->refresh();

            $this->notifyOwner(
                $agency,
                'Featured placement confirmed',
                "{$agency->name} is now scheduled in the homepage featured roster (position {$promotion->position})."
            );

            return $promotion->load(['agency:id,name,slug', 'package']);
        });
    }

    public function update(AgencyPromotion $promotion, array $data, User $actor): AgencyPromotion
    {
        return DB::transaction(function () use ($promotion, $data, $actor) {
            $status = $data['status'] ?? $promotion->status;

            if (in_array($status, ['active', 'pending'], true)) {
                $this->assertEligible($promotion->agency);
                $this->assertSlotAvailable($promotion->id);
            }

            $requestedPosition = array_key_exists('position', $data) && $data['position'] !== null
                ? (int) $data['position']
                : null;

            if (array_key_exists('starts_at', $data)) {
                $promotion->starts_at = $data['starts_at'] ? Carbon::parse($data['starts_at']) : null;
            }
            if (array_key_exists('ends_at', $data)) {
                $promotion->ends_at = $data['ends_at'] ? Carbon::parse($data['ends_at']) : null;
            }
            if ($promotion->starts_at && $promotion->ends_at && $promotion->ends_at->lessThanOrEqualTo($promotion->starts_at)) {
                throw new \DomainException('The promotion end date must be after its start date.');
            }

            // Free launch: payment metadata is retained in the schema for a
            // future billing release, but it is not writable now and the public
            // roster never labels a placement as Sponsored.
            $promotion->price = 0;
            $promotion->currency = 'PKR';
            $promotion->payment_status = 'waived';
            $promotion->payment_method = null;
            $promotion->payment_reference = null;
            if (array_key_exists('notes', $data)) {
                $promotion->notes = $data['notes'];
            }

            $promotion->status = $status;
            $promotion->approved_by = $actor->id;
            $promotion->cancelled_at = $status === 'cancelled' ? now() : null;
            $promotion->save();

            $this->reorderCurrent($promotion->id, $requestedPosition);
            $promotion->refresh();

            return $promotion->load(['agency:id,name,slug', 'package']);
        });
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
        if (count($ids) > $this->maxSlots()) {
            throw new \DomainException('The requested order exceeds the configured featured-slot limit.');
        }

        DB::transaction(function () use ($ids) {
            $rows = AgencyPromotion::query()->current()->whereIn('id', $ids)->get()->keyBy('id');
            if ($rows->count() !== count($ids)) {
                throw new \DomainException('One or more placements are no longer active. Refresh and try again.');
            }

            foreach ($ids as $index => $id) {
                AgencyPromotion::whereKey($id)->update(['position' => $index + 1]);
            }
        });

        Log::info('Featured placement order updated', ['ids' => $ids, 'by' => $actor->id]);
    }

    /**
     * Expire finished placements. Files/listings are untouched; only the
     * homepage advertisement state changes.
     */
    public function expireDue(): int
    {
        $due = AgencyPromotion::query()->expiredDue()->get();
        if ($due->isEmpty()) {
            return 0;
        }

        DB::transaction(function () use ($due) {
            foreach ($due as $promotion) {
                $promotion->status = 'expired';
                $promotion->save();

                if ($promotion->agency) {
                    $this->notifyOwner(
                        $promotion->agency,
                        'Featured placement ended',
                        "The homepage featured placement for {$promotion->agency->name} has ended. Its listings and files remain available."
                    );
                }
            }
        });

        return $due->count();
    }

    public function assertEligible(Agency $agency): void
    {
        if ($agency->status !== 'active' || $agency->verification_status !== 'verified') {
            throw new \DomainException('Only approved, verified and active agencies can receive a featured placement.');
        }
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
