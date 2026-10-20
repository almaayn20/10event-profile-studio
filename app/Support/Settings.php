<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;

/** The few values the team changes from the studio itself (today only the password), kept encrypted. */
class Settings
{
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
