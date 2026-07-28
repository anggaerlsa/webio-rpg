<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\CityController as AdminCityController;
use App\Http\Controllers\Admin\CountryController as AdminCountryController;
use App\Http\Controllers\Admin\ForumCategoryController as AdminForumCategoryController;
use App\Http\Controllers\Admin\ItemController as AdminItemController;
use App\Http\Controllers\Admin\MonsterController as AdminMonsterController;
use App\Http\Controllers\Admin\PlaceController as AdminPlaceController;
use App\Http\Controllers\Admin\PlayerController as AdminPlayerController;
use App\Http\Controllers\Admin\ProvinceController as AdminProvinceController;
use App\Http\Controllers\Admin\QuestController as AdminQuestController;
use App\Http\Controllers\Admin\QuestNodeController as AdminQuestNodeController;
use App\Http\Controllers\Admin\RankController as AdminRankController;
use App\Http\Controllers\Admin\SkillController as AdminSkillController;
use App\Http\Controllers\Admin\SpellController as AdminSpellController;
use App\Http\Controllers\Admin\VillageController as AdminVillageController;
use App\Http\Controllers\Api\CombatController;
use App\Http\Controllers\CharacterController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ForumController;
use App\Http\Controllers\FriendController;
use App\Http\Controllers\QuestController;
use App\Http\Controllers\TownController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Character
    Route::get('character/create', [CharacterController::class, 'create'])->name('character.create');
    Route::post('character', [CharacterController::class, 'store'])->name('character.store');
    Route::get('character', [CharacterController::class, 'show'])->name('character.show');
    Route::post('character/affiliation', [CharacterController::class, 'chooseAffiliation'])->name('character.affiliation');
    Route::post('character/use-item', [CharacterController::class, 'useItem'])->name('character.use-item');
    Route::post('character/equip', [CharacterController::class, 'equip'])->name('character.equip');
    Route::post('character/unequip', [CharacterController::class, 'unequip'])->name('character.unequip');
    Route::post('character/learn', [CharacterController::class, 'learn'])->name('character.learn');

    // Kota — layer kota pemain: kota asal + interaksi Tempat (penginapan/toko/guild).
    Route::get('town', [TownController::class, 'show'])->name('town.show');
    Route::get('town/{place:slug}', [TownController::class, 'place'])->name('town.place');
    Route::post('town/{place:slug}/rest', [TownController::class, 'rest'])->name('town.rest');
    Route::post('town/{place:slug}/buy', [TownController::class, 'buy'])->name('town.buy');
    Route::post('town/{place:slug}/sell', [TownController::class, 'sell'])->name('town.sell');
    Route::post('town/{place:slug}/accept-mission', [TownController::class, 'acceptMission'])->name('town.mission.accept');

    // Quests (Inertia pages + story choices)
    Route::get('quests', [QuestController::class, 'index'])->name('quests.index');
    Route::get('quests/{quest:slug}/play', [QuestController::class, 'play'])->name('quests.play');
    Route::post('quests/{quest:slug}/start', [QuestController::class, 'start'])->name('quests.start');
    Route::post('quests/{quest:slug}/choose', [QuestController::class, 'choose'])->name('quests.choose');

    // Chat (dunia + DM) & pertemanan — JSON, dikonsumsi panel ChatDock via fetch.
    Route::get('chat/me', [ChatController::class, 'me'])->name('chat.me');
    Route::get('chat/world', [ChatController::class, 'world'])->name('chat.world');
    Route::post('chat/world', [ChatController::class, 'postWorld'])->name('chat.world.post');
    Route::get('chat/dm/{friendship}', [ChatController::class, 'dm'])->name('chat.dm');
    Route::post('chat/dm/{friendship}', [ChatController::class, 'postDm'])->name('chat.dm.post');

    Route::get('friends', [FriendController::class, 'index'])->name('friends.index');
    Route::get('friends/search', [FriendController::class, 'search'])->name('friends.search');
    Route::post('friends/request', [FriendController::class, 'request'])->name('friends.request');
    Route::post('friends/{friendship}/accept', [FriendController::class, 'accept'])->name('friends.accept');
    Route::delete('friends/{friendship}', [FriendController::class, 'destroy'])->name('friends.destroy');

    // Balai Warta — forum diskusi permanen (kategori → topik → pesan).
    // Berbeda dari chat: tidak fana, dibaca lewat halaman Inertia biasa.
    Route::get('forum', [ForumController::class, 'index'])->name('forum.index');
    Route::get('forum/k/{category:slug}', [ForumController::class, 'category'])->name('forum.category');
    Route::get('forum/k/{category:slug}/buat', [ForumController::class, 'create'])->name('forum.topic.create');
    Route::post('forum/k/{category:slug}', [ForumController::class, 'store'])->middleware('throttle:10,1')->name('forum.topic.store');
    Route::get('forum/t/{topic:slug}', [ForumController::class, 'topic'])->name('forum.topic');
    Route::post('forum/t/{topic:slug}/balas', [ForumController::class, 'reply'])->middleware('throttle:20,1')->name('forum.reply');
    Route::post('forum/p/{post}/apresiasi', [ForumController::class, 'appreciate'])->middleware('throttle:60,1')->name('forum.post.appreciate');
    Route::put('forum/p/{post}', [ForumController::class, 'updatePost'])->name('forum.post.update');
    Route::delete('forum/p/{post}', [ForumController::class, 'destroyPost'])->name('forum.post.destroy');
    // Moderasi (Dewa) — dijaga di ForumService, bukan middleware, agar pesan galatnya ramah.
    Route::post('forum/t/{topic:slug}/sematkan', [ForumController::class, 'pin'])->name('forum.topic.pin');
    Route::post('forum/t/{topic:slug}/kunci', [ForumController::class, 'lock'])->name('forum.topic.lock');
    Route::delete('forum/t/{topic:slug}', [ForumController::class, 'destroyTopic'])->name('forum.topic.destroy');

    // Combat (JSON turn loop, consumed via fetch from the Vue combat view)
    Route::post('combat/start', [CombatController::class, 'start'])->name('combat.start');
    Route::post('combat/act', [CombatController::class, 'act'])->name('combat.act');
    Route::post('combat/use-item', [CombatController::class, 'useItem'])->name('combat.use-item');
    Route::get('combat/{session}', [CombatController::class, 'show'])->name('combat.show');
});

