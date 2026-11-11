<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'value', 'group', 'description', 'updated_by'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    public static function get(string $key, $default = null)
    {
        $row = static::where('key', $key)->first();
        return $row ? $row->value : $default;
    }

    public static function set(string $key, mixed $value, ?int $updatedBy = null): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'updated_by' => $updatedBy]
        );
    }
}
