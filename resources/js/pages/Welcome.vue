<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    stats: { heroes: number; quests: number; monsters: number; cities: number };
}>();

const classes = [
    {
        name: 'Warrior',
        tagline: 'Baja & Keberanian',
        text: 'Berdiri di garis depan dengan pedang dan perisai. Kekuatan dan ketahanan adalah sumpahmu — tak ada musuh yang melewatimu.',
        attrs: ['STR', 'VIT', 'SP'],
    },
    {
        name: 'Mage',
        tagline: 'Rune & Kebijaksanaan',
        text: 'Kuasai mantra kuno dari buku-buku terlarang. Api, es, dan petir tunduk pada kehendak pikiranmu yang tajam.',
        attrs: ['INT', 'DEX', 'MP'],
    },
];

const features = [
    { icon: '⚔️', title: 'Pertarungan Bergiliran', text: 'Adu taktik skill & sihir. Kritikal, menghindar, dan enam atribut menentukan nasibmu.' },
    { icon: '🛡️', title: 'Rank F hingga S', text: 'Ambil misi di guild, tuntaskan, dan naikkan namamu hingga dikenal seluruh kerajaan.' },
    { icon: '🗡️', title: 'Perlengkapan Legendaris', text: 'Senjata, zirah, dan aksesori dari blacksmith yang menempa kekuatanmu.' },
    { icon: '📜', title: 'Ilmu dari Buku', text: 'Pelajari skill dan sihir baru dari kitab yang dijual di Toko Sihir.' },
    { icon: '🏰', title: 'Kota yang Hidup', text: 'Istirahat di penginapan, berdagang di pasar, dan temukan rumahmu.' },
    { icon: '🔥', title: 'Persaudaraan', text: 'Chat dunia, cari kawan seperjalanan, dan bentuk ikatan secara real-time.' },
];

const journey = [
    { step: 'I', title: 'Bangkitkan Pahlawanmu', text: 'Pilih nama, kota asal, dan jalanmu sebagai Warrior atau Mage.' },
    { step: 'II', title: 'Jawab Panggilan Guild', text: 'Ambil misi pertamamu dan hadapi monster yang mengancam negeri.' },
    { step: 'III', title: 'Tulis Legendamu', text: 'Naik dari rank F hingga S — dan biarkan para bard menyanyikan namamu.' },
];
</script>

