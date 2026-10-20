<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Version;
use App\Services\Studio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/** The company profile PDF and its history: every upload and every approved page makes a new version. */
class ProfileController extends Controller
{
    public function __construct(private Studio $studio) {}

    public function index()
    {
        $versions = Version::latest('id')->get();
        $current = $versions->first();
        $projects = Project::whereNotNull('profile_page')->get()->filter->inProfile();

        return Inertia::render('Profile', [
            'versions' => $versions->map->present()->values(),
            'current' => $current ? $current->present() + [
                'pages_urls' => collect(range(1, $current->pages))->map(fn ($n) => route('studio.profile.page', [$current->id, $n]))->all(),
            ] : null,
            // which page of the current profile is which project
            'placed' => $projects->mapWithKeys(fn (Project $p) => [$p->profile_page => ['id' => $p->public_id, 'client' => $p->client, 'title' => $p->title, 'stale' => $p->profile_stale]]),
            'maxMb' => (int) config('studio.profile_max_mb'),
        ]);
    }

    public function store(Request $request)
    {
        $max = (int) config('studio.profile_max_mb') * 1024;
        $data = $request->validate(['file' => ['required', 'file', 'max:'.$max], 'note' => ['nullable', 'string', 'max:200']]);
        $this->studio->uploadProfile($data['file'], $data['note'] ?? null);

        return back()->with('success', 'ui.studio.profile_uploaded');
    }

    public function download(Version $version)
    {
        return response()->download(Storage::disk('local')->path($version->path), $version->name);
    }

    public function restore(Version $version)
    {
        $this->studio->restore($version);

        return back()->with('success', 'ui.studio.profile_restored');
    }

    public function page(Version $version, int $page)
    {
        $path = $version->thumbsDir().'/p-'.str_pad((string) $page, 3, '0', STR_PAD_LEFT).'.jpg';
        abort_unless(Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), ['Cache-Control' => 'private, max-age=31536000, immutable']);
    }
}
