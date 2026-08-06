<script setup lang="ts">
import StatBar from '@/components/game/StatBar.vue';
import { Button } from '@/components/ui/button';
import { postJson } from '@/lib/gameApi';
import type { PotionState } from '@/types/game';
import { router } from '@inertiajs/vue3';
import { Coins, FlaskConical, Loader2, Skull, Sparkles, Sword, Swords, Wand2 } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

interface Attack {
    kind: 'skill' | 'spell';
    id: number;
    name: string;
    power: number;
    type?: string;
    cost: number;
    resource: 'sp' | 'mp';
}
interface Rewards {
    xp: number;
    gold: number;
    items: { slug: string; qty: number }[];
    leveled_up: boolean;
    new_level: number;
}

const props = defineProps<{ nodeId: number; questSlug: string }>();

const loading = ref(true);
const error = ref<string | null>(null); // galat fatal: pertarungan tak bisa dimuat
const actionError = ref<string | null>(null); // galat aksi (mis. SP kurang) — masih bisa lanjut
const acting = ref(false);

const sessionId = ref<number | null>(null);
const status = ref<'active' | 'won' | 'lost'>('active');
const monster = ref<{ name: string; image: string | null }>({ name: '', image: null });
const monsterHp = ref(0);
const maxMonsterHp = ref(1);
const playerHp = ref(0);
const maxPlayerHp = ref(1);
const playerSp = ref(0);
const maxSp = ref(1);
const playerMp = ref(0);
const maxMp = ref(1);
const attacks = ref<Attack[]>([]);
const potions = ref<PotionState[]>([]);
const rewards = ref<Rewards | null>(null);

const flash = ref<'monster' | 'player' | null>(null);
const floatMonster = ref<number | null>(null);
const floatPlayer = ref<number | null>(null);
const floatHeal = ref<number | null>(null);
const critFlash = ref(false);
const dodgeFlash = ref(false);
const logLine = ref<string>('');

onMounted(async () => {
    try {
        applyView(await postJson(route('combat.start'), { node_id: props.nodeId }));
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Gagal memulai pertarungan.';
    } finally {
        loading.value = false;
    }
});

function applyView(v: Record<string, any>) {
    sessionId.value = v.session_id;
    status.value = v.status;
    monster.value = v.monster;
    monsterHp.value = v.monster_hp;
    maxMonsterHp.value = v.max_monster_hp;
    playerHp.value = v.player_hp;
    maxPlayerHp.value = v.max_player_hp;
    playerSp.value = v.player_sp ?? playerSp.value;
    maxSp.value = v.max_sp ?? maxSp.value;
    playerMp.value = v.player_mp ?? playerMp.value;
    maxMp.value = v.max_mp ?? maxMp.value;
    attacks.value = v.attacks ?? [];
    potions.value = v.potions ?? [];
}

function syncResources(r: Record<string, any>) {
    if (r.player_sp !== undefined) playerSp.value = r.player_sp;
    if (r.max_sp !== undefined) maxSp.value = r.max_sp;
    if (r.player_mp !== undefined) playerMp.value = r.player_mp;
    if (r.max_mp !== undefined) maxMp.value = r.max_mp;
}