// Admin panel — "Dewa Pencipta" (superadmin) only.
Route::middleware(['auth', 'superadmin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::resource('items', AdminItemController::class)->except(['show']);
    Route::resource('skills', AdminSkillController::class)->except(['show']);
    Route::resource('spells', AdminSpellController::class)->except(['show']);
    Route::resource('monsters', AdminMonsterController::class)->except(['show']);
    Route::resource('quests', AdminQuestController::class)->except(['show']);
    Route::resource('quests.nodes', AdminQuestNodeController::class)->except(['show', 'index']);

    // Balai Warta — kategori forum (terkunci = hanya Dewa yang boleh buka topik).
    Route::resource('forum-categories', AdminForumCategoryController::class)
        ->except(['show'])->parameters(['forum-categories' => 'category']);

    // Rank — ambang naik rank (misi per rank).
    Route::get('ranks', [AdminRankController::class, 'index'])->name('ranks.index');
    Route::put('ranks', [AdminRankController::class, 'update'])->name('ranks.update');

    // Pemain — kelola akun + karakter; hapus = cascade karakter & progres.
    Route::resource('players', AdminPlayerController::class)->only(['index', 'edit', 'update', 'destroy']);

    // Dunia — database lokasi hierarkis (Negara → Provinsi → Kota → Desa) + Tempat di tingkat kota.
    // Shallow nesting: create di-scope oleh induk, sisanya datar by id (drill-down lewat "show").
    Route::prefix('world')->name('world.')->group(function () {
        Route::resource('countries', AdminCountryController::class);
        Route::resource('countries.provinces', AdminProvinceController::class)->shallow()->except(['index']);
        Route::resource('provinces.cities', AdminCityController::class)->shallow()->except(['index']);
        Route::resource('cities.villages', AdminVillageController::class)->shallow()->except(['index', 'show']);
        Route::resource('cities.places', AdminPlaceController::class)->shallow()->except(['index', 'show']);
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
