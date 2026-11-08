# TaisarTownHub — Model Architecture & Relationships (Pillar 4)

**Version:** 2.0.0 · **Last updated:** 2026-11-08

## 1. Model Inventory

Located in `backend/app/Models/`. All models use `SoftDeletes` where audit-relevant and define `$fillable`, `$casts`, and relationships.

| Model | Table | Key traits |
|---|---|---|
| `User` | users | HasApiTokens (Sanctum), SoftDeletes, role helper |
| `Property` | properties | SoftDeletes, images/documents casts |
| `Dealer` | dealers | SoftDeletes, commission casts |
| `DealerFile` | dealer_files | SoftDeletes, data URL storage |
| `Project` | projects | SoftDeletes |
| `Transaction` | transactions | SoftDeletes, documents cast |
| `AgencyVideo` | agency_videos | SoftDeletes, tags cast |
| `VideoComment` | video_comments | belongsTo AgencyVideo |
| `Inquiry` | inquiries | SoftDeletes |
| `AuditLog` | audit_logs | Append-only (no soft delete) |
| `BusinessRecord` | business_records | Generic collection store |

## 2. Relationships

```
User ──belongsTo──► Dealer (dealer_id) [optional, for dealer accounts]
Dealer ──hasMany──► DealerFile
Dealer ──hasMany──► Transaction (as dealer)
Property ──hasMany──► Transaction
Seller ──hasMany──► Transaction  (stored in business_records collection 'sellers')
Buyer ──hasMany──► Transaction   (stored in business_records collection 'buyers')
AgencyVideo ──hasMany──► VideoComment (latest first)
Project ──hasMany──► Property (conceptual; via sector/phase)
```

## 3. Model Definitions (key excerpts)

### User
```php
class User extends Authenticatable {
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;
    protected $fillable = ['name','email','password','role','permissions','dealer_id','cnic','phone','avatar','active'];
    protected $casts = ['permissions' => 'array', 'active' => 'boolean'];
    public function dealer() { return $this->belongsTo(Dealer::class); }
    public function hasPermission(string $p): bool {
        if (in_array('all', $this->permissions ?? [])) return true;
        if (in_array($p, $this->permissions ?? [])) return true;
        return in_array($p, RolePermissions::for($this->role));
    }
}
```

### Property
```php
class Property extends Model {
    use SoftDeletes;
    protected $fillable = ['id','title','type','phase','sector','size_sqyd','price_pkr','status','description','location','images','documents'];
    protected $casts = ['images' => 'array', 'documents' => 'array', 'price_pkr' => 'decimal:2'];
    public function transactions() { return $this->hasMany(Transaction::class); }
    public function scopeAvailable($q) { return $q->where('status','Available')->whereNull('deleted_at'); }
}
```

### DealerFile
```php
class DealerFile extends Model {
    use SoftDeletes;
    protected $fillable = ['id','dealer_id','file_name','file_type','file_size','file_data','status','sold_at',
        'sold_price_pkr','cost_pkr','profit_pkr','commission_pkr','buyer_name','buyer_phone','buyer_cnic','sold_by'];
    protected $casts = ['sold_at' => 'datetime', 'sold_price_pkr' => 'decimal:2', 'profit_pkr' => 'decimal:2'];
    public function dealer() { return $this->belongsTo(Dealer::class); }
    public function isSold(): bool { return $this->status === 'Sold'; }
}
```

### AgencyVideo
```php
class AgencyVideo extends Model {
    use SoftDeletes;
    protected $fillable = ['id','title','description','videoUrl','thumbnailUrl','agencyId','agencyName','dealerName',
        'dealerAvatar','phase','sector','duration','likes','views','tags'];
    protected $casts = ['tags' => 'array', 'likes' => 'integer', 'views' => 'integer'];
    public function comments() { return $this->hasMany(VideoComment::class, 'agency_video_id', 'id')->latest(); }
}
```

### BusinessRecord
```php
class BusinessRecord extends Model {
    protected $fillable = ['collection','record_id','payload'];
    protected $casts = ['payload' => 'array'];
    public static function put(string $collection, string $recordId, array $payload): void {
        self::updateOrCreate(['collection' => $collection, 'record_id' => $recordId], ['payload' => $payload]);
    }
}
```

## 4. Accessors & Helpers

- `User::hasPermission()` — union of role matrix + explicit grants.
- `Property::scopeAvailable()` — reusable query scope.
- `DealerFile::isSold()` / `profitMargin()` — domain helpers.
- `AgencyVideo::comments()` — eager-loaded, latest-first.

## 5. Casts Summary

| Model | Cast | Type |
|---|---|---|
| User.permissions | array | JSON |
| Property.images/documents | array | JSON |
| Property.price_pkr | decimal:2 | Money |
| Dealer.commission_value | decimal:2 | Money |
| DealerFile.sold_at | datetime | Carbon |
| AgencyVideo.tags | array | JSON |
| BusinessRecord.payload | array | JSON |
| AuditLog.created_at | datetime | Carbon |

## 6. Fillable Discipline

Every model declares explicit `$fillable` (no `$guarded = []`). Sensitive fields (password, cnic, presenter_pin_hash) are never mass-assignable from request payloads; they are set via dedicated service methods.

## 7. Soft Delete Contract

Models with `SoftDeletes` expose `deleted_at`. Queries default to excluding trashed rows; `withTrashed()` / `onlyTrashed()` available for recycle bin. Restore via `restore()`.
