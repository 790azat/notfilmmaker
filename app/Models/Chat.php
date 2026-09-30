<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chat extends Model
{
    protected $guarded = ['id'];

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->orderBy('id');
    }

    /** Короткая метка разговора для Telegram: #a1b2c3. */
    public function tag(): string
    {
        return '#'.substr($this->sid, 0, 6);
    }
}
