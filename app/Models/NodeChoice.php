<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NodeChoice extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'requirements' => 'array',
            'effects' => 'array',
            'order' => 'integer',
        ];
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(QuestNode::class, 'quest_node_id');
    }
}