<template>
    <Head title="Panggilan Kerajaan" />
    <div class="landing min-h-screen overflow-x-hidden bg-[#0b0d16] font-serif text-[#e8dcc0]">
        <!-- Navigasi -->
        <header class="absolute inset-x-0 top-0 z-20">
            <nav class="mx-auto flex max-w-6xl items-center justify-between px-4 py-5 sm:px-6">
                <span class="font-display text-xl font-bold tracking-[0.2em] text-[#e9c46a]">WEBIO</span>
                <div class="flex items-center gap-2 text-sm sm:gap-3">
                    <Link v-if="$page.props.auth.user" :href="route('dashboard')" class="btn-ghost">Masuk Kerajaan</Link>
                    <template v-else>
                        <Link :href="route('login')" class="btn-ghost">Masuk</Link>
                        <Link :href="route('register')" class="btn-ghost border-[#e9c46a]/50">Daftar</Link>
                    </template>
                </div>
            </nav>
        </header>

        <!-- Hero -->
        <section class="relative flex min-h-screen items-center justify-center overflow-hidden px-4 text-center">
            <!-- Gradasi = cadangan kalau gambar gagal dimuat -->
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top,#1d2347_0%,#0b0d16_65%)]" />
            <img
                src="/images/hero.webp"
                alt=""
                width="1536"
                height="1024"
                fetchpriority="high"
                class="absolute inset-0 h-full w-full object-cover object-[72%_center]"
            />
            <!-- Gelapkan bagian tengah supaya judul tetap terbaca -->
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_center,rgba(11,13,22,0.75)_0%,rgba(11,13,22,0.35)_55%,transparent_80%)]" aria-hidden="true" />
            <div class="fog absolute inset-x-0 bottom-0 h-40" aria-hidden="true" />

            <div class="relative z-10 max-w-3xl pb-24">
                <p class="mb-4 font-display text-xs uppercase tracking-[0.5em] text-[#e9c46a]/80 sm:text-sm">Sebuah dunia menantimu</p>
                <h1 class="title-glow font-display text-4xl font-bold leading-tight text-[#f6e7b8] sm:text-6xl md:text-7xl">
                    Kerajaan Memanggil Para Pahlawannya
                </h1>
                <p class="mx-auto mt-6 max-w-xl text-lg italic text-[#cfc2a3] sm:text-xl">
                    Kegelapan merayap di perbatasan. Guild membutuhkan pedang dan rune baru. Akankah kau menjawab panggilan itu?
                </p>
                <div class="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row">
                    <Link v-if="$page.props.auth.user" :href="route('dashboard')" class="btn-gold">Masuk Kerajaan</Link>
                    <template v-else>
                        <Link :href="route('register')" class="btn-gold">Mulai Petualangan</Link>
                        <Link :href="route('login')" class="btn-ghost px-6 py-3 text-base">Kembali ke Kerajaan</Link>
                    </template>
                </div>
            </div>
        </section>

        <!-- Statistik dunia -->
        <section class="relative z-10 -mt-12 px-4">
            <dl class="parchment mx-auto grid max-w-4xl grid-cols-2 gap-y-6 rounded-md px-6 py-6 text-center sm:grid-cols-4">
                <div v-for="(label, key) in { heroes: 'Petualang', quests: 'Quest', monsters: 'Monster', cities: 'Kota' }" :key="key">
                    <dd class="font-display text-3xl font-bold text-[#3b2410] sm:text-4xl">{{ stats[key] }}</dd>
                    <dt class="mt-1 text-sm uppercase tracking-widest text-[#6b4a26]">{{ label }}</dt>
                </div>
            </dl>
        </section>

        <!-- Pilih jalanmu -->
        <section class="mx-auto max-w-5xl px-4 py-24 sm:px-6">
            <h2 class="section-title">Pilih Jalanmu</h2>
            <p class="section-sub">Dua takdir. Satu legenda yang menunggu ditulis.</p>
            <div class="mt-12 grid gap-6 md:grid-cols-2">
                <article v-for="c in classes" :key="c.name" class="card group p-8">
                    <div class="mb-5 flex h-16 w-16 items-center justify-center rounded-full border border-[#e9c46a]/40 bg-[#e9c46a]/10">
                        <svg v-if="c.name === 'Warrior'" viewBox="0 0 24 24" class="h-8 w-8" fill="none" stroke="#e9c46a" stroke-width="1.6" aria-hidden="true">
                            <path d="M14.5 3H21v6.5L9 21.5 2.5 15 14.5 3z M5 12l7 7 M3 21l3-3" />
                        </svg>
                        <svg v-else viewBox="0 0 24 24" class="h-8 w-8" fill="none" stroke="#e9c46a" stroke-width="1.6" aria-hidden="true">
                            <path d="M12 2l1.8 4.2L18 8l-4.2 1.8L12 14l-1.8-4.2L6 8l4.2-1.8L12 2z M5 16l.9 2.1L8 19l-2.1.9L5 22l-.9-2.1L2 19l2.1-.9L5 16z M19 14l.7 1.6 1.6.7-1.6.7L19 18.6l-.7-1.6-1.6-.7 1.6-.7L19 14z" />
                        </svg>
                    </div>
                    <p class="font-display text-xs uppercase tracking-[0.3em] text-[#e9c46a]/70">{{ c.tagline }}</p>
                    <h3 class="mt-1 font-display text-3xl font-bold text-[#f6e7b8]">{{ c.name }}</h3>
                    <p class="mt-3 text-lg leading-relaxed text-[#cfc2a3]">{{ c.text }}</p>
                    <div class="mt-5 flex gap-2">
                        <span v-for="a in c.attrs" :key="a" class="rounded border border-[#e9c46a]/30 px-2 py-0.5 font-sans text-xs tracking-wider text-[#e9c46a]">
                            {{ a }}
                        </span>
                    </div>
                </article>
            </div>
        </section>

        <!-- Fitur -->
        <section class="border-y border-[#e9c46a]/10 bg-[#0f1220] px-4 py-24 sm:px-6">
            <div class="mx-auto max-w-6xl">
                <h2 class="section-title">Takdirmu Menanti</h2>
                <p class="section-sub">Semua yang dibutuhkan seorang pahlawan — dan bahaya yang mengintai di baliknya.</p>
                <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <article v-for="f in features" :key="f.title" class="card p-6">
                        <div class="text-3xl" aria-hidden="true">{{ f.icon }}</div>
                        <h3 class="mt-3 font-display text-xl font-semibold text-[#f6e7b8]">{{ f.title }}</h3>
                        <p class="mt-2 leading-relaxed text-[#cfc2a3]">{{ f.text }}</p>
                    </article>
                </div>
            </div>
        </section>

        <!-- Perjalanan -->
        <section class="mx-auto max-w-5xl px-4 py-24 sm:px-6">
            <h2 class="section-title">Perjalanan Sang Pahlawan</h2>
            <ol class="mt-12 grid gap-10 md:grid-cols-3">
                <li v-for="j in journey" :key="j.step" class="text-center">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full border-2 border-[#e9c46a] font-display text-2xl font-bold text-[#e9c46a] shadow-[0_0_24px_rgba(233,196,106,0.25)]">
                        {{ j.step }}
                    </div>
                    <h3 class="mt-4 font-display text-xl font-semibold text-[#f6e7b8]">{{ j.title }}</h3>
                    <p class="mt-2 text-[#cfc2a3]">{{ j.text }}</p>
                </li>
            </ol>
        </section>

        <!-- Ajakan akhir -->
        <section class="relative overflow-hidden px-4 py-24 text-center">
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_center,rgba(242,165,65,0.18)_0%,transparent_60%)]" />
            <div class="relative mx-auto max-w-2xl">
                <h2 class="title-glow font-display text-3xl font-bold text-[#f6e7b8] sm:text-5xl">Gerbang Telah Terbuka</h2>
                <p class="mt-4 text-xl italic text-[#cfc2a3]">Akankah kau menjawab panggilan itu?</p>
                <div class="mt-10">
                    <Link v-if="$page.props.auth.user" :href="route('dashboard')" class="btn-gold">Masuk Kerajaan</Link>
                    <Link v-else :href="route('register')" class="btn-gold">Mulai Petualangan</Link>
                </div>
            </div>
        </section>

        <footer class="border-t border-[#e9c46a]/10 px-4 py-8 text-center text-sm text-[#8a7f68]">
            <span class="font-display tracking-[0.2em] text-[#e9c46a]/70">WEBIO</span> · Ditempa untuk para petualang
        </footer>
    </div>
