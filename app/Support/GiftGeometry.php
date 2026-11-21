<?php

namespace App\Support;

use App\Models\Image;
use App\Models\Project;

/**
 * The fixed frame of a 10 Gift page, measured from «10Gift Profile V1.pdf» (566.93 × 311.81 pt, pages 7–33).
 * Points, origin at the top left — the same units the HTML page, the editor and the printed PDF use.
 *
 * A gift page: one picture over the whole page, up to three thumbnails along the foot (packed from the right),
 * a card in the page's colour (85 %) holding the client, the description and three figures, a tab at the top left
 * holding the client's logo (85 %), and the category («هدية», «دعوة» …) in a tag at the top right (solid).
 * A trio page (the awards pages, 34–35): three pictures side by side, a third of the page each, and the tag.
 */
final class GiftGeometry
{
    public const W = 566.93;

    public const H = 311.81;

    /** The card the texts sit on — the profile's own vector, the same on every page. */
    public const CARD = 'M 461.67 156.28 C 456.20 156.28 450.76 157.66 445.96 160.29 L 404.25 183.05 C 395.44 187.86 389.39 196.29 387.66 206.17 '
        .'L 380.32 247.99 C 378.46 258.61 381.94 269.47 389.64 277.03 L 423.85 310.66 C 430.02 316.73 438.18 320.07 446.83 320.07 '
        .'C 449.16 320.07 451.49 319.82 453.77 319.33 L 533.61 302.05 C 540.91 300.47 547.51 296.40 552.20 290.59 '
        .'C 556.88 284.78 559.47 277.47 559.47 270.00 L 559.47 218.89 C 559.47 206.11 551.96 194.41 540.35 189.08 '
        .'L 475.34 159.26 C 471.02 157.28 466.42 156.28 461.67 156.28 Z';

    /** The three thumbnail places, right to left (the first thumbnail goes nearest the card); rounded 10.57 pt. */
    public const SLOTS = [
        ['x' => 250.83, 'y' => 222.08, 'w' => 114.35, 'h' => 77.36],
        ['x' => 130.83, 'y' => 222.08, 'w' => 114.35, 'h' => 77.36],
        ['x' => 10.79, 'y' => 222.08, 'w' => 114.35, 'h' => 77.36],
    ];

    public const SLOT_RADIUS = 10.57;

    /** The logo tab: from off the page's left edge to just past the logo; 12.84–45.44 pt down. */
    public const TAB = ['x' => -122.77, 'y' => 12.84, 'h' => 32.6, 'r' => 8.29, 'min_right' => 42.9, 'empty_right' => 64.3];

    /** Where a logo goes when nobody placed it by hand: this box, the logo fitted inside and centred on its height. */
    public const LOGO_AREA = ['x' => 7.0, 'cy' => 29.14, 'w' => 66.0, 'h' => 20.0];

    /** The category tag: from just before its word to off the page's right edge. */
    public const TAG = ['y' => 18.53, 'h' => 21.22, 'r' => 8.29, 'text_right' => 548.0, 'baseline' => 30.99, 'size' => 11.31, 'pad' => 11.4, 'max_left' => 506.7];

    /** The trio page's three pictures, right to left. */
    public const PANELS = [
        ['x' => 378.0, 'y' => 0.0, 'w' => 188.93, 'h' => self::H],
        ['x' => 189.0, 'y' => 0.0, 'w' => 189.0, 'h' => self::H],
        ['x' => 0.0, 'y' => 0.0, 'w' => 189.0, 'h' => self::H],
    ];

    public const DEFAULT_COLOR = '#2D2925';

    public const CATEGORIES = ['هديـــة', 'دعـــوة', 'حملة تسويقية', 'دروع'];

    public const DEFAULT_STATS = [
        ['value' => '', 'label' => 'عدد النسخ'],
        ['value' => '', 'label' => 'مواد مستخدمه'],
        ['value' => '1', 'label' => 'حجم'],
    ];

    /**
     * The page's pictures in their places: a picture marked 1 is the main one (2, 3, 4 the thumbnails; on a trio
     * page 1, 2, 3 are the panels from the right); places nobody marked are filled with the unmarked pictures in the
     * order they were added. Thumbnails are then packed from the right, as in the profile. Each place is a window
     * the picture covers, scaled to cover it, on the point chosen by hand if any.
     *
     * @return list<array{id: int, x: float, y: float, w: float, h: float, cx: float, cy: float, slot: string, img: array}>
     */
    public static function windows(Project $project): array
    {
        if ($project->isPicturePage()) {
            $places = [['x' => 0.0, 'y' => 0.0, 'w' => self::W, 'h' => self::H, 'slot' => 'hero']];
            $wanted = 1;
        } elseif ($project->kind === 'trio') {
            $places = array_map(fn ($p) => $p + ['slot' => 'panel'], self::PANELS);
            $wanted = 3;
        } else {
            $places = [['x' => 0.0, 'y' => 0.0, 'w' => self::W, 'h' => self::H, 'slot' => 'hero']];
            $wanted = 4;
        }

        $chosen = self::assign($project->images->all(), $wanted);
        $out = [];
        if ($project->kind === 'trio') {
            // the panels, packed from the right
            foreach (array_values(array_filter($chosen)) as $i => $img) {
                $out[] = self::window($img, $places[$i]);
            }

            return $out;
        }
        if ($chosen[0] ?? null) {
            $out[] = self::window($chosen[0], $places[0]);
        }
        if ($wanted > 1) {
            foreach (array_values(array_filter(array_slice($chosen, 1))) as $i => $img) {
                $out[] = self::window($img, self::SLOTS[$i] + ['slot' => 'thumb']);
            }
        }

        return $out;
    }

