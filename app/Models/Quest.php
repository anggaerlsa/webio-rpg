<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quest extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'min_level' => 'integer',
            'order' => 'integer',
            'start_node_id' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(QuestNode::class);
    }

    public function startNode(): BelongsTo
    {
        return $this->belongsTo(QuestNode::class, 'start_node_id');
    }
}
