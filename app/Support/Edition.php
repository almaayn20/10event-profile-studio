<?php

namespace App\Support;

/**
 * Which book this studio makes. One code base serves two profiles, each on its own server with its own data:
 * «event» — the 10 Event profile (960 × 540 pt, pictures under a white card), and «gift» — the 10 Gift profile
 * (566.93 × 311.81 pt, a full-page picture, thumbnails and a coloured card). Set by STUDIO_EDITION.
 */
final class Edition
{
    public static function name(): string
    {
        return config('studio.edition') === 'gift' ? 'gift' : 'event';
    }

    public static function gift(): bool
    {
        return self::name() === 'gift';
    }

    /** The page size in points. */
    public static function page(): array
    {
        return self::gift() ? ['w' => GiftGeometry::W, 'h' => GiftGeometry::H] : ['w' => PageGeometry::W, 'h' => PageGeometry::H];
    }

    /** The kinds of page a person can start from scratch. */
    public static function kinds(): array
    {
        return self::gift() ? ['project', 'trio'] : ['project'];
    }

    /** The largest priority a picture can be given: the event page has three large pictures; the gift page has the
     * main picture and three thumbnails. */
    public static function maxPriority(): int
    {
        return self::gift() ? 4 : 3;
    }
}
