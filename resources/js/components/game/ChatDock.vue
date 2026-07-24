<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { deleteJson, getJson, postJson } from '@/lib/gameApi';
import { ArrowLeft, Check, Globe, MessageSquare, Search, Send, UserPlus, Users, X } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

interface ChatMsg { id: number; user_id: number; name: string; body: string; at: string }
interface Person { friendship_id: number | null; user_id: number; name: string; status?: string }

const me = ref<{ user_id: number; name: string; can_chat: boolean } | null>(null);
const open = ref(false);
const tab = ref<'world' | 'friends'>('world');

const world = ref<ChatMsg[]>([]);
const worldInput = ref('');
const worldBox = ref<HTMLElement | null>(null);

const friends = ref<Person[]>([]);
const incoming = ref<Person[]>([]);
const outgoing = ref<Person[]>([]);

const query = ref('');
const results = ref<Person[]>([]);
let searchTimer: ReturnType<typeof setTimeout> | null = null;

const dm = ref<Person | null>(null);
const dmMsgs = ref<ChatMsg[]>([]);
const dmInput = ref('');
const dmBox = ref<HTMLElement | null>(null);
let dmChannel: string | null = null;

const error = ref('');
const requestCount = computed(() => incoming.value.length);

function echo(): any {
    return (window as any).Echo;
}

