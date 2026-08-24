# M2/1 — Alokasi Poin Atribut

Tanggal: 24 Agustus 2026 · Status: disetujui, siap direncanakan

Sub-proyek **pertama** dari empat di M2 (progression jangka panjang). Tiga berikutnya —
**rank reward**, **misi berulang/harian**, **achievement** — masing-masing dapat spec
sendiri dan tidak dibahas di sini.

## Masalah

Naik level di Webio tidak menghadirkan keputusan apa pun. `LevelService::grantXp()`
menaikkan keenam atribut RPG (STR/AGI/DEX/INT/VIT/LUK) **+1 semuanya**, jadi dua karakter
di level yang sama selalu punya atribut identik. Sistem atributnya sendiri sudah punya efek
yang berbeda-beda dan tunable (STR → damage fisik, INT → damage sihir, AGI → hindar,
DEX+LUK → kritikal, VIT → pertahanan, LUK → emas), tapi tidak ada satu pun jalan bagi
pemain untuk memilih di antaranya. Build tidak ada; yang ada cuma angka level.

## Kriteria sukses

Dua pemain di level yang sama bisa punya karakter yang **terasa berbeda dimainkan** karena
pilihan mereka sendiri — dan keputusan itu bisa dikoreksi dengan biaya, bukan permanen
selamanya.

## Keputusan yang sudah diambil

| Keputusan | Pilihan | Alasan |
|---|---|---|
| Cakupan stat | Hanya 6 atribut RPG | HP/SP/MP/attack/defense/magic_attack/magic_defense tetap auto-growth — menjaga stabilitas dasar combat dan mencegah build yang rusak sendiri (mis. HP sangat rendah). Enam atribut ini yang punya makna gameplay jelas. |
| Poin per level | **5** | Sedikit di bawah 6 yang setara sistem lama, supaya spesialisasi punya trade-off nyata — bukan cuma lebih kuat di segala arah. |
| Bank poin | Boleh ditumpuk | Poin menunggu tidak memblokir progression lain kalau pemain menunda memilih. Alternatifnya (wajib habis sebelum naik level) butuh guard tambahan tanpa manfaat gameplay. |
| Cap per atribut | **Tanpa cap** | Efeknya sudah linear & tunable, dan AGI/DEX kritikal sudah di-clamp 100% di kode. Tanpa cap = tanpa aturan tambahan yang harus dijelaskan ke pemain. |
| Mekanik alokasi | Draft lokal `+`/`−`, satu `Simpan` | Satu request, satu transaksi, bisa dibatalkan sebelum commit — penting karena alokasi permanen sampai respec. |
| Respec | Ada, berbayar, di guild | Keputusan permanen tanpa jalan keluar membuat pemain takut bereksperimen; justru bereksperimen itu yang jadi isi fitur ini. |
| Karakter lama | Reset ke baseline + kembalikan seluruh pool | Adil: semua pemain memilih dari nol dengan aturan baru. Konsekuensinya diterima (lihat "Migrasi"). |
| Penempatan logika | Tanpa service baru | `LevelService` sudah pemilik hubungan level↔stat; `TownService` sudah pemilik "aksi di Tempat yang menagih emas" (pola `restCost`/`rest`). Nol file service baru. |

Alternatif yang ditolak: `StatPointService` baru (satu file untuk tiga method, sementara
`TownService` tetap harus dilibatkan untuk guard tempat & emas — cuma menambah lapisan),
dan menaruh logikanya langsung di controller (logika stat bocor ke controller, sulit diuji,
menyimpang dari pola server-authoritative repo).

## Model data & kurva

Kolom baru: `characters.pending_stat_points` — unsigned integer, default 0.

`LevelService::POINTS_PER_LEVEL = 5`. **Pool seumur hidup** karakter di level L:

```
pool(L) = 5 × (L − 1)
```

`grantXp()` berubah: keenam baris `$character->{atribut} += 1` **dihapus**, diganti
`$character->pending_stat_points += self::POINTS_PER_LEVEL` per level yang didapat. Yang
tidak berubah: pertumbuhan `max_hp` +10, `max_sp`/`max_mp` +4, `attack`/`magic_attack` +2,
`defense`/`magic_defense` +1, dan pemulihan penuh saat naik level.

Konsekuensi yang **disengaja**: `defense` efektif kini hanya tumbuh +1/level dari
auto-growth; sisanya bergantung VIT yang dipilih pemain. Itu memang inti perubahannya —
karakter yang mengabaikan VIT jadi lebih rapuh daripada di sistem lama.

Atribut tetap **berbasis 1** (`CombatService::bonusStat` = `max(0, stat − 1)`): nilai 1 =
baseline, efek 0. Aturan itu tidak disentuh.

## Alokasi

**Server** — `LevelService::allocate(Character $character, array $points): void`

- Kunci yang diterima hanya enam atribut; kunci lain → tolak. Kunci yang **tidak ada**
  dianggap 0, jadi klien boleh mengirim hanya atribut yang diubah.
- Tiap nilai harus integer ≥ 0.
- `array_sum($points)` harus ≥ 1 dan ≤ `pending_stat_points`. Karena batas bawahnya 1,
  karakter tanpa poin tersisa otomatis tertolak.
- Menambahkan tiap nilai ke atribut dasar, mengurangi `pending_stat_points` sebesar total.
- Caller (controller) membungkus dalam transaksi, konsisten dengan service lain.

**Rute** — `POST character/allocate` → `CharacterController@allocate`, payload
`{strength, agility, dexterity, intelligence, vitality, luck}`. Responsnya **redirect back +
flash** (sukses maupun galat), mengikuti pola `character.equip`/`unequip`/`learn` — bukan
JSON 422, karena halaman Karakter adalah halaman Inertia biasa.

