<?php

namespace App\Support;

/**
 * The fixed frame of a 10 Event project page, measured from the company profile (960 × 540 pt, page 16).
 * Everything here is in PDF points with the origin at the top left, so the HTML page, the collage and the
 * printed PDF all speak the same units.
 */
final class PageGeometry
{
    public const W = 960.0;

    public const H = 540.0;

    /** The white card the text sits on, with its brown outline — the profile's own vector, unchanged. */
    public const BLOB = 'M 684.52 544.18 C 701.98 525.66 709.74 499.95 705.33 474.77 L 687.39 372.45 C 683.12 348.08 668.22 327.30 646.52 315.45 '
        .'L 544.48 259.74 C 532.64 253.28 519.26 249.86 505.77 249.86 C 494.07 249.86 482.73 252.34 472.09 257.22 L 313.09 330.19 '
        .'C 284.47 343.32 265.98 372.16 265.98 403.66 L 265.98 528.75 C 265.98 533.95 266.48 539.12 267.47 544.18 Z';

    public const BROWN = '#AF4F12';

    /** The default statistics: the three every project page in the profile opens with. */
    public const DEFAULT_STATS = [
        ['value' => '100+', 'label' => 'مُعلِـن'],
        ['value' => '4 مناطق', 'label' => 'تفعيـــل'],
        ['value' => '1 يـــوم', 'label' => 'للفعالية'],
    ];

    /** Right edges of the (up to three) statistic columns, right to left. */
    public const STAT_COLUMNS = [646.0, 579.7, 502.8];

    /**
     * The card as a polygon (the curves flattened), for working out how much of a picture it hides.
     *
     * @return list<array{0: float, 1: float}>
     */
    public static function blobPolygon(): array
    {
        static $poly = null;
        if ($poly !== null) {
            return $poly;
        }
        preg_match_all('/[MLCZ]|-?\d+(?:\.\d+)?/', self::BLOB, $m);
        $tokens = $m[0];
        $poly = [];
        $cur = [0.0, 0.0];
        $cmd = 'M';
        for ($i = 0; $i < count($tokens);) {
            $t = $tokens[$i];
            if (in_array($t, ['M', 'L', 'C', 'Z'], true)) {
                $cmd = $t;
                $i++;
                if ($t === 'Z') {
                    break;
                }

                continue;
            }
            if ($cmd === 'C') {
                [$x1, $y1, $x2, $y2, $x, $y] = array_map('floatval', array_slice($tokens, $i, 6));
                for ($s = 1; $s <= 12; $s++) {
                    $u = $s / 12;
                    $a = (1 - $u) ** 3;
                    $b = 3 * (1 - $u) ** 2 * $u;
                    $c = 3 * (1 - $u) * $u ** 2;
                    $d = $u ** 3;
                    $poly[] = [$a * $cur[0] + $b * $x1 + $c * $x2 + $d * $x, $a * $cur[1] + $b * $y1 + $c * $y2 + $d * $y];
                }
                $cur = [$x, $y];
                $i += 6;
            } else {
                $cur = [(float) $tokens[$i], (float) $tokens[$i + 1]];
                $poly[] = $cur;
                $i += 2;
            }
        }

        return $poly;
    }

