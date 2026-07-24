<?php

namespace App\Http\Controllers;

use App\Models\Friendship;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(private ChatService $chat) {}

    /** Identitas chat pemain (untuk panel). */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user_id' => $user->id,
            'name' => $user->displayName(),
            'can_chat' => $user->character !== null,
        ]);
    }

    public function world(Request $request): JsonResponse
    {
        $this->ensureCharacter($request);

        return response()->json(['messages' => $this->chat->worldMessages()]);
    }

    public function postWorld(Request $request): JsonResponse
    {
        $this->ensureCharacter($request);
        $data = $request->validate(['body' => ['required', 'string', 'max:500']]);
        $body = trim($data['body']);
        abort_if($body === '', 422, 'Pesan kosong.');

        $message = $this->chat->postWorld($request->user(), $body);

        return response()->json(['message' => $this->one($message, $request->user()->displayName())]);
    }

    public function dm(Request $request, Friendship $friendship): JsonResponse
    {
        $this->ensureCharacter($request);
        $this->authorizeDm($request, $friendship);

        return response()->json(['messages' => $this->chat->dmMessages($friendship)]);
    }

    public function postDm(Request $request, Friendship $friendship): JsonResponse
    {
        $this->ensureCharacter($request);
        $this->authorizeDm($request, $friendship);
        $data = $request->validate(['body' => ['required', 'string', 'max:500']]);
        $body = trim($data['body']);
        abort_if($body === '', 422, 'Pesan kosong.');

        $message = $this->chat->postDm($request->user(), $friendship, $body);

        return response()->json(['message' => $this->one($message, $request->user()->displayName())]);
    }

    private function authorizeDm(Request $request, Friendship $friendship): void
    {
        abort_unless($friendship->status === 'accepted' && $friendship->involves($request->user()), 403, 'Bukan temanmu.');
    }

    private function ensureCharacter(Request $request): void
    {
        abort_unless($request->user()->character, 403, 'Buat karakter dulu untuk mengobrol.');
    }

    /** @return array<string, mixed> */
    private function one(\App\Models\ChatMessage $m, string $name): array
    {
        return ['id' => $m->id, 'user_id' => $m->user_id, 'name' => $name, 'body' => $m->body, 'at' => $m->created_at?->toIso8601String()];
    }
}
