<?php

namespace App\Http\Controllers;

use App\Models\Friendship;
use App\Models\User;
use App\Services\FriendService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FriendController extends Controller
{
    public function __construct(private FriendService $friends) {}

    /** Daftar teman + permintaan masuk/keluar. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'friends' => $this->friends->friends($user),
            'incoming' => $this->friends->incoming($user),
            'outgoing' => $this->friends->outgoing($user),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:50']]);

        return response()->json(['results' => $this->friends->search($request->user(), $data['q'] ?? '')]);
    }

    public function request(Request $request): JsonResponse
    {
        $data = $request->validate(['user_id' => ['required', 'integer']]);
        $target = User::find($data['user_id']);
        if (! $target) {
            return response()->json(['message' => 'Pemain tidak ditemukan.'], 422);
        }

        try {
            $this->friends->sendRequest($request->user(), $target);
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        }

        return response()->json(['ok' => true]);
    }

    public function accept(Request $request, Friendship $friendship): JsonResponse
    {
        try {
            $this->friends->accept($request->user(), $friendship);
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        }

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, Friendship $friendship): JsonResponse
    {
        try {
            $this->friends->remove($request->user(), $friendship);
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        }

        return response()->json(['ok' => true]);
    }
}