async function useAttack(a: Attack) {
    if (acting.value || status.value !== 'active' || sessionId.value === null) return;
    if (!canAfford(a)) {
        actionError.value = `${a.resource === 'mp' ? 'MP' : 'SP'} tidak cukup untuk ${a.name} — butuh ${a.cost}.`;
        return;
    }
    acting.value = true;
    actionError.value = null;
    try {
        const r: Record<string, any> = await postJson(route('combat.act'), {
            session_id: sessionId.value,
            attack_kind: a.kind,
            attack_id: a.id,
        });

        monsterHp.value = r.monster_hp;
        playerHp.value = r.player_hp;
        syncResources(r);

        const resLabel = r.used.resource === 'mp' ? 'MP' : 'SP';
        flash.value = 'monster';
        floatMonster.value = r.used.damage;
        critFlash.value = !!r.used.crit;
        logLine.value = `Kamu memakai ${r.used.name} (${r.used.attack_kind}, −${r.used.damage}${r.used.crit ? ' KRITIKAL!' : ''}, −${r.used.cost} ${resLabel}).`;

        if (r.counter) {
            if (r.counter.dodged) {
                dodgeFlash.value = true;
                logLine.value += ` Kamu menghindar — 0 damage!`;
            } else {
                floatPlayer.value = r.counter.damage;
                logLine.value += ` ${monster.value.name} menyerang balik (${r.counter.kind}, −${r.counter.damage}).`;
            }
        }
        window.setTimeout(() => {
            flash.value = null;
            floatMonster.value = null;
            floatPlayer.value = null;
            critFlash.value = false;
            dodgeFlash.value = false;
        }, 900);

        status.value = r.status;
        attacks.value = r.attacks ?? attacks.value;
        potions.value = r.potions ?? potions.value;
        if (r.status === 'won') rewards.value = r.rewards ?? null;
    } catch (e) {
        // Serangan yang ditolak server (mis. resource kurang) tidak membatalkan
        // pertarungan — cukup tampilkan alasannya.
        actionError.value = e instanceof Error ? e.message : 'Aksi gagal.';
    } finally {
        acting.value = false;
    }
}

/** Ringkasan efek potion untuk label tombol (mis. "+30 HP" / "+30 SP"). */
function potionEffects(p: PotionState): string {
    const out: string[] = [];
    if ((p.heal ?? 0) > 0) out.push(`+${p.heal} HP`);
    if ((p.restore_sp ?? 0) > 0) out.push(`+${p.restore_sp} SP`);
    if ((p.restore_mp ?? 0) > 0) out.push(`+${p.restore_mp} MP`);
    return out.join(' · ') || 'item';
}

async function usePotion(p: PotionState) {
    if (acting.value || status.value !== 'active' || sessionId.value === null) return;
    acting.value = true;
    actionError.value = null;
    try {
        const r: Record<string, any> = await postJson(route('combat.use-item'), {
            session_id: sessionId.value,
            item_id: p.id,
        });

        playerHp.value = r.player_hp;
        syncResources(r);
        floatHeal.value = r.used.heal > 0 ? r.used.heal : null;
        logLine.value = r.used.heal > 0
            ? `Kamu meminum ${r.used.name} (+${r.used.heal} HP).`
            : `Kamu meminum ${r.used.name}.`;
        if (r.counter) {
            if (r.counter.dodged) {
                dodgeFlash.value = true;
                logLine.value += ` Kamu menghindar — 0 damage!`;
            } else {
                floatPlayer.value = r.counter.damage;
                logLine.value += ` ${monster.value.name} menyerang balik (−${r.counter.damage}).`;
            }
        }
        window.setTimeout(() => {
            floatHeal.value = null;
            floatPlayer.value = null;
            dodgeFlash.value = false;
        }, 900);

        status.value = r.status;
        attacks.value = r.attacks ?? attacks.value;
        potions.value = r.potions ?? potions.value;
    } catch (e) {
        actionError.value = e instanceof Error ? e.message : 'Gagal memakai item.';
    } finally {
        acting.value = false;
    }
}

function canAfford(a: Attack): boolean {
    return (a.resource === 'mp' ? playerMp.value : playerSp.value) >= a.cost;
}

// Dua jalur serangan yang dipisah: fisik memotong SP, sihir memotong MP.
const physicalAttacks = computed(() => attacks.value.filter((a) => a.resource === 'sp'));
const magicAttacks = computed(() => attacks.value.filter((a) => a.resource === 'mp'));

function continueStory() {
    router.visit(route('quests.play', props.questSlug));
}
</script>