**Payload karakter** — `StoryEngine::characterState()` dan interface `CharacterState`
(`resources/js/types/game.ts`) mendapat field `pending_stat_points`.

**UI** — di `resources/js/components/game/CharacterStats.vue`, bukan komponen baru: keenam
atribut sudah dirender di sana, jadi tombolnya duduk persis di sebelah stat yang
dipengaruhinya.

- Header panel menampilkan **"Poin tersedia: N"** bila `pending_stat_points > 0`.
- Tiap kartu atribut mendapat tombol `+` dan `−` yang **hanya mengubah draft lokal**.
- Tombol `+` nonaktif saat sisa draft 0; `−` nonaktif saat draft atribut itu 0.
- Tombol **Simpan** & **Batal** muncul hanya saat ada draft; Simpan mengirim satu request.
- Nilai yang ditampilkan tetap stat **efektif** (dasar + perlengkapan) seperti sekarang;
  draft ditambahkan di atasnya sebagai pratinjau.

**Umpan balik level-up** — payload reward di `CombatService` mendapat `stat_points_gained`,
dan `CombatView.vue` menambahkan "+N poin atribut" pada baris "Naik level!". Tanpa ini,
pemain yang naik level di tengah quest tidak tahu ada poin menunggu.

## Respec

`TownService::respecCost(Character) = 20 × level` — senada `restCost` (`10 × level`),
tunable di satu tempat.

`TownService::respec(Character, Place)` — server-authoritative, urutan guard:

1. Tempat berada di kota karakter (kalau tidak → 403, sama seperti aksi Tempat lain).
2. Kategori Tempat `adventurer_guild` **atau** `merchant_guild`. Dua-duanya boleh — latih
   ulang atribut bukan urusan afiliasi misi, jadi tidak di-gate seperti `acceptMission`.
3. Emas ≥ `respecCost`.
4. **Ada yang perlu diatur ulang**: kalau keenam atribut sudah 1 semua *dan*
   `pending_stat_points` sudah = `pool(level)`, tolak — supaya emas tidak hangus untuk
   operasi tanpa efek.

Lolos semua → potong emas, lalu `LevelService::resetAllocation()`.

`LevelService::resetAllocation(Character): void` — set keenam atribut ke **1** dan **set**
(bukan tambah) `pending_stat_points = pool(level)`. Karena set, memanggilnya dua kali tidak
menggandakan pool.

**Rute** — `POST town/{place:slug}/respec`. Tombol **Latih Ulang Atribut** beserta biayanya
tampil di panel guild `resources/js/pages/Town/Place.vue`.

## Migrasi karakter lama

Satu file migrasi: tambah kolom, lalu backfill semua karakter — keenam atribut → 1,
`pending_stat_points = 5 × (level − 1)`.

`down()`: set tiap atribut = `level` (nilai yang dihasilkan rumus lama, karena atribut mulai
dari 1 dan +1 tiap level), lalu hapus kolom. Diuji **dua arah** di MySQL dengan karakter
berlevel, mengikuti kebiasaan migrasi sebelumnya.

**Konsekuensi yang diterima:** poin yang pernah disetel manual Dewa lewat `/admin/players`
ikut hilang, karena backfill diturunkan dari level, bukan dari nilai atribut sekarang.
Karena itu form Pemain di Panel Dewa juga mendapat field `pending_stat_points`, sehingga
Dewa bisa mengembalikannya manual.

## Test

Tiap test di bawah harus **dibuktikan gagal** saat perbaikannya dilepas (kebiasaan repo ini
— lihat memori `webio-verifikasi-test`), kecuali yang ditandai penjaga regresi.

Level & poin:
- Naik level memberi tepat 5 poin dan **tidak** menaikkan keenam atribut (gagal kalau baris
  `+= 1` dikembalikan).
- Naik beberapa level sekaligus dari satu reward memberi 5 × jumlah level.
- Pertumbuhan HP/SP/MP/attack/defense masih terjadi (penjaga regresi).

Alokasi:
- Alokasi sah mengurangi `pending_stat_points` dan menaikkan atribut yang dituju.
- Total melebihi poin tersedia → ditolak, tidak ada perubahan.
- Total 0 → ditolak.
- Kunci di luar enam atribut → ditolak.
- Nilai negatif → ditolak.

Respec:
- Memotong emas sebesar `20 × level`, mereset atribut ke 1, pool = `5 × (level − 1)`.
- Emas kurang → ditolak, atribut utuh.
- Tempat bukan guild → ditolak.
- Tempat di kota lain → 403.
- Respec saat tidak ada alokasi → ditolak (emas tidak terpotong).
- Respec dua kali berturut-turut tidak menggandakan pool.

Migrasi:
- Naik-turun di MySQL dengan karakter berlevel; setelah `up()` pool = `5 × (level − 1)` dan
  atribut = 1; setelah `down()` atribut = `level`.

## Yang sengaja tidak masuk

| Ditunda | Alasan |
|---|---|
| Lencana "poin belum dialokasikan" di sidebar | Butuh plumbing `HandleInertiaRequests` seperti `unreadCount` forum. Pesan level-up di combat sudah memberi tahu; tambahkan kalau pemain ternyata masih kelewat. |
| Cap maksimum per atribut | Belum ada bukti build ekstrem bermasalah. Menambah aturan yang harus dijelaskan tanpa masalah yang dipecahkan. |
| Cooldown / batas jumlah respec | Biaya emas yang berskala level sudah jadi rem. |
| Respec lewat item (mis. "Air Lupa") | Satu jalur cukup untuk membuktikan mekaniknya jalan. |
| Alokasi HP/SP/MP/attack/defense | Sengaja auto-growth — lihat "Keputusan". |
