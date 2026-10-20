<?php

namespace App\Models;

use App\Support\Collage;
use App\Support\PageGeometry;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One project page of the 10 Event profile: a draft until approved, then a page of the profile PDF. */
class Project extends Model
{
    use HasUlids;

    protected $table = 'projects';

    protected $fillable = [
        'client', 'title', 'description', 'details_url', 'details_label', 'stats', 'status', 'layout_seed', 'layout',
        'page_path', 'thumb_path', 'rendered_at', 'profile_version_id', 'profile_page', 'profile_stale', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'stats' => 'array', 'layout' => 'array', 'profile_stale' => 'boolean',
            'rendered_at' => 'datetime', 'approved_at' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function images(): HasMany
    {
        return $this->hasMany(Image::class)->orderBy('sort')->orderBy('id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(Version::class, 'profile_version_id');
    }

    public function dir(): string
    {
        return 'projects/'.$this->public_id;
    }

    /** True while the page is a page of the profile as it stands now. */
    public function inProfile(): bool
    {
        return $this->profile_page !== null && $this->profile_version_id !== null
            && $this->profile_version_id === Version::current()?->id;
    }

    /** Re-tile the page from its pictures and the current seed. */
    public function relayout(): void
    {
        $images = $this->images()->get();
        $this->layout = Collage::arrange($images->map(fn (Image $i) => [
            'id' => $i->id, 'w' => $i->width, 'h' => $i->height, 'priority' => $i->priority,
        ])->all(), (int) $this->layout_seed);
    }

    /**
     * Each picture's window: the part of its cell that the white card leaves in view (the picture is drawn there
     * and nowhere else), and where the picture sits in it — scaled to cover it, on the point chosen by hand if any.
     * Cells the card hides whole are left out.
     *
     * @return list<array{id: int, x: float, y: float, w: float, h: float, cx: float, cy: float, img: array}>
     */
    public function windows(): array
    {
        $images = $this->images->keyBy('id');
        $out = [];
        foreach ($this->layout ?? [] as $c) {
            $img = $images->get($c['id']);
            $box = PageGeometry::visibleBox((float) $c['x'], (float) $c['y'], (float) $c['w'], (float) $c['h']);
            if (! $img || ! $box) {
                continue;
            }
            // a window on the page's own edge or on a neighbour reaches 0.4 pt further, so no viewer draws a seam
            foreach (['w' => 'x', 'h' => 'y'] as $size => $pos) {
                $end = $box[$pos] + $box[$size];
                if ($end >= (float) $c[$pos] + (float) $c[$size] - 0.01 && $end < ($size === 'w' ? PageGeometry::W : PageGeometry::H) - 0.01) {
                    $box[$size] += 0.4;
                }
            }
            $box = array_map(fn ($v) => round($v, 2), $box);
            $out[] = ['id' => $img->id, 'x' => $box['x'], 'y' => $box['y'], 'w' => $box['w'], 'h' => $box['h'], 'cx' => $box['cx'], 'cy' => $box['cy'],
                'img' => PageGeometry::placeImage($box, (float) $img->width, (float) $img->height, $img->crop_x, $img->crop_y, $img->crop_zoom)];
        }

        return $out;
    }

    /** What the page template needs, apart from the pictures. */
    public function content(): array
    {
        return [
            'client' => (string) $this->client,
            'title' => (string) $this->title,
            'description' => trim((string) $this->description),
            'details_url' => $this->details_url,
            'details_label' => $this->details_label,
            'stats' => array_values(array_filter($this->stats ?? [], fn ($s) => filled($s['value'] ?? null) || filled($s['label'] ?? null))),
        ];
    }

    public static function defaults(): array
    {
        return ['stats' => PageGeometry::DEFAULT_STATS, 'details_label' => 'لتفاصيل أكثر'];
    }

    /** The printed page's picture; before the first print, the page's main picture stands in. */
    public function thumbUrl(): ?string
    {
        if ($this->thumb_path) {
            return route('studio.projects.thumb', ['project' => $this->public_id, 'v' => $this->rendered_at?->timestamp]);
        }
        $cover = $this->images()->orderByRaw('priority is null')->orderBy('priority')->orderBy('sort')->first();

        return $cover ? route('studio.images.show', ['image' => $cover->id, 'size' => 'preview']) : null;
    }

    public function present(bool $full = false): array
    {
        $data = [
            'id' => $this->public_id,
            'client' => $this->client,
            'title' => $this->title,
            'status' => $this->status,
            'in_profile' => $this->inProfile(),
            'profile_page' => $this->inProfile() ? $this->profile_page : null,
            'profile_stale' => $this->profile_stale,
            'thumb' => $this->thumbUrl(),
            'images_count' => $this->images_count ?? $this->images()->count(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
        ];
        if ($full) {
            $data += [
                'description' => $this->description ?? '',
                'details_url' => $this->details_url ?? '',
                'details_label' => $this->details_label ?? '',
                'stats' => $this->stats ?? [],
                'layout_seed' => $this->layout_seed,
                'images' => $this->images->map->present()->values(),
                'windows' => $this->layout ? array_map(fn ($w) => array_diff_key($w, ['img' => 1]), $this->windows()) : [],
            ];
        }

        return $data;
    }
}
