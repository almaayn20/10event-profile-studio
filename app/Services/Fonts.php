<?php

namespace App\Services;

use App\Exceptions\UserFacingException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * The profile's typeface. SF Mada is licensed and never part of the repository: the team uploads its two weights from
 * the studio's settings, they are kept in private storage, used by Chrome to print, and shown only inside the studio.
 * Until they are uploaded the pages fall back to IBM Plex Sans Arabic.
 */
class Fonts
{
    public const WEIGHTS = ['medium' => 'Medium', 'bold' => 'Bold'];

    private static function stored(string $weight): string
    {
        return 'fonts/'.$weight.'.ttf';
    }

    public static function uploaded(string $weight): bool
    {
        return Storage::disk('local')->exists(self::stored($weight));
    }

    public static function path(string $weight): string
    {
        abort_unless(isset(self::WEIGHTS[$weight]), 404);

        return self::uploaded($weight)
            ? Storage::disk('local')->path(self::stored($weight))
            : resource_path('fonts/plex-arabic/IBMPlexSansArabic-'.self::WEIGHTS[$weight].'.ttf');
    }

    /** Changes when a font is replaced, so the editor never keeps drawing with the old one. */
    public static function version(): string
    {
        return substr(md5(implode('|', array_map(fn ($w) => @filemtime(self::path($w)), array_keys(self::WEIGHTS)))), 0, 8);
    }

    public static function store(string $weight, UploadedFile $file): void
    {
        abort_unless(isset(self::WEIGHTS[$weight]), 404);
        $ext = strtolower($file->getClientOriginalExtension());
        $head = (string) file_get_contents($file->getRealPath(), false, null, 0, 4);
        if (! in_array($ext, ['ttf', 'otf'], true) || ! in_array($head, ["\x00\x01\x00\x00", 'OTTO', 'true'], true)) {
            throw new UserFacingException('file_type_not_allowed', ['types' => 'TTF · OTF']);
        }
        Storage::disk('local')->putFileAs('fonts', $file, $weight.'.ttf');
    }

    public static function present(): array
    {
        return array_combine(array_keys(self::WEIGHTS), array_map(fn ($w) => self::uploaded($w), array_keys(self::WEIGHTS)));
    }
}
