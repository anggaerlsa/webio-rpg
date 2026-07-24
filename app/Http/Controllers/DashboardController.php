<?php

namespace App\Http\Controllers;

use App\Services\StoryEngine;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private StoryEngine $story) {}

    public function index(Request $request): Response
    {
        $character = $request->user()->character;

        $resume = null;
        if ($character && $character->active_quest_id && $character->activeQuest) {
            $save = $character->saves()->where('slot', 1)->first();
            $resume = [
                'quest_slug' => $character->activeQuest->slug,
                'quest_title' => $character->activeQuest->title,
                'node_key' => $save?->current_node_key,
                'last_played_at' => $save?->last_played_at?->locale('id')->diffForHumans(),
            ];
        }

        return Inertia::render('Dashboard', [
            'character' => $character ? $this->story->characterState($character) : null,
            'resume' => $resume,
            'needsOnboarding' => $character && ! $character->affiliation,
        ]);
    }
}
