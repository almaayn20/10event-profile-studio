<?php

namespace App\Support;

/**
 * Tiles a project page with its pictures, edge to edge and without gaps, the way the profile's designer did by hand.
 *
 * The page is cut again and again into two (a cutting tree) until every picture has its own cell. A cell's area
 * follows the picture's weight: the first-priority picture gets the most room, then the second and third, then the
 * rest share what is left equally. Trees are improved step by step from a few random starts and the best is kept,
 * where "best" means:
 *  - every picture is cropped as little as possible (its cell has nearly its own proportions),
 *  - the priority pictures stay out from behind the white text card,
 *  - no ordinary picture disappears almost completely behind the card, and none is a sliver.
 * The search is seeded, so the same pictures and the same seed always give the same page (preview = PDF);
 * "rearrange" simply asks for another seed.
 */
final class Collage
{
    private const WEIGHT = [1 => 6.0, 2 => 3.6, 3 => 2.4, 0 => 1.0];

    private const GRID = 4.0;   // the hidden-area grid, in points

    private const RESTARTS = 6;

    /**
     * @param  list<array{id: int, w: int|float, h: int|float, priority: int|null}>  $images
     * @return list<array{id: int, x: float, y: float, w: float, h: float}>
     */
    public static function arrange(array $images, int $seed, int $steps = 2400): array
    {
        $images = array_values($images);
        $n = count($images);
        if ($n === 0) {
            return [];
        }
        $page = [0.0, 0.0, PageGeometry::W, PageGeometry::H];
        if ($n === 1) {
            return [self::cell($images[0]['id'], $page)];
        }

        $items = array_map(fn ($im) => [
            'id' => (int) $im['id'],
            'ar' => ($im['w'] ?? 0) > 0 && ($im['h'] ?? 0) > 0 ? $im['w'] / $im['h'] : 1.5,
            'p' => (int) ($im['priority'] ?? 0),
            'wt' => self::WEIGHT[(int) ($im['priority'] ?? 0)] ?? 1.0,
        ], $images);

        mt_srand($seed);
        $best = null;
        $bestScore = INF;
        $per = max(200, intdiv($steps, self::RESTARTS));
        for ($r = 0; $r < self::RESTARTS; $r++) {
            $order = range(0, $n - 1);
            self::shuffle($order);
            $tree = self::randomTree($order);
            $score = self::score(self::cells($tree, $items, $page));
            for ($i = 0; $i < $per; $i++) {
                $temp = 2.0 * (1 - $i / $per) + 0.01;
                $next = self::mutate($tree, $n);
                $cells = self::cells($next, $items, $page);
                $s = self::score($cells);
                if ($s < $score || mt_rand() / mt_getrandmax() < exp(($score - $s) / $temp)) {
                    [$tree, $score] = [$next, $s];
                    if ($s < $bestScore) {
                        [$best, $bestScore] = [$cells, $s];
                    }
                }
            }
            if ($best === null) {
                [$best, $bestScore] = [self::cells($tree, $items, $page), $score];
            }
        }
        mt_srand();

        // reading order (top to bottom, right to left) keeps the stored layout stable and easy to diff
        usort($best, fn ($a, $b) => [round($a['rect'][1]), -round($a['rect'][0])] <=> [round($b['rect'][1]), -round($b['rect'][0])]);

        return array_map(fn ($c) => self::cell($c['item']['id'], $c['rect']), $best);
    }

    /**
     * A leaf holds a picture's index; a node cuts its rectangle side by side (`v`) or one above the other, giving its
     * first part a share of the area from the pictures' weights, nudged by `r`.
     */
    private static function randomTree(array $leaves): array
    {
        if (count($leaves) === 1) {
            return ['leaf' => $leaves[0]];
        }
        $k = mt_rand(1, count($leaves) - 1);

        return ['v' => mt_rand(0, 1) === 1, 'r' => 1.0, 'a' => self::randomTree(array_slice($leaves, 0, $k)), 'b' => self::randomTree(array_slice($leaves, $k))];
    }

