<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['group', 'key', 'value', 'type', 'label', 'description', 'is_public'];

    protected $casts = ['is_public' => 'boolean'];

    // Get a setting value by key
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = Cache::remember("setting:{$key}", 3600, fn() => static::where('key', $key)->first());
        if (!$setting) return $default;
        return static::castValue($setting->value, $setting->type);
    }

    // Set a setting value
    public static function set(string $key, mixed $value): void
    {
        static::where('key', $key)->update(['value' => $value]);
        Cache::forget("setting:{$key}");
    }

    // Get all settings for a group as key=>value
    public static function getGroup(string $group): array
    {
        return static::where('group', $group)->get()->mapWithKeys(fn($s) => [
            $s->key => static::castValue($s->value, $s->type)
        ])->toArray();
    }

    private static function castValue(mixed $value, string $type): mixed
    {
        return match($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'json'    => json_decode($value, true),
            default   => $value,
        };
    }
}
