<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * The studio's single shared password. What a session remembers is a fingerprint of the password in force, so
 * changing the password signs every open session out.
 */
final class StudioAccess
{
    private const KEY = 'studio.password_hash';

    private const SESSION = 'studio.token';

    public static function configured(): bool
    {
        return self::stored() !== null || filled(config('studio.password'));
    }

    public static function check(string $password): bool
    {
        if ($hash = self::stored()) {
            return Hash::check($password, $hash);
        }
        $first = (string) config('studio.password');

        return $first !== '' && hash_equals($first, $password);
    }

    public static function set(string $password): void
    {
        Settings::putSecret(self::KEY, Hash::make($password));
    }

    public static function grant(Request $request): void
    {
        $request->session()->regenerate();
        $request->session()->put(self::SESSION, self::token());
    }

    public static function revoke(Request $request): void
    {
        $request->session()->forget(self::SESSION);
    }

    public static function granted(Request $request): bool
    {
        $token = self::token();

        return $token !== null && hash_equals($token, (string) $request->session()->get(self::SESSION));
    }

    private static function stored(): ?string
    {
        return Settings::secret(self::KEY);
    }

    private static function token(): ?string
    {
        $basis = self::stored() ?? (filled(config('studio.password')) ? 'env:'.config('studio.password') : null);

        return $basis === null ? null : hash_hmac('sha256', $basis, (string) config('app.key'));
    }
}