function time(at: string): string {
    try {
        return new Date(at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    } catch {
        return '';
    }
}

function pushUnique(list: ChatMsg[], msg: ChatMsg) {
    if (!list.some((m) => m.id === msg.id)) list.push(msg);
}

async function scrollToBottom(box: HTMLElement | null) {
    await nextTick();
    if (box) box.scrollTop = box.scrollHeight;
}

// --- World chat ---
async function loadWorld() {
    const r = await getJson<{ messages: ChatMsg[] }>(route('chat.world'));
    world.value = r.messages;
    scrollToBottom(worldBox.value);
}
async function sendWorld() {
    const body = worldInput.value.trim();
    if (!body) return;
    worldInput.value = '';
    try {
        const r = await postJson<{ message: ChatMsg }>(route('chat.world.post'), { body });
        pushUnique(world.value, r.message);
        scrollToBottom(worldBox.value);
    } catch (e: any) {
        error.value = e.message;
    }
}

// --- Friends ---
async function loadFriends() {
    const r = await getJson<{ friends: Person[]; incoming: Person[]; outgoing: Person[] }>(route('friends.index'));
    friends.value = r.friends;
    incoming.value = r.incoming;
    outgoing.value = r.outgoing;
}
function onQuery() {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(runSearch, 300);
}
async function runSearch() {
    const q = query.value.trim();
    if (!q) {
        results.value = [];
        return;
    }
    const r = await getJson<{ results: Person[] }>(route('friends.search') + '?q=' + encodeURIComponent(q));
    results.value = r.results;
}
async function addFriend(p: Person) {
    try {
        await postJson(route('friends.request'), { user_id: p.user_id });
        await Promise.all([loadFriends(), runSearch()]);
    } catch (e: any) {
        error.value = e.message;
    }
}
async function acceptReq(p: Person) {
    if (!p.friendship_id) return;
    await postJson(route('friends.accept', p.friendship_id), {});
    await loadFriends();
}
async function removeFriendship(p: Person) {
    if (!p.friendship_id) return;
    await deleteJson(route('friends.destroy', p.friendship_id));
    if (dm.value?.friendship_id === p.friendship_id) closeDm();
    await Promise.all([loadFriends(), runSearch()]);
}

// --- DM ---
async function openDm(friend: Person) {
    closeDm();
    dm.value = friend;
    const r = await getJson<{ messages: ChatMsg[] }>(route('chat.dm', friend.friendship_id!));
    dmMsgs.value = r.messages;
    scrollToBottom(dmBox.value);
    dmChannel = 'chat.dm.' + friend.friendship_id;
    echo()?.private(dmChannel).listen('.message', (m: ChatMsg) => {
        pushUnique(dmMsgs.value, m);
        scrollToBottom(dmBox.value);
    });
}
function closeDm() {
    if (dmChannel) {
        echo()?.leave(dmChannel);
        dmChannel = null;
    }
    dm.value = null;
    dmMsgs.value = [];
    dmInput.value = '';
}
async function sendDm() {
    const body = dmInput.value.trim();
    if (!body || !dm.value) return;
    dmInput.value = '';
    try {
        const r = await postJson<{ message: ChatMsg }>(route('chat.dm.post', dm.value.friendship_id!), { body });
        pushUnique(dmMsgs.value, r.message);
        scrollToBottom(dmBox.value);
    } catch (e: any) {
        error.value = e.message;
    }
}

function toggle() {
    open.value = !open.value;
    localStorage.setItem('chatOpen', open.value ? '1' : '0');
    if (open.value && tab.value === 'world') scrollToBottom(worldBox.value);
}

onMounted(async () => {
    try {
        me.value = await getJson(route('chat.me'));
    } catch {
        return;
    }
    if (!me.value?.can_chat) return;
    open.value = localStorage.getItem('chatOpen') === '1';

    await Promise.all([loadWorld(), loadFriends()]);

    echo()?.channel('chat.world').listen('.message', (m: ChatMsg) => {
        pushUnique(world.value, m);
        if (open.value && tab.value === 'world') scrollToBottom(worldBox.value);
    });
    echo()?.private('App.Models.User.' + me.value.user_id).listen('.friendship', () => loadFriends());
});

onBeforeUnmount(() => {
    closeDm();
    echo()?.leave('chat.world');
    if (me.value?.user_id) echo()?.leave('App.Models.User.' + me.value.user_id);
});

watch(error, (v) => {
    if (v) setTimeout(() => (error.value = ''), 4000);
});
</script>

<template>
    <div v-if="me?.can_chat">
        <!-- Tombol mengambang -->
        <button
            v-if="!open"
            type="button"
            class="fixed bottom-4 right-4 z-40 flex h-12 w-12 items-center justify-center rounded-full border border-amber-500/40 bg-card shadow-lg transition hover:bg-accent"
            title="Chat & Teman"
            @click="toggle"
        >
            <MessageSquare class="h-5 w-5 text-amber-500" />
            <span v-if="requestCount" class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1 text-[11px] font-bold text-white">{{ requestCount }}</span>
        </button>

        <!-- Panel -->
        <div v-show="open" class="fixed inset-y-0 right-0 z-40 flex w-full max-w-[360px] flex-col border-l bg-card shadow-2xl">
            <!-- Header / tabs -->
            <div class="flex items-center justify-between border-b px-3 py-2">
                <div class="flex gap-1">
                    <button
                        type="button"
                        class="flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-sm font-medium"
                        :class="tab === 'world' ? 'bg-primary text-primary-foreground' : 'hover:bg-accent'"
                        @click="tab = 'world'"
                    >
                        <Globe class="h-4 w-4" /> Dunia
                    </button>
                    <button
                        type="button"
                        class="relative flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-sm font-medium"
                        :class="tab === 'friends' ? 'bg-primary text-primary-foreground' : 'hover:bg-accent'"
                        @click="tab = 'friends'"
                    >
                        <Users class="h-4 w-4" /> Teman
                        <span v-if="requestCount" class="ml-0.5 rounded-full bg-red-500 px-1 text-[10px] font-bold text-white">{{ requestCount }}</span>
                    </button>
                </div>
                <button type="button" class="rounded-md p-1.5 hover:bg-accent" title="Tutup" @click="toggle"><X class="h-4 w-4" /></button>
            </div>

            <div v-if="error" class="border-b bg-red-500/10 px-3 py-1.5 text-xs text-red-600">{{ error }}</div>

            <!-- WORLD -->
            <template v-if="tab === 'world'">
                <div ref="worldBox" class="flex-1 space-y-2 overflow-y-auto px-3 py-3">
                    <div v-if="!world.length" class="pt-6 text-center text-sm text-muted-foreground">Belum ada obrolan. Sapa dunia!</div>
                    <div v-for="m in world" :key="m.id" class="text-sm">
                        <span class="font-semibold" :class="m.user_id === me.user_id ? 'text-amber-600' : 'text-primary'">{{ m.name }}</span>
                        <span class="ml-1 text-[10px] text-muted-foreground">{{ time(m.at) }}</span>
                        <div class="break-words text-foreground/90">{{ m.body }}</div>
                    </div>
                </div>
                <form class="flex gap-2 border-t p-2" @submit.prevent="sendWorld">
                    <input v-model="worldInput" maxlength="500" placeholder="Pesan ke dunia..." class="min-w-0 flex-1 rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring" />
                    <Button type="submit" size="icon" :disabled="!worldInput.trim()"><Send class="h-4 w-4" /></Button>
                </form>
            </template>

            <!-- FRIENDS / DM -->
            <template v-else>
                <!-- DM thread -->
                <template v-if="dm">
                    <div class="flex items-center gap-2 border-b px-3 py-2">
                        <button type="button" class="rounded-md p-1 hover:bg-accent" @click="closeDm"><ArrowLeft class="h-4 w-4" /></button>
                        <span class="font-semibold">{{ dm.name }}</span>
                    </div>
                    <div ref="dmBox" class="flex-1 space-y-2 overflow-y-auto px-3 py-3">
                        <div v-if="!dmMsgs.length" class="pt-6 text-center text-sm text-muted-foreground">Mulai percakapan pribadi.</div>
                        <div v-for="m in dmMsgs" :key="m.id" class="flex" :class="m.user_id === me.user_id ? 'justify-end' : 'justify-start'">
                            <div class="max-w-[80%] rounded-lg px-3 py-1.5 text-sm" :class="m.user_id === me.user_id ? 'bg-primary text-primary-foreground' : 'bg-muted'">
                                <div class="break-words">{{ m.body }}</div>
                                <div class="mt-0.5 text-right text-[10px] opacity-70">{{ time(m.at) }}</div>
                            </div>
                        </div>
                    </div>
                    <form class="flex gap-2 border-t p-2" @submit.prevent="sendDm">
                        <input v-model="dmInput" maxlength="500" placeholder="Pesan pribadi..." class="min-w-0 flex-1 rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring" />
                        <Button type="submit" size="icon" :disabled="!dmInput.trim()"><Send class="h-4 w-4" /></Button>
                    </form>
                </template>

                <!-- Friends list + search -->
                <div v-else class="flex-1 overflow-y-auto px-3 py-3 space-y-4">
                    <!-- Search -->
                    <div>
                        <div class="relative">
                            <Search class="pointer-events-none absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                            <input v-model="query" placeholder="Cari pemain..." class="w-full rounded-md border border-input bg-background py-2 pl-8 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring" @input="onQuery" />
                        </div>
                        <div v-if="results.length" class="mt-2 space-y-1">
                            <div v-for="p in results" :key="p.user_id" class="flex items-center justify-between rounded-md border bg-background px-3 py-2 text-sm">
                                <span class="truncate">{{ p.name }}</span>
                                <Button v-if="p.status === 'none'" size="sm" variant="outline" @click="addFriend(p)"><UserPlus class="mr-1 h-3.5 w-3.5" /> Tambah</Button>
                                <span v-else-if="p.status === 'outgoing'" class="text-xs text-muted-foreground">Menunggu</span>
                                <Button v-else-if="p.status === 'incoming'" size="sm" variant="outline" @click="acceptReq(p)"><Check class="mr-1 h-3.5 w-3.5" /> Terima</Button>
                                <Button v-else-if="p.status === 'friends'" size="sm" variant="ghost" @click="openDm(p)">Chat</Button>
                            </div>
                        </div>
                    </div>

                    <!-- Incoming requests -->
                    <div v-if="incoming.length">
                        <div class="mb-1 text-xs font-semibold uppercase text-muted-foreground">Permintaan ({{ incoming.length }})</div>
                        <div v-for="p in incoming" :key="p.friendship_id!" class="flex items-center justify-between rounded-md border bg-background px-3 py-2 text-sm">
                            <span class="truncate">{{ p.name }}</span>
                            <div class="flex gap-1">
                                <Button size="sm" variant="outline" @click="acceptReq(p)"><Check class="h-3.5 w-3.5" /></Button>
                                <Button size="sm" variant="ghost" class="text-red-600" @click="removeFriendship(p)"><X class="h-3.5 w-3.5" /></Button>
                            </div>
                        </div>
                    </div>

                    <!-- Friends -->
                    <div>
                        <div class="mb-1 text-xs font-semibold uppercase text-muted-foreground">Teman ({{ friends.length }})</div>
                        <div v-if="!friends.length" class="rounded-md border border-dashed px-3 py-4 text-center text-xs text-muted-foreground">Belum punya teman. Cari pemain di atas.</div>
                        <div v-for="p in friends" :key="p.friendship_id!" class="group flex items-center justify-between rounded-md border bg-background px-3 py-2 text-sm">
                            <button type="button" class="flex-1 truncate text-left hover:text-primary" @click="openDm(p)">{{ p.name }}</button>
                            <Button size="sm" variant="ghost" class="text-red-600 opacity-0 transition group-hover:opacity-100" title="Hapus teman" @click="removeFriendship(p)"><X class="h-3.5 w-3.5" /></Button>
                        </div>
                    </div>

                    <!-- Outgoing -->
                    <div v-if="outgoing.length">
                        <div class="mb-1 text-xs font-semibold uppercase text-muted-foreground">Terkirim</div>
                        <div v-for="p in outgoing" :key="p.friendship_id!" class="flex items-center justify-between rounded-md border bg-background px-3 py-2 text-sm text-muted-foreground">
                            <span class="truncate">{{ p.name }}</span>
                            <Button size="sm" variant="ghost" class="text-red-600" @click="removeFriendship(p)">Batal</Button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>
