<?php

namespace App\Http\Controllers;

use App\Exceptions\UserFacingException;
use App\Models\Project;
use App\Models\Version;
use App\Services\Studio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/** Project pages: written as drafts, approved into the profile at a chosen page, and edited at any time after. */
class ProjectController extends Controller
{
    public function __construct(private Studio $studio) {}

    public function index()
    {
        $current = Version::current();

        return Inertia::render('Index', [
            'projects' => Project::withCount('images')->orderByDesc('updated_at')->get()->map->present()->values(),
            'profile' => $current ? $current->present() + ['thumb' => route('studio.profile.page', [$current->id, 1])] : null,
        ]);
    }

    public function store()
    {
        $project = Project::create(Project::defaults() + ['layout_seed' => random_int(1, 999_999)]);

        return redirect()->route('studio.projects.edit', $project);
    }

    public function edit(Project $project)
    {
        $project->load('images');
        $current = Version::current();

        return Inertia::render('Project', [
            'project' => $project->present(true),
            'profile' => $current ? $current->present() + [
                'pages_urls' => collect(range(1, $current->pages))->map(fn ($n) => route('studio.profile.page', [$current->id, $n]))->all(),
            ] : null,
            'suggestedAt' => $this->suggestedAt($current),
            'defaults' => Project::defaults(),
        ]);
    }

    /**
     * Where a new project page most likely goes: right after the last project page already in the profile, or else
     * just before the closing page.
     */
    private function suggestedAt(?Version $current): int
    {
        if (! $current) {
            return 1;
        }
        $last = Project::where('profile_version_id', $current->id)->max('profile_page');

        return $last ? min($last + 1, $current->pages + 1) : max(1, $current->pages);
    }

    public function update(Request $request, Project $project)
    {
        $project->fill($this->validated($request));
        if ($project->isDirty() && $project->inProfile()) {
            $project->profile_stale = true;
        }
        $project->save();

        return back()->with('success', 'ui.studio.saved');
    }

    /** The page as it would print, from the form as it stands (saved or not), for the live preview. */
    public function preview(Request $request, Project $project)
    {
        $project->load('images');
        if (! $project->layout) {
            $project->relayout();
        }
        $draft = (clone $project)->fill($this->validated($request));

        return response($this->studio->html($project, false, $draft->content()))
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->header('Cache-Control', 'no-store');
    }

    /** Another arrangement of the same pictures. */
    public function shuffle(Project $project)
    {
        $project->layout_seed = random_int(1, 999_999);
        $project->relayout();
        $this->touched($project);

        return back();
    }

    public function destroy(Project $project)
    {
        if ($project->inProfile()) {
            $this->studio->remove($project);
        }
        foreach ($project->images as $image) {
            $this->studio->deleteImage($image);
        }
        Storage::disk('local')->deleteDirectory($project->dir());
        $project->delete();

        return redirect()->route('studio.index')->with('success', 'ui.studio.deleted');
    }

    /** Approve: the page is printed and placed in the profile so that it becomes page `at`. */
    public function approve(Request $request, Project $project)
    {
        $data = $request->validate(['at' => ['required', 'integer', 'min:1', 'max:2000']]);
        $this->requireImages($project);
        $this->studio->insert($project, (int) $data['at']);
        $project->forceFill(['status' => 'approved', 'approved_at' => now()])->save();

        return back()->with('success', 'ui.studio.approved');
    }

    /** Put the edited page in place of its old one in the profile. */
    public function refresh(Project $project)
    {
        $this->requireImages($project);
        $this->studio->update($project);

        return back()->with('success', 'ui.studio.refreshed');
    }

    /** Back to a draft: the page leaves the profile. */
    public function unapprove(Project $project)
    {
        if ($project->inProfile()) {
            $this->studio->remove($project);
        }
        $project->forceFill(['status' => 'draft', 'approved_at' => null, 'profile_version_id' => null, 'profile_page' => null])->save();

        return back()->with('success', 'ui.studio.unapproved');
    }

    /** The page is already in an uploaded profile: remember on which page, change nothing in the file. */
    public function link(Request $request, Project $project)
    {
        $data = $request->validate(['page' => ['required', 'integer', 'min:1', 'max:2000']]);
        $this->studio->link($project, (int) $data['page']);
        $project->forceFill(['status' => 'approved', 'approved_at' => $project->approved_at ?? now()])->save();

        return back()->with('success', 'ui.studio.linked');
    }

    public function thumb(Project $project)
    {
        abort_unless($project->thumb_path && Storage::disk('local')->exists($project->thumb_path), 404);

        return response()->file(Storage::disk('local')->path($project->thumb_path), ['Cache-Control' => 'private, max-age=86400']);
    }

    /** This one page as a PDF, printed now. */
    public function pdf(Project $project)
    {
        $this->requireImages($project);
        $this->studio->render($project);
        $name = trim(($project->client ?: 'مشروع').' - '.($project->title ?: $project->public_id)).'.pdf';

        return response()->download(Storage::disk('local')->path($project->page_path), str_replace(['/', '\\'], '-', $name));
    }

    private function requireImages(Project $project): void
    {
        if (! $project->images()->exists()) {
            throw new UserFacingException('portfolio_no_images');
        }
    }

    /** A change to the pictures or their arrangement: the printed page is out of date, and so is the profile's copy. */
    private function touched(Project $project): void
    {
        if ($project->inProfile()) {
            $project->profile_stale = true;
        }
        $project->save();
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'client' => ['nullable', 'string', 'max:160'],
            'title' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'details_url' => ['nullable', 'url:http,https', 'max:600'],
            'details_label' => ['nullable', 'string', 'max:60'],
            'stats' => ['nullable', 'array', 'max:3'],
            'stats.*.value' => ['nullable', 'string', 'max:24'],
            'stats.*.label' => ['nullable', 'string', 'max:30'],
        ]);
        $data['stats'] = array_values(array_map(fn ($s) => ['value' => trim((string) ($s['value'] ?? '')), 'label' => trim((string) ($s['label'] ?? ''))], $data['stats'] ?? []));
        foreach (['client', 'title'] as $k) {
            $data[$k] = trim((string) ($data[$k] ?? ''));
        }

        return $data;
    }
}
