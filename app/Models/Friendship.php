<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Friendship extends Model
{
    protected $guarded = [];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function addressee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'addressee_id');
    }

    /** Apakah user terlibat dalam pertemanan ini. */
    public function involves(User $user): bool
    {
        return (int) $this->requester_id === (int) $user->id || (int) $this->addressee_id === (int) $user->id;
    }

    /** Lawan bicara dari sudut pandang $user. */
    public function other(User $user): ?User
    {
        if ((int) $this->requester_id === (int) $user->id) {
            return $this->addressee;
        }
        if ((int) $this->addressee_id === (int) $user->id) {
            return $this->requester;
        }

        return null;
    }

    /** Scope: pertemanan yang melibatkan user (arah mana pun). */
    public function scopeInvolving(Builder $q, User $user): Builder
    {
        return $q->where(fn (Builder $w) => $w->where('requester_id', $user->id)->orWhere('addressee_id', $user->id));
    }
}