    /**
     * $wanted places, by priority first (priority n → place n), then the unmarked pictures in order into the places
     * still empty. A picture marked beyond the places is left out.
     *
     * @param  list<Image>  $images
     * @return array<int, Image|null>
     */
    public static function assign(array $images, int $wanted): array
    {
        $places = array_fill(0, $wanted, null);
        $rest = [];
        usort($images, fn ($a, $b) => [$a->sort, $a->id] <=> [$b->sort, $b->id]);
        foreach ($images as $img) {
            $p = (int) ($img->priority ?? 0);
            if ($p >= 1 && $p <= $wanted && $places[$p - 1] === null) {
                $places[$p - 1] = $img;
            } elseif (! $p) {
                $rest[] = $img;
            }
        }
        foreach ($places as $i => $img) {
            if ($img === null && $rest) {
                $places[$i] = array_shift($rest);
            }
        }

        return $places;
    }

    private static function window(Image $img, array $place): array
    {
        $box = ['w' => $place['w'], 'h' => $place['h'], 'cx' => $place['w'] / 2, 'cy' => $place['h'] / 2];

        return [
            'id' => $img->id, 'x' => round($place['x'], 2), 'y' => round($place['y'], 2), 'w' => round($place['w'], 2), 'h' => round($place['h'], 2),
            'cx' => round($box['cx'], 2), 'cy' => round($box['cy'], 2), 'slot' => $place['slot'],
            'img' => PageGeometry::placeImage($box, (float) $img->width, (float) $img->height, $img->crop_x, $img->crop_y, $img->crop_zoom),
        ];
    }

    /**
     * Where the logo sits ({x, y, w, h} in points) and where the tab ends: as placed by hand (the imported pages keep
     * the profile's own placing), else fitted into LOGO_AREA. Without a logo the tab keeps the profile's empty width.
     */
    public static function logoPlace(?array $logo): array
    {
        if (! $logo || empty($logo['path'])) {
            // no logo: the tab keeps the width it had in the profile, or the profile's usual empty width
            return ['box' => null, 'tab' => round((float) ($logo['tab'] ?? self::TAB['empty_right']), 2)];
        }
        $box = $logo['box'] ?? null;
        if (! $box) {
            $iw = max(1.0, (float) ($logo['w'] ?? 1));
            $ih = max(1.0, (float) ($logo['h'] ?? 1));
            $a = self::LOGO_AREA;
            $s = min($a['w'] / $iw, $a['h'] / $ih);
            $box = ['x' => $a['x'], 'y' => round($a['cy'] - $ih * $s / 2, 2), 'w' => round($iw * $s, 2), 'h' => round($ih * $s, 2)];
        }
        // the tab ends as far past the logo as the logo is from the page's edge, as in the profile
        $tab = $logo['tab'] ?? max(self::TAB['min_right'], $box['x'] + $box['w'] + max(4.0, (float) $box['x']));

        return ['box' => $box, 'tab' => round((float) $tab, 2)];
    }

    /** The parts of the page that lie over the main picture (the card and the thumbnails used), for the crop view. */
    public static function coverPath(Project $project): string
    {
        if ($project->kind !== 'project' && $project->kind !== null) {
            return '';
        }
        $parts = [self::CARD];
        foreach (self::windows($project) as $w) {
            if ($w['slot'] === 'thumb') {
                $parts[] = self::roundedRect($w['x'], $w['y'], $w['w'], $w['h'], self::SLOT_RADIUS);
            }
        }

        return implode(' ', $parts);
    }

    public static function roundedRect(float $x, float $y, float $w, float $h, float $r): string
    {
        $f = fn ($v) => number_format($v, 2, '.', '');
        $k = 0.5523 * $r;

        return 'M '.$f($x + $r).' '.$f($y).' L '.$f($x + $w - $r).' '.$f($y)
            .' C '.$f($x + $w - $r + $k).' '.$f($y).' '.$f($x + $w).' '.$f($y + $r - $k).' '.$f($x + $w).' '.$f($y + $r)
            .' L '.$f($x + $w).' '.$f($y + $h - $r)
            .' C '.$f($x + $w).' '.$f($y + $h - $r + $k).' '.$f($x + $w - $r + $k).' '.$f($y + $h).' '.$f($x + $w - $r).' '.$f($y + $h)
            .' L '.$f($x + $r).' '.$f($y + $h)
            .' C '.$f($x + $r - $k).' '.$f($y + $h).' '.$f($x).' '.$f($y + $h - $r + $k).' '.$f($x).' '.$f($y + $h - $r)
            .' L '.$f($x).' '.$f($y + $r)
            .' C '.$f($x).' '.$f($y + $r - $k).' '.$f($x + $r - $k).' '.$f($y).' '.$f($x + $r).' '.$f($y).' Z';
    }
}
