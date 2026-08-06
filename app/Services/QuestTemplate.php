<?php

namespace App\Services;

use RuntimeException;

/**
 * Mengembangkan bentuk misi RINGKAS jadi struktur node long-form yang
 * dimengerti importer. Dipanggil sebelum jalur upsert biasa, jadi runtime
 * (StoryEngine/CombatService/Panel Dewa) tidak pernah tahu template ini ada —
 * yang masuk DB tetap node normal.
 *
 * Murni fungsi: tidak menyentuh DB, jadi bisa diuji tanpa migrasi.
 */
class QuestTemplate
{
    /** Arketipe yang dikenali. */
    private const SHAPES = ['hunt'];

    private const DEFAULT_LOSE = 'Kau tumbang. Pulihkan diri lalu coba lagi.';

    private const DEFAULT_OUTRO = 'Tugas selesai. Laporkan hasilnya ke guild.';

    /**
     * @param  array<string, mixed>  $quest
     * @return array<string, mixed>
     */
    public static function expand(array $quest): array
    {
        $slug = $quest['slug'] ?? '(tanpa slug)';
        $shapes = array_values(array_filter(self::SHAPES, fn (string $k) => isset($quest[$k])));

        if (count($shapes) > 1) {
            throw new RuntimeException("Misi `{$slug}`: pilih salah satu arketipe saja.");
        }
        if ($shapes === []) {
            return $quest; // long-form, biarkan apa adanya
        }
        if (isset($quest['nodes'])) {
            throw new RuntimeException("Misi `{$slug}`: bentuk ringkas tidak bisa dicampur dengan `nodes`.");
        }

        $shape = $shapes[0];
        $body = $quest[$shape];
        if (! is_array($body)) {
            throw new RuntimeException("Misi `{$slug}`: `{$shape}` harus berupa objek.");
        }
        unset($quest[$shape]);

        $title = (string) ($quest['title'] ?? $slug);

        return array_merge($quest, self::hunt($slug, $title, $body));
    }

    /**
     * intro → fight → (win) → ending_win, plus ending kalah.
     *
     * @param  array<string, mixed>  $hunt
     * @return array<string, mixed>
     */
    private static function hunt(string $slug, string $title, array $hunt): array
    {
        $monster = $hunt['monster'] ?? null;
        if (! is_array($monster) || ! isset($monster['slug'])) {
            throw new RuntimeException("Misi `{$slug}`: `hunt.monster` wajib punya `slug`.");
        }

        $intro = self::prose($slug, 'hunt.intro', $hunt['intro'] ?? null);
        $fight = self::prose($slug, 'hunt.fight', $hunt['fight'] ?? null);
        $win = self::prose($slug, 'hunt.win', $hunt['win'] ?? null);
        $lose = self::prose($slug, 'hunt.lose', $hunt['lose'] ?? null, required: false);
        $outro = self::prose($slug, 'hunt.outro', $hunt['outro'] ?? null, required: false);

        // Presence-based, bukan truthy — `"reward": {}` (array kosong) tetap dianggap ada.
        $hasReward = array_key_exists('reward', $hunt) && $hunt['reward'] !== null;
        $reward = $hasReward ? $hunt['reward'] : null;
        $afterFight = $hasReward ? 'win' : 'ending_win';

        $nodes = [
            [
                'key' => 'intro',
                'type' => 'narrative',
                'title' => $intro['title'] ?? $title,
                'body' => $intro['body'],
                'choices' => [['label' => 'Hadapi', 'next' => 'fight']],
            ],
            [
                'key' => 'fight',
                'type' => 'combat',
                'title' => $fight['title'] ?? ($monster['name'] ?? $monster['slug']).'!',
                'body' => $fight['body'],
                'monster' => $monster['slug'],
                'payload' => ['on_win_node_key' => $afterFight, 'on_lose_node_key' => 'lose'],
            ],
        ];

        if ($hasReward) {
            $nodes[] = self::rewardNode($win, $reward);
        }

        $nodes[] = self::endingWin($win, $outro, hasReward: $hasReward);
        $nodes[] = [
            'key' => 'lose',
            'type' => 'ending',
            'title' => $lose['title'] ?? 'Kalah',
            'body' => $lose['body'] ?? self::DEFAULT_LOSE,
            'payload' => ['result' => 'defeat'],
        ];

        return ['start_node' => 'intro', 'nodes' => $nodes, 'monsters' => [$monster]];
    }

    /**
     * Node reward yang mengalir otomatis ke ending sukses.
     *
     * @param  array{title: ?string, body: ?string}  $win
     * @param  array<string, mixed>  $reward
     * @return array<string, mixed>
     */
    private static function rewardNode(array $win, array $reward): array
    {
        return [
            'key' => 'win',
            'type' => 'reward',
            'title' => $win['title'] ?? 'Berhasil',
            'body' => $win['body'],
            'payload' => $reward,
            'choices' => [['label' => 'Lanjutkan', 'next' => 'ending_win', 'is_auto' => true]],
        ];
    }

    /**
     * Node ending sukses. Bila ada node reward, prosa `win` sudah terpakai di
     * sana, jadi ending memakai `outro` (atau teks default). Bila tidak, prosa
     * `win` pindah ke ending supaya tidak hilang.
     *
     * @param  array{title: ?string, body: ?string}  $win
     * @param  array{title: ?string, body: ?string}  $outro
     * @return array<string, mixed>
     */
    private static function endingWin(array $win, array $outro, bool $hasReward): array
    {
        return [
            'key' => 'ending_win',
            'type' => 'ending',
            'title' => ($hasReward ? $outro['title'] : $win['title']) ?? 'Misi Tuntas',
            'body' => $hasReward ? ($outro['body'] ?? self::DEFAULT_OUTRO) : $win['body'],
            'payload' => ['result' => 'victory'],
        ];
    }

    /**
     * Normalkan satu field prosa. Menerima string ATAU objek
     * `{"title": "...", "body": "..."}` — bentuk objek dipakai kalau penulis
     * mau menimpa judul default.
     *
     * @return array{title: ?string, body: ?string}
     */
    private static function prose(string $slug, string $field, mixed $value, bool $required = true): array
    {
        if (is_string($value) && trim($value) !== '') {
            return ['title' => null, 'body' => $value];
        }

        if (is_array($value)) {
            $title = isset($value['title']) ? (string) $value['title'] : null;
            $body = isset($value['body']) ? (string) $value['body'] : '';

            if (trim($body) !== '') {
                return ['title' => $title, 'body' => $body];
            }
            if (! $required) {
                return ['title' => $title, 'body' => null];
            }
        }

        if ($required) {
            throw new RuntimeException("Misi `{$slug}`: `{$field}` wajib diisi.");
        }

        return ['title' => null, 'body' => null];
    }
}
