<?php

namespace App\Services;

use App\Models\Character;
use App\Models\Item;
use App\Models\Place;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Interaksi pemain dengan Tempat (places) di sebuah kota: istirahat di
 * Penginapan (memulihkan HP/SP/MP dengan biaya gold) dan jual-beli di toko
 * (Potion Shop, Blacksmith, Pasar). Semua mutasi server-authoritative &
 * ter-persist; klien hanya mengirim {place, item, qty}.
 */
class TownService
{
    /** Biaya istirahat di penginapan per level karakter (tunable). */
    private const REST_COST_PER_LEVEL = 10;

    /**
     * Jenis item yang dijual/diterima tiap kategori toko. Kategori yang tak
     * tercantum bukan toko (Penginapan/City Hall/Guild tak berjualan).
     *
     * @var array<string, list<string>>
     */
    public const SHOP_STOCK = [
        'potion_shop' => ['consumable'],
        'blacksmith' => ['weapon', 'armor'],
        'magic_shop' => ['book'],
        'market' => ['consumable', 'misc', 'weapon', 'armor', 'accessory', 'book'],
    ];

    /** Biaya untuk istirahat penuh di penginapan (skala level). */
    public function restCost(Character $character): int
    {
        return self::REST_COST_PER_LEVEL * max(1, (int) $character->level);
    }

    /** Apakah semua pool (HP/SP/MP) karakter sudah penuh. */
    public function isFullyRested(Character $character): bool
    {
        return $character->hp >= $character->max_hp
            && $character->sp >= $character->max_sp
            && $character->mp >= $character->max_mp;
    }

    /**
     * Istirahat di penginapan: pulihkan HP/SP/MP penuh dengan biaya gold.
     * Ditolak bila bukan penginapan, sudah penuh, atau gold kurang.
     *
     * @return array{cost: int, restored: array{hp: int, sp: int, mp: int}}
     */
    public function rest(Character $character, Place $place): array
    {
        abort_unless($place->category === 'inn', 422, 'Tempat ini bukan penginapan.');
        abort_if($this->isFullyRested($character), 422, 'HP, SP, dan MP-mu sudah penuh.');

        $cost = $this->restCost($character);
        abort_if($character->gold < $cost, 422, "Emasmu tidak cukup untuk istirahat (butuh {$cost} emas).");

        return DB::transaction(function () use ($character, $place, $cost) {
            $restored = [
                'hp' => $character->max_hp - $character->hp,
                'sp' => $character->max_sp - $character->sp,
                'mp' => $character->max_mp - $character->mp,
            ];

            $character->gold -= $cost;
            $character->hp = $character->max_hp;
            $character->sp = $character->max_sp;
            $character->mp = $character->max_mp;
            $character->is_alive = true;
            $character->save();

            return ['cost' => $cost, 'restored' => $restored];
        });
    }

    /** Apakah place ini sebuah toko (punya stok jual-beli). */
    public function isShop(Place $place): bool
    {
        return array_key_exists($place->category, self::SHOP_STOCK);
    }

    /** Apakah sebuah item boleh diperjualbelikan di toko ini (jenis cocok & punya nilai). */
    public function sells(Place $place, Item $item): bool
    {
        $types = self::SHOP_STOCK[$place->category] ?? [];

        return in_array($item->type, $types, true) && (int) $item->value > 0;
    }

    /** Harga jual kembali pemain (separuh nilai item, minimal 1 bila bernilai). */
    public function sellPrice(Item $item): int
    {
        return max(1, intdiv((int) $item->value, 2));
    }

    /**
     * Katalog yang dijual toko ini (semua item dengan jenis cocok & bernilai).
     *
     * @return Collection<int, Item>
     */
    public function stock(Place $place): Collection
    {
        if (! $this->isShop($place)) {
            return new Collection;
        }

        return Item::query()
            ->whereIn('type', self::SHOP_STOCK[$place->category])
            ->where('value', '>', 0)
            ->orderBy('value')
            ->orderBy('name')
            ->get();
    }

    /**
     * Beli sejumlah item dari toko dengan gold. Ditolak bila bukan toko, item
     * tak dijual di sini, atau emas kurang.
     *
     * @return array{item: Item, qty: int, total: int}
     */
    public function buy(Character $character, Place $place, Item $item, int $qty = 1): array
    {
        abort_unless($this->isShop($place), 422, 'Tempat ini tidak menjual apa pun.');
        abort_unless($this->sells($place, $item), 422, "{$item->name} tidak dijual di sini.");
        abort_if($qty < 1, 422, 'Jumlah tidak valid.');

        $total = (int) $item->value * $qty;
        abort_if($character->gold < $total, 422, "Emasmu tidak cukup (butuh {$total} emas).");

        return DB::transaction(function () use ($character, $item, $qty, $total) {
            $character->gold -= $total;
            $character->save();
            app(StoryEngine::class)->giveItem($character, $item->slug, $qty);

            return ['item' => $item, 'qty' => $qty, 'total' => $total];
        });
    }

    /**
     * Jual sejumlah item ke toko untuk gold. Ditolak bila bukan toko, item tak
     * laku di sini, terpasang (equipped), atau stok pemain kurang.
     *
     * @return array{item: Item, qty: int, total: int}
     */
    public function sell(Character $character, Place $place, Item $item, int $qty = 1): array
    {
        abort_unless($this->isShop($place), 422, 'Tempat ini tidak membeli apa pun.');
        abort_unless($this->sells($place, $item), 422, "{$item->name} tidak laku di sini.");
        abort_if($qty < 1, 422, 'Jumlah tidak valid.');

        $owned = $character->items()->where('item_id', $item->id)->first();
        abort_unless($owned && $owned->pivot->quantity >= $qty, 422, 'Stokmu tidak cukup untuk dijual.');
        abort_if((bool) $owned->pivot->equipped, 422, 'Lepas dulu item yang terpasang sebelum menjualnya.');

        $total = $this->sellPrice($item) * $qty;

        return DB::transaction(function () use ($character, $item, $qty, $total) {
            $character->gold += $total;
            $character->save();
            app(StoryEngine::class)->takeItem($character, $item->slug, $qty);

            return ['item' => $item, 'qty' => $qty, 'total' => $total];
        });
    }
}
