<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
        'purchasePricePKR', 'sellingPricePKR', 'internalNotes'
    ];

    protected $hidden = ['purchasePricePKR', 'sellingPricePKR', 'internalNotes'];

    protected $casts = [
        'images' => 'array',
        'features' => 'array',
        'isFeatured' => 'boolean',
        'possessionReady' => 'boolean',
        'mdaApproved' => 'boolean',
        'westOpen' => 'boolean',
        'cornerPlot' => 'boolean',
        'parkFacing' => 'boolean',
        'mainBoulevard' => 'boolean',
    ];

    public function assignedDealer()
    {
        return $this->belongsTo(Dealer::class, 'assignedDealerId', 'id');
    }
}