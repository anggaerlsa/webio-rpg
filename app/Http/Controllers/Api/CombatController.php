<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CombatSession;
use App\Models\QuestNode;
use App\Services\CombatService;
use App\Services\StoryEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON endpoints for the in-combat turn loop. Lives under web middleware so it
 * uses the same session auth + CSRF as the rest of the app (no Sanctum needed).
 * The client only ever sends {node_id} / {session_id, question_id, option_index};
 * all HP, damage and rewards are computed server-side.
 */
class CombatController extends Controller
{
    public function __construct(
        private CombatService $combat,
        private StoryEngine $story,
    ) {}

    public function start(Request $request): JsonResponse
    {
        $character = $request->user()->character;
        abort_unless($character, 403);

        $data = $request->validate(['node_id' => ['required', 'integer']]);
        $node = QuestNode::with('monster')->find($data['node_id']);
        abort_unless($node, 404);

        // The node must be the player's CURRENT scene (can't start arbitrary fights).
        $save = $this->story->save($character);
        if ($save->current_node_key !== $node->key || (int) $save->quest_id !== (int) $node->quest_id) {
            abort(422, 'You are not at this encounter.');
        }

        return response()->json($this->combat->start($character, $node));
    }

    public function act(Request $request): JsonResponse
    {
        $character = $request->user()->character;
        abort_unless($character, 403);

        $data = $request->validate([
            'session_id' => ['required', 'integer'],
            'attack_kind' => ['required', 'string', 'in:skill,spell'],
            'attack_id' => ['required', 'integer'],
        ]);

        $session = CombatSession::find($data['session_id']);
        abort_unless($session && (int) $session->character_id === (int) $character->id, 404);

        return response()->json($this->combat->act($session, $data['attack_kind'], (int) $data['attack_id']));
    }

    public function useItem(Request $request): JsonResponse
    {
        $character = $request->user()->character;
        abort_unless($character, 403);

        $data = $request->validate([
            'session_id' => ['required', 'integer'],
            'item_id' => ['required', 'integer'],
        ]);

        $session = CombatSession::find($data['session_id']);
        abort_unless($session && (int) $session->character_id === (int) $character->id, 404);

        return response()->json($this->combat->useItem($session, (int) $data['item_id']));
    }

    public function show(Request $request, CombatSession $session): JsonResponse
    {
        $character = $request->user()->character;
        abort_unless($character && (int) $session->character_id === (int) $character->id, 404);

        return response()->json($this->combat->view($session, $character));
    }
}