    private static function cells(array $tree, array $items, array $rect): array
    {
        $out = [];
        self::place($tree, $items, $rect, $out);

        return $out;
    }

    private static function place(array $tree, array $items, array $rect, array &$out): void
    {
        if (isset($tree['leaf'])) {
            $out[] = ['item' => $items[$tree['leaf']], 'rect' => $rect];

            return;
        }
        [$x, $y, $w, $h] = $rect;
        $wa = self::weight($tree['a'], $items) * $tree['r'];
        $ratio = $wa / ($wa + self::weight($tree['b'], $items));
        if ($tree['v']) {
            $wA = $w * $ratio;      // the first part on the right, as the page is read
            self::place($tree['a'], $items, [$x + $w - $wA, $y, $wA, $h], $out);
            self::place($tree['b'], $items, [$x, $y, $w - $wA, $h], $out);
        } else {
            $hA = $h * $ratio;
            self::place($tree['a'], $items, [$x, $y, $w, $hA], $out);
            self::place($tree['b'], $items, [$x, $y + $hA, $w, $h - $hA], $out);
        }
    }

    private static function weight(array $tree, array $items): float
    {
        return isset($tree['leaf']) ? $items[$tree['leaf']]['wt'] : self::weight($tree['a'], $items) + self::weight($tree['b'], $items);
    }

    /** One small change: swap two pictures, turn a cut, move a cut, swap a cut's halves, or move a picture elsewhere. */
    private static function mutate(array $tree, int $n): array
    {
        $kind = mt_rand(0, 4);
        if ($kind === 0) {
            $leaves = self::leaves($tree);
            $i = $leaves[mt_rand(0, $n - 1)];
            $j = $leaves[mt_rand(0, $n - 1)];

            return self::mapLeaves($tree, [$i => $j, $j => $i]);
        }
        if ($kind === 4 && $n > 2) {
            $leaves = self::leaves($tree);
            $rest = self::without($tree, $leaves[mt_rand(0, $n - 1)], $leaf);

            return self::graft($rest, mt_rand(0, self::nodeCount($rest) - 1), $leaf);
        }

        return self::editNode($tree, mt_rand(0, $n - 2), $kind);     // a tree of n leaves has n − 1 cuts
    }

    private static function leaves(array $t): array
    {
        return isset($t['leaf']) ? [$t['leaf']] : array_merge(self::leaves($t['a']), self::leaves($t['b']));
    }

    private static function mapLeaves(array $t, array $map): array
    {
        if (isset($t['leaf'])) {
            return ['leaf' => $map[$t['leaf']] ?? $t['leaf']];
        }
        $t['a'] = self::mapLeaves($t['a'], $map);
        $t['b'] = self::mapLeaves($t['b'], $map);

        return $t;
    }

    private static function nodeCount(array $t): int
    {
        return isset($t['leaf']) ? 1 : 1 + self::nodeCount($t['a']) + self::nodeCount($t['b']);
    }

    /** Change the target-th cut (pre-order): turn it, move it, or swap its halves. */
    private static function editNode(array $t, int $target, int $kind, int &$seen = 0): array
    {
        if (isset($t['leaf'])) {
            return $t;
        }
        if ($seen++ === $target) {
            if ($kind === 1) {
                $t['v'] = ! $t['v'];
            } elseif ($kind === 2) {
                $t['r'] = max(0.6, min(1.65, $t['r'] * (0.8 + mt_rand(0, 1000) / 1000 * 0.45)));
            } else {
                [$t['a'], $t['b'], $t['r']] = [$t['b'], $t['a'], 1 / $t['r']];
            }

            return $t;
        }
        $t['a'] = self::editNode($t['a'], $target, $kind, $seen);
        $t['b'] = self::editNode($t['b'], $target, $kind, $seen);

        return $t;
    }

    /** The tree without the given leaf (its sibling takes its parent's place); `$taken` receives the leaf. */
    private static function without(array $t, int $leaf, ?int &$taken = null): array
    {
        $taken = $leaf;
        if (isset($t['leaf'])) {
            return $t;
        }
        if (($t['a']['leaf'] ?? null) === $leaf) {
            return $t['b'];
        }
        if (($t['b']['leaf'] ?? null) === $leaf) {
            return $t['a'];
        }
        $t['a'] = self::without($t['a'], $leaf);
        $t['b'] = self::without($t['b'], $leaf);

        return $t;
    }

