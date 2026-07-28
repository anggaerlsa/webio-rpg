<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ForumPost extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_first' => 'boolean',
            'appreciations' => 'integer',
            'edited_at' => 'datetime',
        ];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(ForumTopic::class, 'topic_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Pesan yang dikutip (satu tingkat, tanpa pohon bersarang). */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(ForumPost::class, 'reply_to_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(ForumVote::class, 'post_id');
    }
}
