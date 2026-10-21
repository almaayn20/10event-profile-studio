<?php

namespace App\Services;

use App\Exceptions\UserFacingException;
use App\Models\Image;
use App\Models\Project;
use App\Models\Version;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Everything the profile studio does to files: takes pictures in (lighter, upright), prints a project page,
 * and edits the company profile — each edit a new version, so nothing is ever lost.
 */
class Studio
{
    /** Longest side a stored picture keeps; a full-page picture prints at about 2.5× its size at this width. */
    private const MAX_SIDE = 2400;

    private const JPEG_QUALITY = 82;

    public function __construct(private PdfEngine $engine) {}

    private static function disk()
    {
        return Storage::disk('local');
    }

    private static function abs(string $path): string
    {
        return self::disk()->path($path);
    }

    // ── pictures ────────────────────────────────────────────────────────────────────────────────────────────────

    public function storeImage(Project $project, UploadedFile $file): Image
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: '');
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new UserFacingException('file_type_not_allowed', ['types' => 'JPG · PNG · WEBP']);
        }
        $src = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $src) {
            throw new UserFacingException('portfolio_image_unreadable');
        }
        $src = self::upright($src, $file->getRealPath(), $ext);

        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, self::MAX_SIDE / max($w, $h));
        $name = Str::lower(Str::random(16));
        $dir = $project->dir().'/images';
        self::disk()->makeDirectory($dir);

        $full = self::resized($src, $scale);
        $path = $dir.'/'.$name.'.jpg';
        imagejpeg($full, self::abs($path), self::JPEG_QUALITY);
        $fw = imagesx($full);
        $fh = imagesy($full);

        $preview = self::resized($full, min(1, 960 / max($fw, $fh)));
        $previewPath = $dir.'/'.$name.'_960.jpg';
        imagejpeg($preview, self::abs($previewPath), 76);
        imagedestroy($src);
        if ($full !== $src) {
            imagedestroy($full);
        }
        imagedestroy($preview);

        return $project->images()->create([
            'path' => $path, 'preview_path' => $previewPath, 'width' => $fw, 'height' => $fh,
            'bytes' => (int) filesize(self::abs($path)),
            'sort' => (int) $project->images()->max('sort') + 1,
        ]);
    }

    public function deleteImage(Image $image): void
    {
        self::disk()->delete(array_filter([$image->path, $image->preview_path]));
        $image->delete();
    }

    /** Phone pictures arrive sideways with a note saying so; store them the way they are seen. */
    private static function upright(\GdImage $img, string $path, string $ext): \GdImage
    {
        if (! in_array($ext, ['jpg', 'jpeg'], true) || ! function_exists('exif_read_data')) {
            return $img;
        }
        $o = (int) (@exif_read_data($path)['Orientation'] ?? 1);
        $out = match ($o) {
            3 => imagerotate($img, 180, 0),
            6 => imagerotate($img, -90, 0),
            8 => imagerotate($img, 90, 0),
            default => $img,
        };
        if ($out !== $img) {
            imagedestroy($img);
        }

        return $out;
    }

    private static function resized(\GdImage $img, float $scale): \GdImage
    {
        if ($scale >= 1) {
            return $img;
        }
        $w = max(1, (int) round(imagesx($img) * $scale));
        $h = max(1, (int) round(imagesy($img) * $scale));
        $out = imagecreatetruecolor($w, $h);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));   // a transparent PNG lands on white, not black
        imagecopyresampled($out, $img, 0, 0, 0, 0, $w, $h, imagesx($img), imagesy($img));

        return $out;
    }

    // ── the page ────────────────────────────────────────────────────────────────────────────────────────────────

    /**
     * The page as HTML. For the editor the pictures and fonts are web addresses; for printing they are files on
     * disk, read by Chrome directly.
     */
    public function html(Project $project, bool $print, ?array $override = null): string
    {
        $images = $project->images->keyBy('id');
        $cells = [];
        foreach ($project->windows() as $win) {
            $img = $images->get($win['id']);
            $src = $print
                ? 'file://'.self::abs($img->path)
                : route('studio.images.show', ['image' => $img->id, 'size' => 'preview', 'v' => $img->updated_at?->timestamp]);
            $cells[] = $win + ['src' => $src];
        }

        return view('portfolio.page', [
            'picturePage' => $project->isPicturePage(),
            'project' => $override ?? $project->content(),
            'cells' => $cells,
            'fonts' => [
                'medium' => $print ? 'file://'.Fonts::path('medium') : route('studio.fonts', ['weight' => 'medium', 'v' => Fonts::version()]),
                'bold' => $print ? 'file://'.Fonts::path('bold') : route('studio.fonts', ['weight' => 'bold', 'v' => Fonts::version()]),
            ],
        ])->render();
    }

    /** Print the page to PDF (and a picture of it). */
    public function render(Project $project): void
    {
        $project->loadMissing('images');
        if (! $project->layout) {
            $project->relayout();
        }
        $dir = $project->dir();
        self::disk()->makeDirectory($dir);
        $html = self::abs($dir.'/page.html');
        File::put($html, $this->html($project, true));

        $res = $this->engine->run('page', [
            'html' => $html, 'pdf' => self::abs($dir.'/page.pdf'), 'thumb' => self::abs($dir.'/page.jpg'), 'thumb_dpi' => 60,
        ], self::abs($dir.'/work'));
        if (! ($res['ok'] ?? false)) {
            throw new UserFacingException('portfolio_render_failed', [], 500);
        }
        $project->forceFill([
            'page_path' => $dir.'/page.pdf',
            'thumb_path' => ! empty($res['thumb']) ? $dir.'/page.jpg' : null,
            'rendered_at' => now(),
        ])->save();
    }

    // ── the profile ─────────────────────────────────────────────────────────────────────────────────────────────

    /** A new profile file from the person: becomes the current version; earlier page positions no longer apply. */
    public function uploadProfile(UploadedFile $file, ?string $note): Version
    {
        if (strtolower($file->getClientOriginalExtension()) !== 'pdf') {
            throw new UserFacingException('file_type_not_allowed', ['types' => 'PDF']);
        }

        return $this->book(function () use ($file, $note) {
            $tmp = self::abs('tmp/'.Str::random(12).'.pdf');
            File::ensureDirectoryExists(dirname($tmp));
            File::copy($file->getRealPath(), $tmp);

            return $this->newVersion($tmp, 'upload', $note, null, $file->getClientOriginalName());
        });
    }

    /** Put an approved page into the profile so that it becomes page $at. */
    public function insert(Project $project, int $at): Version
    {
        return $this->book(function () use ($project, $at) {
            $current = $this->requireProfile();
            $at = max(1, min($at, $current->pages + 1));
            $this->render($project);
            $version = $this->compose($current, [['op' => 'insert', 'at' => $at, 'pdf' => self::abs($project->page_path)]], 'insert', $project);

            // every page from $at on moved one place down
            $this->carryPositions($current, $version, fn (int $p) => $p >= $at ? $p + 1 : $p, $project->id);
            $project->forceFill(['profile_version_id' => $version->id, 'profile_page' => $at, 'profile_stale' => false])->save();

            return $version;
        });
    }

    /** Swap the project's page in the profile for its latest print. */
    public function update(Project $project): Version
    {
        return $this->book(function () use ($project) {
            $current = $this->requireProfile();
            if (! $project->inProfile()) {
                throw new UserFacingException('portfolio_not_in_profile');
            }
            $this->render($project);
            $version = $this->compose($current, [['op' => 'replace', 'page' => $project->profile_page, 'pdf' => self::abs($project->page_path)]], 'update', $project);
            $this->carryPositions($current, $version, fn (int $p) => $p, $project->id);
            $project->forceFill(['profile_version_id' => $version->id, 'profile_stale' => false])->save();

            return $version;
        });
    }

    public function remove(Project $project): Version
    {
        return $this->book(function () use ($project) {
            $current = $this->requireProfile();
            if (! $project->inProfile()) {
                throw new UserFacingException('portfolio_not_in_profile');
            }
            $page = $project->profile_page;
            $version = $this->compose($current, [['op' => 'remove', 'page' => $page]], 'remove', $project);
            $this->carryPositions($current, $version, fn (int $p) => $p > $page ? $p - 1 : $p, $project->id);
            $project->forceFill(['profile_version_id' => null, 'profile_page' => null, 'profile_stale' => false])->save();

            return $version;
        });
    }

    /** Move the project's page, as it is in the profile, so that it becomes page $to; nothing is printed again. */
    public function move(Project $project, int $to): Version
    {
        return $this->book(function () use ($project, $to) {
            $current = $this->requireProfile();
            if (! $project->inProfile()) {
                throw new UserFacingException('portfolio_not_in_profile');
            }
            $from = $project->profile_page;
            $to = max(1, min($to, $current->pages));
            if ($to === $from) {
                return $current;
            }
            $version = $this->compose($current, [['op' => 'move', 'page' => $from, 'to' => $to]], 'move', $project);

            // the pages between the old place and the new one shift one place towards the old one
            $this->carryPositions($current, $version, fn (int $p) => match (true) {
                $from < $to && $p > $from && $p <= $to => $p - 1,
                $from > $to && $p >= $to && $p < $from => $p + 1,
                default => $p,
            }, $project->id);
            $project->forceFill(['profile_version_id' => $version->id, 'profile_page' => $to])->save();

            return $version;
        });
    }

    /** The page is already in the profile (it came with an uploaded file): only remember where. */
    public function link(Project $project, int $page): void
    {
        $current = $this->requireProfile();
        if ($page < 1 || $page > $current->pages) {
            throw new UserFacingException('portfolio_page_out_of_range', ['pages' => $current->pages]);
        }
        $project->forceFill(['profile_version_id' => $current->id, 'profile_page' => $page])->save();
    }

    /** An earlier version becomes the current one again (as a new version, so the history stays whole). */
    public function restore(Version $old): Version
    {
        return $this->book(function () use ($old) {
            $tmp = self::abs('tmp/'.Str::random(12).'.pdf');
            File::ensureDirectoryExists(dirname($tmp));
            File::copy(self::abs($old->path), $tmp);
            $version = $this->newVersion($tmp, 'restore', null, null, $old->name);
            Project::where('profile_version_id', $old->id)->update(['profile_version_id' => $version->id]);

            return $version;
        });
    }

    private function requireProfile(): Version
    {
        return Version::current() ?? throw new UserFacingException('portfolio_no_profile');
    }

    /** One edit of the book at a time: two people approving at once would otherwise both start from the same file. */
    private function book(callable $fn)
    {
        return Cache::lock('portfolio.book', 300)->block(120, fn () => DB::transaction($fn));
    }

    private function compose(Version $from, array $ops, string $source, Project $project): Version
    {
        $tmp = self::abs('tmp/'.Str::random(12).'.pdf');
        File::ensureDirectoryExists(dirname($tmp));
        $res = $this->engine->run('compose', ['base' => self::abs($from->path), 'out' => $tmp, 'ops' => $ops], self::abs('tmp/work'));
        if (! ($res['ok'] ?? false)) {
            $code = $res['error']['code'] ?? '';
            throw new UserFacingException(str_starts_with($code, 'portfolio_') ? $code : 'portfolio_compose_failed', [], 500);
        }
        $label = trim($project->client.' — '.$project->title, ' —');

        return $this->newVersion($tmp, $source, $label, $project->id, $from->name);
    }

    private function newVersion(string $tmpPdf, string $source, ?string $note, ?int $projectId, string $name): Version
    {
        $version = Version::create([
            'path' => '', 'name' => self::cleanName($name), 'pages' => 0, 'bytes' => 0,
            'source' => $source, 'note' => $note ? Str::limit($note, 250) : null, 'project_id' => $projectId,
        ]);
        $path = 'versions/'.$version->id.'/profile.pdf';
        self::disk()->makeDirectory(dirname($path));
        File::move($tmpPdf, self::abs($path));

        $info = $this->engine->run('inspect', ['pdf' => self::abs($path), 'thumbs_dir' => self::abs($version->thumbsDir()), 'thumb_dpi' => 18],
            self::abs('tmp/work'));
        if (! ($info['ok'] ?? false)) {
            self::disk()->deleteDirectory('versions/'.$version->id);
            $version->delete();
            $code = $info['error']['code'] ?? '';
            throw new UserFacingException($code === 'portfolio_pdf_unreadable' ? $code : 'portfolio_compose_failed');
        }
        $version->update([
            'path' => $path, 'pages' => (int) $info['pages'], 'bytes' => (int) filesize(self::abs($path)),
            'width' => $info['width'] ?? null, 'height' => $info['height'] ?? null,
        ]);

        return $version;
    }

    /** Projects that were pages of $from keep their place (moved by $move) in $to. */
    private function carryPositions(Version $from, Version $to, callable $move, int $exceptId): void
    {
        Project::where('profile_version_id', $from->id)->whereNotNull('profile_page')->where('id', '!=', $exceptId)->get()
            ->each(fn (Project $p) => $p->forceFill(['profile_version_id' => $to->id, 'profile_page' => $move($p->profile_page)])->save());
    }

    private static function cleanName(string $name): string
    {
        $name = trim(preg_replace('/[\\\\\/:*?"<>|]+/u', ' ', $name)) ?: 'profile.pdf';

        return str_ends_with(strtolower($name), '.pdf') ? $name : $name.'.pdf';
    }
}
