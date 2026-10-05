<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    /**
     * Per-request memo of raw (undecrypted) DB values, keyed by setting key.
     * Every request is a fresh PHP process (no Octane), so this never leaks
     * between real requests — it only avoids re-querying the same key twice
     * within one request (e.g. AppServiceProvider::configureFromDatabase()
     * and HandleInertiaRequests::share() both read several of these).
     *
     * @var array<string, string|null>
     */
    private static array $memo = [];

    public static function get(string $key, ?string $default = null): ?string
    {
        if (! array_key_exists($key, self::$memo)) {
            self::$memo[$key] = static::query()->find($key)?->value;
        }

        return self::$memo[$key] ?? $default;
    }

    public static function set(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);

        self::$memo[$key] = $value;
    }

    /**
     * Reset the per-request memo. Only needed in tests, where many requests
     * are simulated within a single long-lived PHP process.
     */
    public static function flushMemo(): void
    {
        self::$memo = [];
    }
}
