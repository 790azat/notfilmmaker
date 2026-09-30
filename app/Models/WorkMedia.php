<?php

namespace App\Models;

use App\Support\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkMedia extends Model
{
    protected $table = 'work_media';

    protected $guarded = ['id'];

    public function work(): BelongsTo
    {
        return $this->belongsTo(Work::class);
    }

    public function url(): string
    {
        return Media::url($this->path);
    }
}
