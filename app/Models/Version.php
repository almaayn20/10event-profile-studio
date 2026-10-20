<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One state of the company profile PDF. The newest row is the current profile. */
class Version extends Model
{
    protected $table = 'profile_versions';

    protected $fillable = ['path', 'name', 'pages', 'bytes', 'width', 'height', 'source', 'note', 'project_id'];

    protected function casts(): array
    {
        return ['width' => 'float', 'height' => 'float'];
    }

    public static function current(): ?self
    {
        return static::latest('id')->first();
    }

    /** Where the small page pictures of this version live (p-001.jpg …). */
    public function thumbsDir(): string
    {
        return 'versions/'.$this->id.'/pages';
    }

    public function present(): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'pages' => $this->pages, 'bytes' => $this->bytes,
            'source' => $this->source, 'note' => $this->note, 'project_id' => $this->project_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
