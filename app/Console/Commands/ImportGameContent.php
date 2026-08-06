<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Models\Monster;
use App\Models\NodeChoice;
use App\Models\Quest;
use App\Models\QuestNode;
use App\Models\Skill;
use App\Models\Spell;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Import game content (quests, nodes, monsters, questions, items) from the JSON
 * files in database/content. Idempotent: natural keys (slug / quest+key) upsert,
 * and child rows (choices/questions) are replaced so re-running converges.
 */
class ImportGameContent extends Command
{
    protected $signature = 'game:import {--fresh : Wipe content + player progress (keeps users & characters) before importing}';

    protected $description = 'Import game content from database/content JSON files.';

    public function handle(): int
    {
        $base = database_path('content');
        if (! File::isDirectory($base)) {
            $this->error("Content directory not found: {$base}");

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->freshWipe();
        }

        DB::transaction(function () use ($base) {
            $this->importItems($base.DIRECTORY_SEPARATOR.'items');
            $this->importSkills($base.DIRECTORY_SEPARATOR.'skills');
            $this->importSpells($base.DIRECTORY_SEPARATOR.'spells');
            $this->importStandaloneMonsters($base.DIRECTORY_SEPARATOR.'monsters');
            $this->importQuests($base.DIRECTORY_SEPARATOR.'quests');
        });

        $this->newLine();
        $this->info('Imported: '
            .Quest::count().' quests, '
            .QuestNode::count().' nodes, '
            .NodeChoice::count().' choices, '
            .Monster::count().' monsters, '
            .Skill::count().' skills, '
            .Spell::count().' spells, '
            .Item::count().' items.');

        return self::SUCCESS;
    }

    private function freshWipe(): void
    {
        $this->warn('Wiping content + player progress (keeping users & characters)...');
        Schema::disableForeignKeyConstraints();
        foreach ([
            'node_choices', 'combat_questions', 'quest_nodes', 'quests',
            'monsters', 'items', 'skills', 'combat_sessions',
            'character_items', 'character_skill', 'character_spell', 'game_saves',
        ] as $table) {
            DB::table($table)->truncate();
        }
        Schema::enableForeignKeyConstraints();
    }

    private function importItems(string $dir): void
    {
        foreach ($this->jsonFiles($dir) as $data) {
            Item::updateOrCreate(['slug' => $data['slug']], [
                'name' => $data['name'],
                'type' => $data['type'] ?? 'misc',
                'description' => $data['description'] ?? null,
                'image' => $data['image'] ?? null,
                'stats' => $data['stats'] ?? null,
                'value' => $data['value'] ?? 0,
            ]);
        }
    }

    private function importSkills(string $dir): void
    {
        foreach ($this->jsonFiles($dir) as $data) {
            Skill::updateOrCreate(['slug' => $data['slug']], [
                'name' => $data['name'],
                'type' => $data['type'] ?? 'physical',
                'description' => $data['description'] ?? null,
                'image' => $data['image'] ?? null,
                'power' => $data['power'] ?? 1,
                'level_req' => $data['level_req'] ?? 1,
                'is_default' => $data['is_default'] ?? false,
                'effects' => $data['effects'] ?? null,
            ]);
        }
    }

    private function importSpells(string $dir): void
    {
        foreach ($this->jsonFiles($dir) as $data) {
            Spell::updateOrCreate(['slug' => $data['slug']], [
                'name' => $data['name'],
                'element' => $data['element'] ?? 'arcane',
                'description' => $data['description'] ?? null,
                'image' => $data['image'] ?? null,
                'power' => $data['power'] ?? 1,
                'mana_cost' => $data['mana_cost'] ?? 0,
                'min_level' => $data['min_level'] ?? 1,
                'is_default' => $data['is_default'] ?? false,
                'effects' => $data['effects'] ?? null,
            ]);
        }
    }

    private function importStandaloneMonsters(string $dir): void
    {
        foreach ($this->jsonFiles($dir) as $data) {
            $this->upsertMonster($data);
        }
    }

