<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RankRule extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'missions_required' => 'integer',
        ];
    }
}
