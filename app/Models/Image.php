<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A picture on a project page. Priority 1–3 makes it one of the large ones; null leaves it ordinary. */
class Image extends Model
{
    protected $table = 'project_images';

    protected $fillable = ['project_id', 'path', 'preview_path', 'width', 'height', 'bytes', 'priority', 'sort', 'crop_x', 'crop_y', 'crop_zoom'];

    protected function casts(): array
    {
        return ['priority' => 'integer', 'width' => 'integer', 'height' => 'integer', 'crop_x' => 'float', 'crop_y' => 'float', 'crop_zoom' => 'float'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function present(): array
    {
        return [
            'id' => $this->id, 'width' => $this->width, 'height' => $this->height, 'bytes' => $this->bytes,
            'priority' => $this->priority,
            'crop' => $this->crop_zoom === null ? null : ['x' => $this->crop_x, 'y' => $this->crop_y, 'zoom' => $this->crop_zoom],
            'url' => route('studio.images.show', ['image' => $this->id, 'size' => 'preview', 'v' => $this->updated_at?->timestamp]),
        ];
    }
}
