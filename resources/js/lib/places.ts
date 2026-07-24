// Metadata tampilan untuk tiap kategori Tempat (ikon + warna aksen).
// Kunci kategori harus sama dengan App\Models\Place::CATEGORIES.
import {
    BedDouble,
    BookOpen,
    Coins,
    FlaskConical,
    Hammer,
    Landmark,
    Store,
    Swords,
    type LucideIcon,
} from 'lucide-vue-next';

export interface PlaceMeta {
    icon: LucideIcon;
    /** Kelas warna teks/aksen Tailwind untuk ikon. */
    tint: string;
}

const FALLBACK: PlaceMeta = { icon: Landmark, tint: 'text-primary' };

export const PLACE_META: Record<string, PlaceMeta> = {
    adventurer_guild: { icon: Swords, tint: 'text-amber-500' },
    merchant_guild: { icon: Coins, tint: 'text-emerald-500' },
    city_hall: { icon: Landmark, tint: 'text-sky-500' },
    market: { icon: Store, tint: 'text-orange-500' },
    blacksmith: { icon: Hammer, tint: 'text-zinc-400' },
    potion_shop: { icon: FlaskConical, tint: 'text-fuchsia-500' },
    magic_shop: { icon: BookOpen, tint: 'text-violet-500' },
    inn: { icon: BedDouble, tint: 'text-rose-400' },
};

export function placeMeta(category: string): PlaceMeta {
    return PLACE_META[category] ?? FALLBACK;
}
