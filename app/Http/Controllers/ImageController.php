<?php

namespace App\Http\Controllers;

use App\Exceptions\UserFacingException;
use App\Models\Image;
use App\Models\Project;
use App\Services\Studio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** The pictures of a page: any number of them, and up to three marked 1 · 2 · 3 to be the large ones. */
class ImageController extends Controller
{
    public function __construct(private Studio $studio) {}

    public function store(Request $request, Project $project)
    {
        $max = (int) config('studio.image_max_mb') * 1024;
        $request->validate(['images' => ['required', 'array', 'max:40'], 'images.*' => ['file', 'max:'.$max]]);
        foreach ($request->file('images') as $file) {
            $this->studio->storeImage($project, $file);
        }
        $this->arranged($project);

        return back();
    }

    /** Give a picture priority 1, 2 or 3 (taking it from whichever picture had it), or make it ordinary again. */
    public function priority(Request $request, Image $image)
    {
        $data = $request->validate(['priority' => ['nullable', 'integer', 'in:1,2,3']]);
        $priority = $data['priority'] ?? null;
        DB::transaction(function () use ($image, $priority) {
            if ($priority) {
                Image::where('project_id', $image->project_id)->where('priority', $priority)->where('id', '!=', $image->id)->update(['priority' => null]);
            }
            $image->update(['priority' => $priority]);
        });
        $this->arranged($image->project);

        return back();
    }

    public function destroy(Image $image)
    {
        $project = $image->project;
        $this->studio->deleteImage($image);
        $this->arranged($project);

        return back();
    }

    public function show(Request $request, Image $image)
    {
        $path = $request->query('size') === 'preview' && $image->preview_path ? $image->preview_path : $image->path;
        if (! Storage::disk('local')->exists($path)) {
            throw new UserFacingException('not_found', [], 404);
        }

        return response()->file(Storage::disk('local')->path($path), ['Cache-Control' => 'private, max-age=604800']);
    }

    private function arranged(Project $project): void
    {
        $project->unsetRelation('images');
        $project->relayout();
        if ($project->inProfile()) {
            $project->profile_stale = true;
        }
        $project->touch();
        $project->save();
    }
}
