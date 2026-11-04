<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor',
        'role',
        'action',
        'target',
        'detail',
        'ip',
        'created_at',
    ];
}
