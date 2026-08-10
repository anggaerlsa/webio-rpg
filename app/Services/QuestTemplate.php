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
    private const SHAPES = ['hunt', 'errand'];

    /** Field yang boleh muncul di blok `hunt`. Salah tulis harus gagal keras. */
    private const HUNT_FIELDS = ['monster', 'intro', 'fight', 'win', 'lose', 'outro', 'reward'];

    /** Field yang boleh muncul di blok `errand`. Salah tulis harus gagal keras. */
    private const ERRAND_FIELDS = ['beats', 'win', 'outro', 'reward'];

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

        $reserved = array_values(array_filter(
            ['nodes', 'monsters', 'start_node'],
            fn (string $k) => isset($quest[$k])
        ));
        if ($reserved !== []) {
            throw new RuntimeException("Misi `{$slug}`: bentuk ringkas tidak bisa dicampur dengan `".implode('`, `', $reserved).'`.');
        }

        $shape = $shapes[0];
        $body = $quest[$shape];
        if (! is_array($body)) {
            throw new RuntimeException("Misi `{$slug}`: `{$shape}` harus berupa objek.");
        }

        $allowed = $shape === 'hunt' ? self::HUNT_FIELDS : self::ERRAND_FIELDS;
        $unknown = array_diff(array_keys($body), $allowed);
        if ($unknown !== []) {
            throw new RuntimeException("Misi `{$slug}`: `{$shape}` punya field tak dikenal — ".implode(', ', $unknown).'.');
        }

        unset($quest[$shape]);

        $title = (string) ($quest['title'] ?? $slug);

        $expanded = $shape === 'hunt'
            ? self::hunt($slug, $title, $body)
            : self::errand($slug, $title, $body);

        return array_merge($quest, $expanded);
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

        $reward = self::reward($slug, 'hunt', $hunt);
        $hasReward = $reward !== null;
        $afterFight = $hasReward ? 'win' : 'ending_win';

        $nodes = [
            [
                'key' => 'intro',
                'type' => 'narrative',
                'title' => $intro['title'] ?? $title,
                'body' => $intro['body'],
                'choices' => [['label' => $intro['label'] ?? 'Hadapi', 'next' => 'fight']],
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
     * Rangkaian narasi tanpa tarung: beat_1..beat_n → (win) → ending_win.
     * Tidak punya ending kalah karena tidak ada yang bisa mengalahkan pemain.
     *
     * @param  array<string, mixed>  $errand
     * @return array<string, mixed>
     */
    private static function errand(string $slug, string $title, array $errand): array
    {
        $beats = $errand['beats'] ?? null;
        if (! is_array($beats) || $beats === []) {
            throw new RuntimeException("Misi `{$slug}`: `errand.beats` wajib berisi minimal satu adegan.");
        }

        $win = self::prose($slug, 'errand.win', $errand['win'] ?? null);
        $outro = self::prose($slug, 'errand.outro', $errand['outro'] ?? null, required: false);

        $reward = self::reward($slug, 'errand', $errand);
        $hasReward = $reward !== null;
        $afterBeats = $hasReward ? 'win' : 'ending_win';

        $nodes = [];
        $beats = array_values($beats);
        $count = count($beats);
        foreach ($beats as $i => $raw) {
            $n = $i + 1;
            $beat = self::prose($slug, "errand.beats[{$i}]", $raw);
            $nodes[] = [
                'key' => "beat_{$n}",
                'type' => 'narrative',
                'title' => $beat['title'] ?? $title,
                'body' => $beat['body'],
                'choices' => [[
                    'label' => $beat['label'] ?? 'Lanjutkan',
                    'next' => $n < $count ? 'beat_'.($n + 1) : $afterBeats,
                ]],
            ];
        }

        if ($hasReward) {
            $nodes[] = self::rewardNode($win, $reward);
        }

        $nodes[] = self::endingWin($win, $outro, hasReward: $hasReward);

        return ['start_node' => 'beat_1', 'nodes' => $nodes, 'monsters' => []];
    }

    /**
     * Ambil payload `reward` dari body arketipe. Presence-based, bukan
     * truthy — `"reward": {}` (array kosong) tetap dianggap ada; key hilang
     * atau bernilai `null` berarti tidak ada reward. Nilai lain (string,
     * angka, dst.) ditolak keras di sini supaya penulis konten dapat pesan
     * yang jelas, bukan `TypeError` mentah dari `rewardNode()`.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>|null
     */
    private static function reward(string $slug, string $shape, array $body): ?array
    {
        if (! array_key_exists('reward', $body) || $body['reward'] === null) {
            return null;
        }
        if (! is_array($body['reward'])) {
            throw new RuntimeException("Misi `{$slug}`: `{$shape}.reward` harus berupa objek.");
        }

        return $body['reward'];
    }

    /**
     * Node reward yang mengalir otomatis ke ending sukses.
     *
     * @param  array{title: ?string, body: ?string, label: ?string}  $win
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
            'choices' => [['label' => $win['label'] ?? 'Lanjutkan', 'next' => 'ending_win', 'is_auto' => true]],
        ];
    }

    /**
     * Node ending sukses. Bila ada node reward, prosa `win` sudah terpakai di
     * sana, jadi ending memakai `outro` (atau teks default). Bila tidak, prosa
     * `win` pindah ke ending supaya tidak hilang.
     *
     * @param  array{title: ?string, body: ?string, label: ?string}  $win
     * @param  array{title: ?string, body: ?string, label: ?string}  $outro
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
     * `{"title": "...", "body": "...", "label": "..."}` — bentuk objek dipakai
     * kalau penulis mau menimpa judul default atau label tombol pilihannya.
     *
     * @return array{title: ?string, body: ?string, label: ?string}
     */
    private static function prose(string $slug, string $field, mixed $value, bool $required = true): array
    {
        if (is_string($value) && trim($value) !== '') {
            return ['title' => null, 'body' => $value, 'label' => null];
        }

        if (is_array($value)) {
            // Judul & label kosong dianggap tidak ditulis: tombol tanpa teks itu
            // rusak di mata pemain, bukan pilihan gaya.
            $title = self::text($value['title'] ?? null);
            $body = isset($value['body']) ? (string) $value['body'] : '';
            $label = self::text($value['label'] ?? null);

            if (trim($body) !== '') {
                return ['title' => $title, 'body' => $body, 'label' => $label];
            }
            if (! $required) {
                return ['title' => $title, 'body' => null, 'label' => $label];
            }
        }

        if ($required) {
            throw new RuntimeException("Misi `{$slug}`: `{$field}` wajib diisi.");
        }

        return ['title' => null, 'body' => null, 'label' => null];
    }

    /** Teks opsional: string kosong (atau spasi saja) dihitung sebagai tidak ditulis. */
    private static function text(mixed $value): ?string
    {
        return is_scalar($value) && trim((string) $value) !== '' ? (string) $value : null;
    }
}
