<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;

/** The few values the team changes from the studio itself: the password (encrypted) and a few switches. */
class Settings
{
    /** Statistics on every project page, or on none (each project keeps its own, ready for when they come back). */
    public static function statsShown(): bool
    {
        return Setting::find('stats_shown')?->value !== '0';
    }

    public static function showStats(bool $on): void
    {
        Setting::updateOrCreate(['key' => 'stats_shown'], ['value' => $on ? '1' : '0']);
    }

    public static function secret(string $key): ?string
    {
        $value = Setting::find($key)?->value;
        if ($value === null) {
            return null;
        }
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function putSecret(string $key, ?string $value): void
    {
        if ($value === null || $value === '') {
            Setting::where('key', $key)->delete();

            return;
        }
        Setting::updateOrCreate(['key' => $key], ['value' => Crypt::encryptString($value)]);
    }
}
