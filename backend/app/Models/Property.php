<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Property extends Model
{
    use SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'plotNumber', 'fileNumber', 'title', 'description',
        'pricePKR', 'formattedPrice', 'location', 'phase', 'sector',
        'block', 'sizeSqYards', 'roadWidthFeet', 'type', 'status',
        'isFeatured', 'possessionReady', 'mdaApproved', 'westOpen',
        'cornerPlot', 'parkFacing', 'mainBoulevard', 'images', 'features',
        'contactPerson', 'contactPhone', 'contactWhatsApp', 'assignedDealerId',
        'agencyName', 'createdDate',
        'purchasePricePKR', 'sellingPricePKR', 'internalNotes',
        // Enterprise fields
        'agency_id', 'created_by', 'updated_by', 'approval_status', 'visibility',
        'featured_status', 'listing_purpose', 'slug', 'category_id', 'sector_id',
        'block_id', 'submitted_at', 'published_at', 'expires_at', 'rejection_reason',
        'streetNumber', 'sizeUnit', 'frontFeet', 'depthFeet', 'roadWidthFt',
        'isRoadFacing', 'is100ftRoadFacing', 'videoUrl', 'bedrooms', 'bathrooms',
        'sellerId', 'sellerName', 'buyerId', 'buyerName', 'deletedBy',
        'pending_changes',
    ];

    protected $hidden = ['purchasePricePKR', 'sellingPricePKR', 'internalNotes'];

    protected $casts = [
        'images' => 'array',
        'features' => 'array',
        'pending_changes' => 'array',
        'isFeatured' => 'boolean',
        'possessionReady' => 'boolean',
        'mdaApproved' => 'boolean',
        'westOpen' => 'boolean',
        'cornerPlot' => 'boolean',
        'parkFacing' => 'boolean',
        'mainBoulevard' => 'boolean',
        'isRoadFacing' => 'boolean',
        'is100ftRoadFacing' => 'boolean',
        'submitted_at' => 'datetime',
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /* ---------------- Relationships ---------------- */

    public function assignedDealer()
    {
        return $this->belongsTo(Dealer::class, 'assignedDealerId', 'id');
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sectorRelation(): BelongsTo
    {
        return $this->belongsTo(Sector::class, 'sector_id');
    }

    public function blockRelation(): BelongsTo
    {
        return $this->belongsTo(Block::class, 'block_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PropertyCategory::class, 'category_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(PropertyStatusHistory::class, 'property_id')->orderByDesc('created_at');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'property_id');
    }

    public function mediaFiles(): HasMany
    {
        return $this->hasMany(MediaFile::class, 'property_id');
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class, 'property_id');
    }

    public function viewsCount(): int
    {
        return $this->views()->count();
    }

    public function views()
    {
        return $this->hasMany(PropertyView::class, 'property_id');
    }

    /* ---------------- Scopes ---------------- */

    /** Public marketplace visibility. */
    public function scopePubliclyVisible($query)
    {
        return $query->where('approval_status', 'approved')
            ->where('visibility', 'public')
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function scopeForAgency($query, ?string $agencyId)
    {
        return $agencyId === null
            ? $query->whereNull('agency_id') // platform-owned records only
            : $query->where('agency_id', $agencyId);
    }

    public function scopeFeatured($query)
    {
        return $query->where(function ($q) {
            $q->where('featured_status', 'granted')->orWhere('isFeatured', true);
        });
    }

    /* ---------------- Workflow helpers ---------------- */

    public function isPubliclyVisible(): bool
    {
        return $this->approval_status === 'approved'
            && $this->visibility === 'public'
            && !$this->trashed()
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /** Fields whose edit requires re-approval (public-facing content). */
    public const REAPPROVAL_FIELDS = [
        'title', 'description', 'pricePKR', 'location', 'sector', 'block',
        'sizeSqYards', 'type', 'images', 'features', 'videoUrl', 'listing_purpose',
        'contactPerson', 'contactPhone', 'contactWhatsApp',
    ];
}
