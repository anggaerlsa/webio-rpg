<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Models\City;
use App\Models\Item;
use App\Models\Monster;
use App\Models\Quest;
use App\Models\Spell;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/Dashboard', [
            'stats' => [
                'cities' => City::count(),
                'items' => Item::count(),
                'spells' => Spell::count(),
                'quests' => Quest::count(),
                'monsters' => Monster::count(),
                'players' => Character::count(),
            ],
        ]);
    }
}
