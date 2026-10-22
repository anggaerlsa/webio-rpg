<script setup lang="ts">
import AdminTabs from '@/components/admin/AdminTabs.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, X } from 'lucide-vue-next';
import { watch } from 'vue';

interface Npc {
    id: number;
    slug: string;
    name: string;
    role: string;
    gender: string;
    description: string | null;
    image: string | null;
    greeting: string;
    greeting_cold: string | null;
    greeting_warm: string | null;
    apology_reply: string | null;
    refusal_message: string | null;
    choices: Choice[] | null;
}
interface Choice {
    label: string;
    reply: string;
    affection: number;
    min_level: string | null;
}
interface Place {
    id: number;
    name: string;
    city: { id: number; name: string; province: { country: { id: number; name: string } } };
}

const props = defineProps<{ npc: Npc | null; place: Place; genders: Record<string, string>; levels: Record<string, string> }>();

const isEdit = !!props.npc;

const form = useForm({
    name: props.npc?.name ?? '',
    slug: props.npc?.slug ?? '',
    role: props.npc?.role ?? '',
    gender: props.npc?.gender ?? 'male',
    description: props.npc?.description ?? '',
    image: props.npc?.image ?? '',
    greeting: props.npc?.greeting ?? '',
    greeting_cold: props.npc?.greeting_cold ?? '',
    greeting_warm: props.npc?.greeting_warm ?? '',
    apology_reply: props.npc?.apology_reply ?? '',
    refusal_message: props.npc?.refusal_message ?? '',
    choices: (props.npc?.choices ?? []).map((c) => ({ ...c })),
});

const err = (key: string) => (form.errors as Record<string, string>)[key];

if (!isEdit) {
    watch(
        () => form.name,
        (n) => {
            form.slug = (n || '')
                .toLowerCase()
                .trim()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
        },
    );
}

function submit() {
    if (isEdit) form.put(route('admin.world.npcs.update', props.npc!.id));
    else form.post(route('admin.world.places.npcs.store', props.place.id));
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Dunia', href: route('admin.world.countries.index') },
    { title: props.place.city.name, href: route('admin.world.cities.show', props.place.city.id) },
    { title: `NPC ${props.place.name}`, href: route('admin.world.places.npcs.index', props.place.id) },
    { title: isEdit ? `Ubah ${props.npc!.name}` : 'NPC Baru', href: '#' },
];

const fieldClass = 'w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring';
</script>

