<?php

namespace App\Http\Controllers;

use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Services\ForumService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Balai Warta — forum diskusi permanen. Semua izin & hitungan diputuskan
 * ForumService; controller hanya memvalidasi bentuk masukan dan merender halaman.
 */
class ForumController extends Controller
{
    public function __construct(private ForumService $forum) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $categories = $this->forum->categories($user);

        // Membuka Balai Warta = menganggap semua balasan sudah terlihat.
        $this->forum->markSeen($user);

        return Inertia::render('Forum/Index', [
            'categories' => $categories,
            'is_moderator' => $this->forum->isModerator($user),
        ]);
    }

    public function category(Request $request, ForumCategory $category): Response
    {
        $user = $request->user();

        return Inertia::render('Forum/Category', [
            'category' => $this->categoryDto($category),
            'topics' => $this->forum->topics($category)->withQueryString(),
            'block_reason' => $this->forum->postBlockReason($user, $category),
            'is_moderator' => $this->forum->isModerator($user),
        ]);
    }

    public function create(Request $request, ForumCategory $category): Response
    {
        $reason = $this->forum->postBlockReason($request->user(), $category);
        if ($reason !== null) {
            return Inertia::render('Forum/Category', [
                'category' => $this->categoryDto($category),
                'topics' => $this->forum->topics($category),
                'block_reason' => $reason,
                'is_moderator' => $this->forum->isModerator($request->user()),
            ]);
        }

        return Inertia::render('Forum/Create', [
            'category' => $this->categoryDto($category),
        ]);
    }

    public function store(Request $request, ForumCategory $category): RedirectResponse
    {
        $data = $this->validateTopic($request);

        try {
            $topic = $this->forum->createTopic($request->user(), $category, $data['title'], $data['body']);
        } catch (HttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('forum.topic', $topic->slug)
            ->with('success', 'Topik dibuka.');
    }

    public function topic(Request $request, ForumTopic $topic): Response
    {
        $user = $request->user();
        $this->forum->countView($topic);

        return Inertia::render('Forum/Topic', [
            'topic' => [
                'slug' => $topic->slug,
                'title' => $topic->title,
                'is_pinned' => $topic->is_pinned,
                'is_locked' => $topic->is_locked,
                'author' => $topic->user?->displayName(),
                'created_at' => $topic->created_at?->toIso8601String(),
                'views' => $topic->views,
                'replies' => $topic->replies_count,
                'can_delete' => $this->forum->isModerator($user),
            ],
            'category' => $this->categoryDto($topic->category),
            'posts' => $this->forum->posts($topic, $user)->withQueryString(),
            'block_reason' => $this->forum->replyBlockReason($user, $topic),
            'is_moderator' => $this->forum->isModerator($user),
            'edit_window' => ForumService::EDIT_WINDOW_MINUTES,
        ]);
    }

    public function reply(Request $request, ForumTopic $topic): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:5000'],
            'reply_to_id' => ['nullable', 'integer'],
        ], $this->messages());

        try {
            $this->forum->reply($request->user(), $topic, trim($data['body']), $data['reply_to_id'] ?? null);
        } catch (HttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        // Balasan terbaru ada di halaman terakhir.
        return redirect()->route('forum.topic', [$topic->slug, 'page' => $this->lastPage($topic)]);
    }

    public function updatePost(Request $request, ForumPost $post): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:5000'],
        ], $this->messages());

        try {
            $this->forum->editPost($request->user(), $post, trim($data['body']));
        } catch (HttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pesan diperbarui.');
    }

    public function destroyPost(Request $request, ForumPost $post): RedirectResponse
    {
        try {
            $this->forum->deletePost($request->user(), $post);
        } catch (HttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pesan dihapus.');
    }

    public function appreciate(Request $request, ForumPost $post): RedirectResponse
    {
        try {
            $this->forum->appreciate($request->user(), $post);
        } catch (HttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back();
    }

    public function pin(Request $request, ForumTopic $topic): RedirectResponse
    {
        try {
            $this->forum->setPinned($request->user(), $topic, ! $topic->is_pinned);
        } catch (HttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $topic->fresh()->is_pinned ? 'Topik disematkan.' : 'Sematan dilepas.');
    }

    public function lock(Request $request, ForumTopic $topic): RedirectResponse
    {
        try {
            $this->forum->setLocked($request->user(), $topic, ! $topic->is_locked);
        } catch (HttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $topic->fresh()->is_locked ? 'Topik dikunci.' : 'Topik dibuka kembali.');
    }

    public function destroyTopic(Request $request, ForumTopic $topic): RedirectResponse
    {
        $slug = $topic->category->slug;

        try {
            $this->forum->deleteTopic($request->user(), $topic);
        } catch (HttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('forum.category', $slug)->with('success', 'Topik dihapus.');
    }

    /** @return array<string, mixed> */
    private function categoryDto(ForumCategory $category): array
    {
        return [
            'slug' => $category->slug,
            'name' => $category->name,
            'description' => $category->description,
            'is_locked' => $category->is_locked,
            'min_rank' => $category->min_rank,
        ];
    }

    /** @return array<string, string> */
    private function validateTopic(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:4', 'max:120'],
            'body' => ['required', 'string', 'min:2', 'max:5000'],
        ], $this->messages());

        return ['title' => trim($data['title']), 'body' => trim($data['body'])];
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'title.required' => 'Judul wajib diisi.',
            'title.min' => 'Judul terlalu pendek (minimal 4 karakter).',
            'title.max' => 'Judul maksimal 120 karakter.',
            'body.required' => 'Isi pesan wajib diisi.',
            'body.min' => 'Isi pesan terlalu pendek.',
            'body.max' => 'Isi pesan maksimal 5000 karakter.',
        ];
    }

    private function lastPage(ForumTopic $topic): int
    {
        return (int) ceil(($topic->posts()->count() ?: 1) / ForumService::POSTS_PER_PAGE);
    }
}