</template>

<style scoped>
.btn-gold {
    @apply inline-block rounded-sm border border-[#f6e7b8]/60 bg-gradient-to-b from-[#f0cf78] to-[#c9973a] px-8 py-3 font-display text-base font-bold uppercase tracking-widest text-[#2a1a08] shadow-[0_0_30px_rgba(233,196,106,0.35)] transition hover:shadow-[0_0_45px_rgba(233,196,106,0.6)] hover:brightness-110 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#e9c46a];
}
.btn-ghost {
    @apply inline-block rounded-sm border border-transparent px-4 py-1.5 font-display tracking-wider text-[#e8dcc0] transition hover:border-[#e9c46a]/60 hover:text-[#f6e7b8] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#e9c46a];
}
.section-title {
    @apply text-center font-display text-3xl font-bold text-[#f6e7b8] sm:text-4xl;
}
.section-sub {
    @apply mx-auto mt-3 max-w-xl text-center text-lg italic text-[#a99d82];
}
.card {
    @apply rounded-md border border-[#e9c46a]/15 bg-gradient-to-b from-[#171b30] to-[#10131f] transition duration-300 hover:-translate-y-1 hover:border-[#e9c46a]/50 hover:shadow-[0_10px_40px_rgba(233,196,106,0.12)];
}
.parchment {
    background: linear-gradient(180deg, #efe0bb, #dcc595);
    box-shadow:
        0 20px 50px rgba(0, 0, 0, 0.5),
        inset 0 0 40px rgba(120, 80, 30, 0.35);
    border: 1px solid #b08a4f;
}
.title-glow {
    text-shadow: 0 0 30px rgba(233, 196, 106, 0.35);
}
.fog {
    background: linear-gradient(to top, #0b0d16 15%, transparent);
}
@media (prefers-reduced-motion: reduce) {
    .card {
        transition: none;
    }
}
</style>
