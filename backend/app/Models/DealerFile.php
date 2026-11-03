<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DealerFile extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title', 'type', 'url', 'folder', 'file_size_kb', 'dimensions',
        'dealer_id', 'uploaded_by', 'status', 'sold_by_dealer_id',
        'selling_price_kbr', 'cost_price_kbr', 'profit_kbr',
        'buyer_name', 'buyer_phone', 'buyer_cnic'
    ];

    protected $casts = [
        'file_size_kb' => 'float',
        'selling_price_kbr' => 'float',
        'cost_price_kbr' => 'float',
        'profit_kbr' => 'float',
    ];

    public function dealer()
    {
        return $this->belongsTo(Dealer::class, 'dealer_id', 'id');
    }

    public function uploadedBy()
    {
        return $this->belongsTo(Dealer::class, 'uploaded_by', 'id');
    }

    public function soldBy()
    {
        return $this->belongsTo(Dealer::class, 'sold_by_dealer_id', 'id');
    }
}