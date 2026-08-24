<script setup lang="ts">
import AdminTabs from '@/components/admin/AdminTabs.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from 'lucide-vue-next';

interface Choice {
    label: string;
    next_node_key: string;
    requirements: string;
    effects: string;
}
interface Node {
    id: number;
    key: string;
    type: string;
    title: string | null;
    body: string | null;
    image: string | null;
    monster_id: number | null;
    payload: unknown;
    choices?: Array<{ label: string; next_node_key: string | null; requirements: unknown; effects: unknown }>;
}
interface MonsterLite {
    id: number;
    name: string;
    slug: string;
}

const props = defineProps<{
    quest: { id: number; slug: string; title: string };
    node: Node | null;
    types: string[];
    monsters: MonsterLite[];
}>();

const isEdit = !!props.node;
const typeLabels: Record<string, string> = {
    narrative: 'Narasi',
    choice: 'Pilihan',
    combat: 'Pertarungan',
    reward: 'Hadiah',
    ending: 'Akhir',
};

const form = useForm({
    key: props.node?.key ?? '',
    type: props.node?.type ?? 'narrative',
    title: props.node?.title ?? '',
    body: props.node?.body ?? '',
    image: props.node?.image ?? '',
    monster_id: props.node?.monster_id ?? '',
    payload: props.node?.payload ? JSON.stringify(props.node.payload, null, 2) : '',
    choices: (props.node?.choices ?? []).map((c) => ({
        label: c.label,
        next_node_key: c.next_node_key ?? '',
        requirements: c.requirements ? JSON.stringify(c.requirements) : '',
        effects: c.effects ? JSON.stringify(c.effects) : '',
    })) as Choice[],
});

function addChoice() {
    form.choices.push({ label: '', next_node_key: '', requirements: '', effects: '' });
}
function removeChoice(i: number) {
    form.choices.splice(i, 1);
}
function cErr(i: number, field: string): string | undefined {
    return (form.errors as Record<string, string>)[`choices.${i}.${field}`];
}

function submit() {
    if (isEdit) form.put(route('admin.quests.nodes.update', [props.quest.id, props.node!.id]));
    else form.post(route('admin.quests.nodes.store', props.quest.id));
}

const payloadHints: Record<string, string> = {
    combat: '{"on_win_node_key": "menang", "on_lose_node_key": "kalah"}',
    reward: '{"xp": 25, "gold": 20, "item_slugs": ["potion"]}',
    ending: '{"result": "victory"}',
    narrative: '',
    choice: '',
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Misi', href: '/admin/quests' },
    { title: props.quest.title, href: route('admin.quests.edit', props.quest.id) },
    { title: isEdit ? 'Ubah Adegan' : 'Adegan Baru', href: '#' },
];

const fieldClass = 'w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring';
</script>

<template>
    <Head :title="isEdit ? 'Ubah Adegan' : 'Adegan Baru'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl space-y-6 p-4">
            <AdminTabs />

            <form class="space-y-6" @submit.prevent="submit">
                <div class="rounded-xl border bg-card p-6">
                    <h1 class="text-xl font-bold">{{ isEdit ? 'Ubah Adegan' : 'Adegan Baru' }}</h1>
                    <p class="text-sm text-muted-foreground">Misi: {{ quest.title }}</p>

                    <div class="mt-5 flex flex-col gap-5">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="key">Key (kunci unik)</Label>
                                <Input id="key" v-model="form.key" required placeholder="mis. intro" />
                                <InputError :message="form.errors.key" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="type">Tipe</Label>
                                <select id="type" v-model="form.type" :class="fieldClass">
                                    <option v-for="t in types" :key="t" :value="t">{{ typeLabels[t] ?? t }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label for="title">Judul (opsional)</Label>
                            <Input id="title" v-model="form.title" placeholder="mis. Mulut Gua" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="body">Narasi / Teks (opsional)</Label>
                            <textarea id="body" v-model="form.body" rows="3" :class="fieldClass"></textarea>
                        </div>
                        <div class="grid gap-2">
                            <Label for="image">URL Gambar (opsional)</Label>
                            <Input id="image" v-model="form.image" placeholder="/images/nodes/gua.png" />
                        </div>

                        <div v-if="form.type === 'combat'" class="grid gap-2">
                            <Label for="monster_id">Monster</Label>
                            <select id="monster_id" v-model="form.monster_id" :class="fieldClass">
                                <option value="">— tanpa monster —</option>
                                <option v-for="m in monsters" :key="m.id" :value="m.id">{{ m.name }} ({{ m.slug }})</option>
                            </select>
                            <p class="text-xs text-muted-foreground">Adegan pertarungan butuh monster + payload <code>on_win_node_key</code> / <code>on_lose_node_key</code>.</p>
                            <InputError :message="form.errors.monster_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="payload">Payload — JSON (opsional)</Label>
                            <textarea id="payload" v-model="form.payload" rows="2" :class="fieldClass" :placeholder="payloadHints[form.type]"></textarea>
                            <InputError :message="form.errors.payload" />
                        </div>
                    </div>
                </div>

                <!-- Editor pilihan -->
                <div class="rounded-xl border bg-card p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">Pilihan</h2>
                            <p class="text-sm text-muted-foreground">Tombol yang bisa dipilih pemain di adegan ini.</p>
                        </div>
                        <Button type="button" variant="outline" size="sm" @click="addChoice"><Plus class="mr-1 h-4 w-4" /> Pilihan</Button>
                    </div>

                    <div v-if="!form.choices.length" class="mt-4 rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                        Belum ada pilihan. Adegan akhir (ending) & pertarungan boleh tanpa pilihan.
                    </div>

                    <div v-for="(c, ci) in form.choices" :key="ci" class="mt-4 grid gap-3 rounded-lg border p-4">
                        <div class="flex items-start gap-2">
                            <div class="grid flex-1 gap-1">
                                <Label>Label</Label>
                                <Input v-model="c.label" placeholder="mis. Masuk ke dalam" />
                                <InputError :message="cErr(ci, 'label')" />
                            </div>
                            <Button type="button" variant="ghost" size="sm" class="mt-6 text-red-600" @click="removeChoice(ci)"><Trash2 class="h-4 w-4" /></Button>
                        </div>
                        <div class="grid gap-1">
                            <Label>Menuju adegan (key)</Label>
                            <Input v-model="c.next_node_key" placeholder="mis. fork" />
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="grid gap-1">
                                <Label>Syarat — JSON</Label>
                                <Input v-model="c.requirements" placeholder='{"has_item":"torch"}' />
                                <InputError :message="cErr(ci, 'requirements')" />
                            </div>
                            <div class="grid gap-1">
                                <Label>Efek — JSON</Label>
                                <Input v-model="c.effects" placeholder='{"gold":10,"xp":5}' />
                                <InputError :message="cErr(ci, 'effects')" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3">
                    <Button type="submit" :disabled="form.processing">{{ isEdit ? 'Simpan Perubahan' : 'Tambah Adegan' }}</Button>
                    <Button type="button" variant="outline" @click="router.visit(route('admin.quests.edit', quest.id))">Batal</Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
