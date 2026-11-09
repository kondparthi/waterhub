<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'label', 'is_public'];
    protected $casts = ['is_public' => 'boolean'];

    public static function get(string $key, $default = null)
    {
        $all = Cache::rememberForever('app_settings', fn () => static::all()->keyBy('key')->map(fn ($s) => $s->castValue())->all());
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function flush(): void { Cache::forget('app_settings'); }

    public function castValue()
    {
        return match ($this->type) {
            'int' => (int) $this->value,
            'decimal' => (float) $this->value,
            'bool' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($this->value ?? 'null', true),
            default => $this->value,
        };
    }
}