    private function importQuests(string $dir): void
    {
        foreach ($this->jsonFiles($dir) as $data) {
            // Embedded monsters first so nodes can resolve monster_id by slug.
            foreach ($data['monsters'] ?? [] as $monsterData) {
                $this->upsertMonster($monsterData);
            }

            $quest = Quest::updateOrCreate(['slug' => $data['slug']], [
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'affiliation' => $data['affiliation'] ?? null,
                'required_rank' => $data['required_rank'] ?? null,
                'cover_image' => $data['cover_image'] ?? null,
                'min_level' => $data['min_level'] ?? 1,
                'order' => $data['order'] ?? 0,
                'is_published' => $data['is_published'] ?? true,
            ]);

            foreach ($data['nodes'] ?? [] as $nodeData) {
                $monsterId = empty($nodeData['monster'])
                    ? null
                    : Monster::where('slug', $nodeData['monster'])->value('id');

                $node = QuestNode::updateOrCreate(
                    ['quest_id' => $quest->id, 'key' => $nodeData['key']],
                    [
                        'type' => $nodeData['type'] ?? 'narrative',
                        'title' => $nodeData['title'] ?? null,
                        'body' => $nodeData['body'] ?? null,
                        'image' => $nodeData['image'] ?? null,
                        'monster_id' => $monsterId,
                        'payload' => $nodeData['payload'] ?? null,
                    ]
                );

                // Replace choices for idempotency.
                $node->choices()->delete();
                foreach ($nodeData['choices'] ?? [] as $i => $choiceData) {
                    NodeChoice::create([
                        'quest_node_id' => $node->id,
                        'label' => $choiceData['label'],
                        'next_node_key' => $choiceData['next'] ?? null,
                        'requirements' => $choiceData['requirements'] ?? null,
                        'effects' => $choiceData['effects'] ?? null,
                        'order' => $choiceData['order'] ?? $i,
                        'is_auto' => $choiceData['is_auto'] ?? false,
                    ]);
                }
            }

            // Resolve the start node id now that nodes exist.
            if (! empty($data['start_node'])) {
                $startId = QuestNode::where('quest_id', $quest->id)
                    ->where('key', $data['start_node'])
                    ->value('id');
                $quest->update(['start_node_id' => $startId]);
            }

            $this->line("  - quest '{$quest->slug}' (".count($data['nodes'] ?? []).' nodes)');
        }
    }

    /**
     * Field yang boleh muncul di blok monster konten. Salah tulis harus gagal
     * keras — field yang diam-diam diabaikan itu jebakan saat menyeimbangkan.
     */
    private const MONSTER_FIELDS = [
        'slug', 'name', 'level', 'image', 'max_hp', 'attack', 'defense',
        'magic_attack', 'magic_defense', 'attack_kind', 'xp_reward', 'gold_reward', 'loot',
    ];

    /** @param array<string, mixed> $data */
    private function upsertMonster(array $data): void
    {
        $slug = $data['slug'] ?? null;
        if (! is_string($slug) || $slug === '') {
            throw new RuntimeException('Ada blok monster tanpa `slug`.');
        }
        if (! isset($data['name'])) {
            throw new RuntimeException("Monster `{$slug}`: `name` wajib diisi.");
        }

        $unknown = array_diff(array_keys($data), self::MONSTER_FIELDS);
        if ($unknown !== []) {
            throw new RuntimeException("Monster `{$slug}`: field tak dikenal — ".implode(', ', $unknown).'.');
        }

        if (array_key_exists('level', $data) && (! is_int($data['level']) || $data['level'] < 1)) {
            throw new RuntimeException("Monster `{$slug}`: `level` harus integer ≥ 1.");
        }

        // Rumus level jadi dasar; field yang ditulis eksplisit menimpanya.
        $stats = isset($data['level']) ? Monster::statsForLevel($data['level']) : [];
        foreach (['max_hp', 'attack', 'defense', 'magic_attack', 'magic_defense', 'xp_reward', 'gold_reward'] as $field) {
            if (isset($data[$field])) {
                $stats[$field] = (int) $data[$field];
            }
        }

        foreach (['max_hp', 'attack'] as $field) {
            if (! isset($stats[$field])) {
                throw new RuntimeException("Monster `{$slug}`: `{$field}` wajib bila `level` tidak diisi.");
            }
        }

        Monster::updateOrCreate(['slug' => $slug], [
            'name' => $data['name'],
            'image' => $data['image'] ?? null,
            'max_hp' => $stats['max_hp'],
            'attack' => $stats['attack'],
            'defense' => $stats['defense'] ?? 0,
            'magic_attack' => $stats['magic_attack'] ?? 0,
            'magic_defense' => $stats['magic_defense'] ?? 0,
            'attack_kind' => $data['attack_kind'] ?? 'physical',
            'xp_reward' => $stats['xp_reward'] ?? 0,
            'gold_reward' => $stats['gold_reward'] ?? 0,
            'loot' => $data['loot'] ?? null,
        ]);
    }

    /**
     * Read and decode every *.json file in a directory.
     *
     * @return array<int, array<string, mixed>>
     */
    private function jsonFiles(string $dir): array
    {
        if (! File::isDirectory($dir)) {
            return [];
        }

        $out = [];
        foreach (File::files($dir) as $file) {
            if ($file->getExtension() !== 'json') {
                continue;
            }
            $data = json_decode(File::get($file->getPathname()), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error("Invalid JSON in {$file->getFilename()}: ".json_last_error_msg());

                continue;
            }
            $out[] = $data;
        }

        return $out;
    }
}