    /** Put a leaf beside the target-th node (pre-order), under a new cut. */
    private static function graft(array $t, int $target, int $leaf, int &$seen = 0): array
    {
        if ($seen++ === $target) {
            $new = ['leaf' => $leaf];
            $v = mt_rand(0, 1) === 1;

            return mt_rand(0, 1) ? ['v' => $v, 'r' => 1.0, 'a' => $new, 'b' => $t] : ['v' => $v, 'r' => 1.0, 'a' => $t, 'b' => $new];
        }
        if (! isset($t['leaf'])) {
            $t['a'] = self::graft($t['a'], $target, $leaf, $seen);
            $t['b'] = self::graft($t['b'], $target, $leaf, $seen);
        }

        return $t;
    }

    private static function score(array $cells): float
    {
        $s = 0.0;
        foreach ($cells as $c) {
            [$x, $y, $w, $h] = $c['rect'];
            $it = $c['item'];
            $crop = log(($w / $h) / $it['ar']);
            $s += 2.0 * sqrt($it['wt']) * $crop * $crop;

            $hidden = self::hiddenShare($x, $y, $w, $h);
            if ($it['p'] > 0) {
                $s += 6.0 * $it['wt'] * $hidden;              // a chosen picture must be seen
            } elseif ($hidden > 0.6) {
                $s += 12.0 * ($hidden - 0.6);                 // an ordinary one may tuck behind the card, but not vanish
            }
            $short = min($w, $h);
            if ($short < 90) {
                $s += (90 - $short) / 90 * 5.0;               // no slivers
            }
        }

        return $s;
    }

    /** Share of a rectangle covered by the text card, from a summed-area table of the card. */
    private static function hiddenShare(float $x, float $y, float $w, float $h): float
    {
        static $sat = null;
        $g = self::GRID;
        $cols = (int) (PageGeometry::W / $g);
        $rows = (int) (PageGeometry::H / $g);
        if ($sat === null) {
            $sat = array_fill(0, ($rows + 1) * ($cols + 1), 0);
            for ($r = 1; $r <= $rows; $r++) {
                $run = 0;
                for ($c = 1; $c <= $cols; $c++) {
                    $run += PageGeometry::insideBlob(($c - 0.5) * $g, ($r - 0.5) * $g) ? 1 : 0;
                    $sat[$r * ($cols + 1) + $c] = $sat[($r - 1) * ($cols + 1) + $c] + $run;
                }
            }
        }
        $c0 = max(0, min($cols, (int) round($x / $g)));
        $c1 = max(0, min($cols, (int) round(($x + $w) / $g)));
        $r0 = max(0, min($rows, (int) round($y / $g)));
        $r1 = max(0, min($rows, (int) round(($y + $h) / $g)));
        $area = ($c1 - $c0) * ($r1 - $r0);
        if ($area <= 0) {
            return 0.0;
        }
        $at = fn ($r, $c) => $sat[$r * ($cols + 1) + $c];

        return ($at($r1, $c1) - $at($r0, $c1) - $at($r1, $c0) + $at($r0, $c0)) / $area;
    }

    /** Fisher–Yates on the seeded generator, so a seed always means the same order. */
    private static function shuffle(array &$a): void
    {
        for ($i = count($a) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$a[$i], $a[$j]] = [$a[$j], $a[$i]];
        }
    }

    /** Edges are rounded, not sizes, so neighbours share the very same edge and the cells still add up to the page. */
    private static function cell(int $id, array $rect): array
    {
        [$x0, $y0] = [round($rect[0], 2), round($rect[1], 2)];
        [$x1, $y1] = [round($rect[0] + $rect[2], 2), round($rect[1] + $rect[3], 2)];

        return ['id' => $id, 'x' => $x0, 'y' => $y0, 'w' => round($x1 - $x0, 2), 'h' => round($y1 - $y0, 2)];
    }
}
