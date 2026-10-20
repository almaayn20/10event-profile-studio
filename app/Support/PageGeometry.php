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