<template>
    <Head :title="isEdit ? 'Ubah NPC' : 'Tambah NPC'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl space-y-6 p-4">
            <AdminTabs />

            <div class="rounded-xl border bg-card p-6">
                <h1 class="text-xl font-bold">{{ isEdit ? 'Ubah NPC' : 'Tambah NPC' }}</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Tempat: <span class="font-medium text-foreground">{{ place.city.name }} / {{ place.name }}</span>
                </p>

                <form class="mt-5 flex flex-col gap-5" @submit.prevent="submit">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="name">Nama</Label>
                            <Input id="name" v-model="form.name" required placeholder="mis. Garrick Thorne" />
                            <InputError :message="form.errors.name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="slug">Slug</Label>
                            <Input id="slug" v-model="form.slug" required placeholder="garrick-thorne" />
                            <InputError :message="form.errors.slug" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="role">Peran</Label>
                            <Input id="role" v-model="form.role" required placeholder="mis. Pandai Besi" />
                            <InputError :message="form.errors.role" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="gender">Jenis Kelamin</Label>
                            <select id="gender" v-model="form.gender" :class="fieldClass">
                                <option v-for="(label, key) in genders" :key="key" :value="key">{{ label }}</option>
                            </select>
                            <InputError :message="form.errors.gender" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="description">Deskripsi (opsional)</Label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="2"
                            :class="fieldClass"
                            placeholder="Penampilan, umur, watak..."
                        ></textarea>
                        <InputError :message="form.errors.description" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="image">URL Potret Full Body (opsional)</Label>
                        <Input id="image" v-model="form.image" placeholder="/images/npcs/garrick-thorne.webp" />
                        <InputError :message="form.errors.image" />
                    </div>

                    <div class="grid gap-4">
                        <div class="grid gap-2">
                            <Label for="greeting">Sapaan Netral</Label>
                            <textarea id="greeting" v-model="form.greeting" rows="2" required :class="fieldClass"></textarea>
                            <InputError :message="form.errors.greeting" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="greeting_cold">Sapaan Ketus (Tak Disukai & Dibenci)</Label>
                            <textarea id="greeting_cold" v-model="form.greeting_cold" rows="2" :class="fieldClass"></textarea>
                            <InputError :message="form.errors.greeting_cold" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="greeting_warm">Sapaan Hangat (Teman ke atas)</Label>
                            <textarea id="greeting_warm" v-model="form.greeting_warm" rows="2" :class="fieldClass"></textarea>
                            <InputError :message="form.errors.greeting_warm" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="apology_reply">Jawaban "Minta maaf"</Label>
                            <textarea id="apology_reply" v-model="form.apology_reply" rows="2" :class="fieldClass"></textarea>
                            <InputError :message="form.errors.apology_reply" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="refusal_message">Pesan Menolak Melayani (opsional)</Label>
                            <textarea
                                id="refusal_message"
                                v-model="form.refusal_message"
                                rows="2"
                                :class="fieldClass"
                                placeholder="Kosongkan untuk NPC yang tak pernah menolak (guild/istana)."
                            ></textarea>
                            <InputError :message="form.errors.refusal_message" />
                        </div>
                    </div>

                    <div class="grid gap-3">
                        <Label>Pilihan Dialog</Label>
                        <p class="text-xs text-muted-foreground">
                            Pemain memilih teks pilihan, NPC menjawab. Aksi toko/istirahat/misi & "Pamit" ditambahkan otomatis.
                        </p>
                        <div v-for="(c, i) in form.choices" :key="i" class="grid gap-2 rounded-lg border p-3">
                            <div class="flex items-start gap-2">
                                <div class="flex-1">
                                    <Input v-model="c.label" placeholder="Teks pilihan pemain" />
                                    <InputError :message="err(`choices.${i}.label`)" />
                                </div>
                                <Button type="button" size="sm" variant="outline" class="text-red-600" @click="form.choices.splice(i, 1)"
                                    ><X class="h-4 w-4"
                                /></Button>
                            </div>
                            <textarea v-model="c.reply" rows="2" :class="fieldClass" placeholder="Jawaban NPC"></textarea>
                            <InputError :message="err(`choices.${i}.reply`)" />
                            <div class="grid gap-2 sm:grid-cols-2">
                                <select v-model.number="c.affection" :class="fieldClass">
                                    <option :value="2">Disukai (+2 afeksi)</option>
                                    <option :value="0">Netral (0)</option>
                                    <option :value="-2">Tidak disukai (−2 afeksi)</option>
                                </select>
                                <select v-model="c.min_level" :class="fieldClass">
                                    <option :value="null">Terbuka sejak awal</option>
                                    <option v-for="(label, key) in levels" :key="key" :value="key">Terkunci sampai {{ label }}</option>
                                </select>
                            </div>
                            <InputError :message="err(`choices.${i}.affection`) || err(`choices.${i}.min_level`)" />
                        </div>
                        <div>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                @click="form.choices.push({ label: '', reply: '', affection: 0, min_level: null })"
                            >
                                <Plus class="mr-1 h-4 w-4" /> Tambah Pilihan
                            </Button>
                        </div>
                        <InputError :message="form.errors.choices" />
                    </div>

                    <div class="flex gap-3">
                        <Button type="submit" :disabled="form.processing">{{ isEdit ? 'Simpan Perubahan' : 'Buat NPC' }}</Button>
                        <Button type="button" variant="outline" @click="router.visit(route('admin.world.places.npcs.index', place.id))">Batal</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