<template>
    <div class="mx-auto max-w-2xl">
        <div v-if="loading" class="flex items-center justify-center gap-2 py-16 text-muted-foreground">
            <Loader2 class="h-5 w-5 animate-spin" /> Bersiap untuk bertarung...
        </div>

        <div v-else-if="error" class="rounded-xl border border-red-500/40 bg-red-500/10 p-4 text-sm text-red-600">
            {{ error }}
            <Button class="mt-3" variant="outline" @click="continueStory">Kembali</Button>
        </div>

        <div v-else class="space-y-6">
            <!-- Monster -->
            <div class="relative overflow-hidden rounded-xl border bg-card p-5" :class="{ 'animate-bounce': flash === 'monster' }">
                <div class="flex items-center gap-4">
                    <div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-lg bg-muted">
                        <img v-if="monster.image" :src="monster.image" :alt="monster.name" class="h-full w-full rounded-lg object-cover" />
                        <Swords v-else class="h-9 w-9 text-muted-foreground" />
                    </div>
                    <div class="flex-1">
                        <h3 class="mb-2 text-lg font-bold">{{ monster.name }}</h3>
                        <StatBar label="HP Monster" :value="monsterHp" :max="maxMonsterHp" color="bg-red-600" :flash="flash === 'monster'" />
                    </div>
                </div>
                <transition name="fade">
                    <div v-if="floatMonster !== null" class="pointer-events-none absolute right-6 top-3 text-2xl font-extrabold text-red-500">−{{ floatMonster }}</div>
                </transition>
                <transition name="fade">
                    <div v-if="critFlash" class="pointer-events-none absolute right-5 top-12 font-display text-lg font-extrabold text-amber-400 drop-shadow">KRITIKAL!</div>
                </transition>
            </div>

            <!-- Player HP -->
            <div
                class="rounded-xl border bg-card p-4"
                :class="{ 'ring-2 ring-red-500/60': floatPlayer !== null, 'ring-2 ring-emerald-500/60': floatHeal !== null }"
            >
                <StatBar label="HP-mu" :value="playerHp" :max="maxPlayerHp" color="bg-emerald-500" :flash="floatPlayer !== null || floatHeal !== null" />
                <transition name="fade">
                    <div v-if="floatHeal !== null" class="mt-1 text-right text-sm font-bold text-emerald-500">Kamu memulihkan +{{ floatHeal }} HP</div>
                </transition>
                <transition name="fade">
                    <div v-if="floatPlayer !== null" class="mt-1 text-right text-sm font-bold text-red-500">Kamu menerima −{{ floatPlayer }} HP</div>
                </transition>
                <transition name="fade">
                    <div v-if="dodgeFlash" class="mt-1 text-right text-sm font-bold text-sky-400">Menghindar! 0 damage</div>
                </transition>
                <div class="mt-3 grid grid-cols-2 gap-3">
                    <StatBar label="SP" :value="playerSp" :max="maxSp" color="bg-amber-500" />
                    <StatBar label="MP" :value="playerMp" :max="maxMp" color="bg-indigo-500" />
                </div>
            </div>

            <!-- Action log -->
            <div v-if="logLine && status === 'active'" class="rounded-lg bg-muted/50 px-4 py-2 text-center text-sm text-muted-foreground">
                {{ logLine }}
            </div>

            <!-- Galat aksi (mis. resource kurang) — pertarungan tetap berjalan -->
            <div
                v-if="actionError && status === 'active'"
                class="rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-2 text-center text-sm text-red-600"
            >
                {{ actionError }}
            </div>

            <!-- Serangan fisik (SP) -->
            <div v-if="status === 'active'" class="rounded-xl border bg-card p-5">
                <p class="mb-3 flex items-center gap-2 text-sm font-medium">
                    <Sword class="h-4 w-4 text-amber-500" /> Serangan Fisik
                    <span class="text-xs font-normal text-muted-foreground">— memakai SP</span>
                </p>
                <div class="grid gap-2 sm:grid-cols-2">
                    <Button
                        v-for="a in physicalAttacks"
                        :key="a.kind + a.id"
                        variant="outline"
                        :disabled="acting || !canAfford(a)"
                        class="h-auto justify-between py-3"
                        :title="canAfford(a) ? '' : `SP tidak cukup (butuh ${a.cost})`"
                        @click="useAttack(a)"
                    >
                        <span class="flex items-center gap-2">
                            <Sword class="h-4 w-4 text-amber-500" /> {{ a.name }}
                        </span>
                        <span class="flex items-center gap-2 text-xs">
                            <span class="text-muted-foreground">⚔{{ a.power }}</span>
                            <span :class="canAfford(a) ? 'text-amber-600' : 'text-red-500'">{{ a.cost }} SP</span>
                        </span>
                    </Button>
                </div>
                <p v-if="!physicalAttacks.length" class="text-sm text-muted-foreground">Belum ada skill fisik.</p>
            </div>

            <!-- Sihir (MP) -->
            <div v-if="status === 'active'" class="rounded-xl border bg-card p-5">
                <p class="mb-3 flex items-center gap-2 text-sm font-medium">
                    <Wand2 class="h-4 w-4 text-indigo-500" /> Sihir
                    <span class="text-xs font-normal text-muted-foreground">— memakai MP</span>
                </p>
                <div class="grid gap-2 sm:grid-cols-2">
                    <Button
                        v-for="a in magicAttacks"
                        :key="a.kind + a.id"
                        variant="outline"
                        :disabled="acting || !canAfford(a)"
                        class="h-auto justify-between py-3"
                        :title="canAfford(a) ? '' : `MP tidak cukup (butuh ${a.cost})`"
                        @click="useAttack(a)"
                    >
                        <span class="flex items-center gap-2">
                            <Wand2 class="h-4 w-4 text-indigo-500" /> {{ a.name }}
                        </span>
                        <span class="flex items-center gap-2 text-xs">
                            <span class="text-muted-foreground">✦{{ a.power }}</span>
                            <span :class="canAfford(a) ? 'text-indigo-500' : 'text-red-500'">{{ a.cost }} MP</span>
                        </span>
                    </Button>
                </div>
                <p v-if="!magicAttacks.length" class="text-sm text-muted-foreground">
                    Belum menguasai sihir — beli buku di Toko Sihir.
                </p>
            </div>

            <!-- Potions -->
            <div v-if="status === 'active' && potions.length" class="rounded-xl border bg-card p-5">
                <p class="mb-3 text-sm font-medium text-muted-foreground">Gunakan item:</p>
                <div class="grid gap-2 sm:grid-cols-2">
                    <Button
                        v-for="p in potions"
                        :key="p.id"
                        variant="outline"
                        :disabled="acting"
                        class="h-auto justify-between py-3"
                        @click="usePotion(p)"
                    >
                        <span class="flex items-center gap-2">
                            <FlaskConical class="h-4 w-4 text-emerald-500" /> {{ p.name }}
                        </span>
                        <span class="text-xs text-muted-foreground">{{ potionEffects(p) }} · ×{{ p.quantity }}</span>
                    </Button>
                </div>
                <p class="mt-2 text-xs text-muted-foreground">Meminum potion memakai giliranmu — monster tetap menyerang balik.</p>
            </div>

            <!-- Win -->
            <div v-else-if="status === 'won'" class="rounded-xl border border-emerald-500/40 bg-emerald-500/10 p-6 text-center">
                <Sparkles class="mx-auto mb-2 h-10 w-10 text-emerald-500" />
                <h3 class="text-xl font-bold text-emerald-600">Menang!</h3>
                <div v-if="rewards" class="mt-3 flex flex-col items-center gap-1 text-sm">
                    <span class="flex items-center gap-1"><Sparkles class="h-4 w-4" /> +{{ rewards.xp }} XP</span>
                    <span class="flex items-center gap-1"><Coins class="h-4 w-4 text-amber-500" /> +{{ rewards.gold }} emas</span>
                    <span v-if="rewards.items.length" class="text-muted-foreground">Jarahan: {{ rewards.items.map((i) => i.slug).join(', ') }}</span>
                    <span v-if="rewards.leveled_up" class="font-semibold text-violet-500">Naik level! Kini level {{ rewards.new_level }}</span>
                </div>
                <Button class="mt-4" @click="continueStory">Lanjut</Button>
            </div>

            <!-- Loss -->
            <div v-else-if="status === 'lost'" class="rounded-xl border border-red-500/40 bg-red-500/10 p-6 text-center">
                <Skull class="mx-auto mb-2 h-10 w-10 text-red-500" />
                <h3 class="text-xl font-bold text-red-600">Kamu tumbang...</h3>
                <p class="mt-1 text-sm text-muted-foreground">Kegelapan menelanmu. Petualanganmu terhenti di sini.</p>
                <Button class="mt-4" variant="secondary" @click="continueStory">Lanjut</Button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.6s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
