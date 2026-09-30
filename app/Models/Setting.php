<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Throwable;

class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    private static ?array $loaded = null;

    /** Все настройки одним запросом на запрос страницы. */
    public static function values(): array
    {
        if (static::$loaded !== null) {
            return static::$loaded;
        }
        try {
            return static::$loaded = static::query()->pluck('value', 'key')->all();
        } catch (Throwable) {
            return static::$loaded = [];
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = static::values()[$key] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }

    /** Текст на текущем языке с запасными вариантами. */
    public static function text(string $key, ?string $default = null): ?string
    {
        $value = static::get($key);
        if (is_array($value)) {
            foreach ([app()->getLocale(), 'en', 'ru', 'hy'] as $locale) {
                if (! empty($value[$locale])) {
                    return $value[$locale];
                }
            }

            return $default;
        }

        return $value ?: $default;
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        static::$loaded = null;
    }
}