    /**
     * Where the card crosses the horizontal line at $y: [left, right], or null where the line misses it. The card is
     * a single bump, so every line meets it at most once.
     *
     * @return array{0: float, 1: float}|null
     */
    public static function blobSpan(float $y): ?array
    {
        static $rows = [];
        $key = (string) round($y, 2);
        if (array_key_exists($key, $rows)) {
            return $rows[$key];
        }
        $poly = self::blobPolygon();
        $n = count($poly);
        $xs = [];
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$xi, $yi] = $poly[$i];
            [$xj, $yj] = $poly[$j];
            if (($yi > $y) !== ($yj > $y)) {
                $xs[] = $xi + ($y - $yi) * ($xj - $xi) / ($yj - $yi);
            }
        }

        return $rows[$key] = $xs ? [min($xs), max($xs)] : null;
    }

    /**
     * The part of a rectangle the card leaves in view: its bounding box (x, y, w, h), the middle of what shows
     * (cx, cy, from the box's corner) and how much of the box the card still covers (hidden, 0–1); null when the
     * card hides it all. Rows are sampled every $step points; the box is then grown by $pad towards the card (never
     * past the rectangle), so a picture drawn in it always reaches under the card's edge and leaves no gap.
     *
     * @return array{x: float, y: float, w: float, h: float, cx: float, cy: float, hidden: float, visible: float}|null
     */
    public static function visibleBox(float $x, float $y, float $w, float $h, float $step = 2.0, float $pad = 4.0): ?array
    {
        $x1 = $x + $w;
        $y1 = $y + $h;
        $rows = max(1, (int) ceil($h / $step));
        $dy = $h / $rows;
        $vx0 = INF;
        $vx1 = -INF;
        $vy0 = INF;
        $vy1 = -INF;
        $area = 0.0;
        $mx = 0.0;
        $my = 0.0;
        for ($r = 0; $r < $rows; $r++) {
            $ry = $y + ($r + 0.5) * $dy;
            $span = self::blobSpan($ry);
            // the visible pieces of this row: left of the card and right of it
            $pieces = $span === null ? [[$x, $x1]] : array_filter([[$x, min($x1, $span[0])], [max($x, $span[1]), $x1]], fn ($p) => $p[1] - $p[0] > 0.01);
            foreach ($pieces as [$a, $b]) {
                $vx0 = min($vx0, $a);
                $vx1 = max($vx1, $b);
                $vy0 = min($vy0, $ry - $dy / 2);
                $vy1 = max($vy1, $ry + $dy / 2);
                $len = ($b - $a) * $dy;
                $area += $len;
                $mx += ($a + $b) / 2 * $len;
                $my += $ry * $len;
            }
        }
        if ($area < 1.0) {
            return null;
        }
        // grow towards the card only: an edge that is the rectangle's own edge stays where it is
        $vx0 = $vx0 - $x < 0.01 ? $x : max($x, $vx0 - $pad);
        $vx1 = $x1 - $vx1 < 0.01 ? $x1 : min($x1, $vx1 + $pad);
        $vy0 = $vy0 - $y < 0.01 ? $y : max($y, $vy0 - $pad);
        $vy1 = $y1 - $vy1 < 0.01 ? $y1 : min($y1, $vy1 + $pad);
        $box = ($vx1 - $vx0) * ($vy1 - $vy0);

        return [
            'x' => $vx0, 'y' => $vy0, 'w' => $vx1 - $vx0, 'h' => $vy1 - $vy0,
            'cx' => $mx / $area - $vx0, 'cy' => $my / $area - $vy0,
            'hidden' => max(0.0, 1 - $area / $box), 'visible' => $area,
        ];
    }

    /**
     * Where a picture sits in its window ($box: w, h, cx, cy) so it covers the window: scaled to cover it, times
     * $zoom, with the picture's point ($fx, $fy — fractions of its width and height) on the middle of what shows,
     * as near as the picture's edges allow. Returns left, top, width, height relative to the window.
     *
     * @return array{x: float, y: float, w: float, h: float}
     */
    public static function placeImage(array $box, float $iw, float $ih, ?float $fx = null, ?float $fy = null, ?float $zoom = null): array
    {
        $iw = max(1.0, $iw);
        $ih = max(1.0, $ih);
        $s = max($box['w'] / $iw, $box['h'] / $ih) * max(1.0, $zoom ?? 1.0);
        $dw = $iw * $s;
        $dh = $ih * $s;
        $left = min(0.0, max($box['w'] - $dw, $box['cx'] - ($fx ?? 0.5) * $dw));
        $top = min(0.0, max($box['h'] - $dh, $box['cy'] - ($fy ?? 0.5) * $dh));

        return ['x' => round($left, 2), 'y' => round($top, 2), 'w' => round($dw, 2), 'h' => round($dh, 2)];
    }

    public static function insideBlob(float $x, float $y): bool
    {
        $poly = self::blobPolygon();
        $in = false;
        $n = count($poly);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$xi, $yi] = $poly[$i];
            [$xj, $yj] = $poly[$j];
            if (($yi > $y) !== ($yj > $y) && $x < ($xj - $xi) * ($y - $yi) / (($yj - $yi) ?: 1e-9) + $xi) {
                $in = ! $in;
            }
        }

        return $in;
    }
}
