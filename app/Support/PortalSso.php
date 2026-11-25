<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * A sign-in link from the 10concept platforms portal: «v1.<base64url claims>.<HMAC-SHA256>», signed with this
 * studio's own secret, valid for a minute and only once. Same format as 10event-portal/lib/sso.js.
 */
final class PortalSso
{
    public static function verify(string $token): array
    {
        $secret = (string) config('studio.portal_secret');
        if (strlen($secret) < 32) {
            throw new RuntimeException('الدخول من البوابة غير مفعّل هنا');
        }
        $parts = explode('.', $token);
        if (count($parts) !== 3 || $parts[0] !== 'v1' || $parts[1] === '' || $parts[2] === '') {
            throw new RuntimeException('رابط الدخول غير صالح');
        }
        $want = rtrim(strtr(base64_encode(hash_hmac('sha256', 'v1.'.$parts[1], $secret, true)), '+/', '-_'), '=');
        if (! hash_equals($want, $parts[2])) {
            throw new RuntimeException('رابط الدخول غير صالح');
        }
        $claims = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/'), true), true);
        if (! is_array($claims)) {
            throw new RuntimeException('رابط الدخول غير صالح');
        }
        if (($claims['app'] ?? null) !== config('studio.portal_app')) {
            throw new RuntimeException('رابط الدخول لمنصة ثانية');
        }
        if (! is_numeric($claims['exp'] ?? null) || $claims['exp'] <= time()) {
            throw new RuntimeException('انتهت صلاحية رابط الدخول — افتح المنصة من البوابة مرة ثانية');
        }
        $jti = (string) ($claims['jti'] ?? '');
        if ($jti === '' || ! Cache::add('portal-sso:'.$jti, 1, 180)) {
            throw new RuntimeException('رابط الدخول مستخدم — افتح المنصة من البوابة مرة ثانية');
        }

        return $claims;
    }

    public static function safeNext(?string $next): string
    {
        $n = (string) $next;

        return str_starts_with($n, '/') && ! str_starts_with($n, '//') && ! str_starts_with($n, '/\\') ? substr($n, 0, 500) : '/';
    }
}
