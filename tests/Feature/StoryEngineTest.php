<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\NodeChoice;
use App\Models\Quest;
use App\Models\QuestNode;
use App\Models\User;
use App\Services\StoryEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class StoryEngineTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{char: Character, quest: Quest, start: QuestNode}
     */
    private function scenario(): array
    {
        $user = User::factory()->create();
        $char = $user->characters()->create([
            'name' => 'Hero', 'level' => 1, 'xp' => 0, 'hp' => 50, 'max_hp' => 50,
            'attack' => 10, 'defense' => 5, 'gold' => 0, 'is_alive' => true,
        ]);
        $quest = Quest::create(['slug' => 'test-quest', 'title' => 'Test', 'min_level' => 1, 'order' => 1]);
        $start = QuestNode::create(['quest_id' => $quest->id, 'key' => 'start', 'type' => 'choice', 'body' => 'Begin']);
        QuestNode::create(['quest_id' => $quest->id, 'key' => 'next', 'type' => 'narrative', 'body' => 'Onward']);
        $quest->update(['start_node_id' => $start->id]);

        return ['char' => $char, 'quest' => $quest, 'start' => $start];
    }

    public function test_choices_are_locked_when_requirements_are_not_met(): void
    {
        $s = $this->scenario();
        $story = app(StoryEngine::class);
        $story->startQuest($s['char'], $s['quest']);

        NodeChoice::create(['quest_node_id' => $s['start']->id, 'label' => 'Hard', 'next_node_key' => 'next', 'requirements' => ['min_level' => 5], 'order' => 0]);
        NodeChoice::create(['quest_node_id' => $s['start']->id, 'label' => 'Easy', 'next_node_key' => 'next', 'order' => 1]);

        $state = $story->currentState($s['char']->fresh());
        $byLabel = collect($state['node']['choices'])->keyBy('label');

        $this->assertTrue($byLabel['Hard']['locked']);
        $this->assertStringContainsString('level 5', $byLabel['Hard']['hint']);
        $this->assertFalse($byLabel['Easy']['locked']);
    }

    public function test_choosing_applies_effects_and_advances_the_node(): void
    {
        $s = $this->scenario();
        $story = app(StoryEngine::class);
        $story->startQuest($s['char'], $s['quest']);

        $choice = NodeChoice::create([
            'quest_node_id' => $s['start']->id, 'label' => 'Go', 'next_node_key' => 'next',
            'effects' => ['gold' => 15, 'xp' => 10, 'flags' => ['went' => true]], 'order' => 0,
        ]);

        $story->choose($s['char']->fresh(), $choice);

        $char = $s['char']->fresh();
        $this->assertSame(15, $char->gold);
        $this->assertSame(10, $char->xp);

        $save = $char->saves()->first();
        $this->assertSame('next', $save->current_node_key);
        $this->assertTrue($save->state['flags']['went']);
    }

    public function test_a_choice_with_unmet_requirements_is_refused(): void
    {
        $s = $this->scenario();
        $story = app(StoryEngine::class);
        $story->startQuest($s['char'], $s['quest']);

        $choice = NodeChoice::create([
            'quest_node_id' => $s['start']->id, 'label' => 'Bribe', 'next_node_key' => 'next',
            'requirements' => ['min_gold' => 999], 'order' => 0,
        ]);

        $this->expectException(HttpException::class);
        $story->choose($s['char']->fresh(), $choice);
    }

    public function test_character_levels_up_when_xp_crosses_threshold(): void
    {
        $s = $this->scenario();
        $story = app(StoryEngine::class);
        $story->startQuest($s['char'], $s['quest']);

        $choice = NodeChoice::create([
            'quest_node_id' => $s['start']->id, 'label' => 'Train', 'next_node_key' => 'next',
            'effects' => ['xp' => 100], 'order' => 0,
        ]);

        $story->choose($s['char']->fresh(), $choice);

        $char = $s['char']->fresh();
        $this->assertSame(2, $char->level);
        $this->assertSame(60, $char->max_hp);
        $this->assertSame(60, $char->hp); // healed on level up
    }
}
