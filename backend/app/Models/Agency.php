<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Agency extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'agencies';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'name', 'slug', 'logo_url', 'cover_url', 'description', 'email', 'phone',
        'whatsapp', 'office_address', 'city', 'registration_number', 'website',
        'status', 'verification_status', 'verification_notes', 'verification_document_id',
        'owner_user_id', 'verified_by', 'verified_at', 'settings',
    ];

    protected $hidden = ['settings'];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'verified_at' => 'datetime',
        ];
    }

    public function members(): HasMany
    {
        return $this->hasMany(AgencyMember::class, 'agency_id');
    }

    public function activeMembers(): HasMany
    {
        return $this->members()->where('status', 'active');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class, 'agency_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'agency_id');
    }

    public function mediaFiles(): HasMany
    {
        return $this->hasMany(MediaFile::class, 'agency_id');
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class, 'agency_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'agency_id');
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(AgencySubscription::class, 'agency_id');
    }

    /** Every placement (history included) — use the service for active slots. */
    public function promotions(): HasMany
    {
        return $this->hasMany(AgencyPromotion::class, 'agency_id');
    }

    /** Currently live homepage placement, if any. */
    public function activePromotion(): HasOne
    {
        return $this->hasOne(AgencyPromotion::class, 'agency_id')
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->orderBy('position');
    }

    public function documentFolders(): HasMany
    {
        return $this->hasMany(DocumentFolder::class, 'agency_id');
    }

    public function documentShares(): HasMany
    {
        return $this->hasMany(DocumentShare::class, 'agency_id');
    }

    public function documentAccessLogs(): HasMany
    {
        return $this->hasMany(DocumentAccessLog::class, 'agency_id');
    }

    public function quarantinedDocuments(): HasMany
    {
        return $this->hasMany(DocumentQuarantine::class, 'agency_id');
    }

    public function leadActivities(): HasMany
    {
        return $this->hasMany(LeadActivity::class, 'agency_id');
    }

    public function transactionPayments(): HasMany
    {
        return $this->hasMany(TransactionPayment::class, 'agency_id');
    }

    public function paymentSchedules(): HasMany
    {
        return $this->hasMany(PaymentSchedule::class, 'agency_id');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class, 'agency_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'agency_id');
    }

    public function subscriptionInvoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class, 'agency_id');
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'agency_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'agency_id');
    }

    public function propertyStatusHistory(): HasMany
    {
        return $this->hasMany(PropertyStatusHistory::class, 'agency_id');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(AgencyInvitation::class, 'agency_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    /** Effective limits: subscription plan limits merged with per-agency overrides. */
    public function limits(): array
    {
        // Every agency starts with an unlimited workspace in this release.
        // Platform administrators may still assign a plan/override for other
        // limits (listings, staff, leads); storage bytes are monitored only.
        $defaults = [
            'listings' => 10, 'staff' => 2, 'storage_mb' => 0,
            'dealer_files' => 100, 'featured_credits' => 0, 'listing_days' => 30,
        ];
        $subscription = $this->subscription;
        if ($subscription && $subscription->plan) {
            $defaults = array_merge($defaults, $subscription->plan->limits ?? []);
        }
        if ($subscription && $subscription->limits_override) {
            $defaults = array_merge($defaults, $subscription->limits_override);
        }
        return $defaults;
    }
}
