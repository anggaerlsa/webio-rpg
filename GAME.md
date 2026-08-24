# Webio — Game RPG Fantasi Interaktif

Game cerita/RPG berbasis web (gaya *hogwarts.io*): quest & dungeon dengan **combat berbasis serangan** —
pemain memilih skill/spell yang dikuasai (semua punya **Pukul** secara default), monster membalas. Login + progres tersimpan di server.

## Stack
- **Laravel 12** (vue-starter-kit **v1.0.2**) + **Inertia 2** + **Vue 3** + **Tailwind 3** + shadcn-vue
- **Real-time**: **Laravel Reverb** (websocket) + **Laravel Echo** + pusher-js — untuk chat & pertemanan (lihat **Chat & Pertemanan**).
- **Tema visual RPG fantasy** (di `resources/css/app.css`): palet via variabel CSS — light "perkamen" (krem + tinta sepia + aksen perunggu/emas), dark "dungeon" (obsidian + emas berpendar). Judul pakai font **Cinzel** (`.font-display`), narasi quest pakai **EB Garamond** (`.font-serif`); bar status (HP/SP/MP/XP) berkilau seperti gauge. Mengubah palet = ubah variabel di `app.css` (seluruh komponen ikut). Warna stat (HP merah/hijau, SP amber, MP indigo) sengaja tetap eksplisit.
- **PHP 8.2** (XAMPP) · **MySQL/MariaDB** (database `webio`)
- Logika game **server-authoritative**: semua HP/damage/RNG/reward dihitung di server (anti-cheat).
  Klien hanya mengirim `{node_id}` / `{session_id, attack_kind, attack_id}`.

## Karakter & onboarding
- **1 akun = 1 karakter.** Pembuatan karakter: nama, **gender** (Pria/Wanita), **tanggal lahir** (→ usia), **class** (**Warrior**/**Mage** saja untuk sekarang). Stat default sama untuk semua class.
- **Job** (user-meta) mulai **"Commoner"**. Setelah karakter dibuat, dashboard menampilkan **modal afiliasi**: **Guild Petualang** atau **Guild Merchant**.
  - Petualang → job `adventurer`, `rank = F`, dapat item **Kartu Tanda Petualang**.
  - Merchant → job `merchant`, `rank = F`, dapat item **Kartu Tanda Dagang**.
- **Hadiah pembuka:** karakter baru langsung menerima **satu senjata latihan sesuai class** dan **otomatis memakainya** — Warrior → **Pedang Latihan**, Mage → **Tongkat Latihan** (`CharacterController::STARTER_WEAPON` + `giveStarterWeapon`). Senjata latihan lain (Busur Panah/Belati/Tombak Latihan) dijual murah di Blacksmith. Kalau itemnya belum diimpor atau syarat levelnya diubah admin, pembuatan karakter tetap jalan (hadiahnya saja yang dilewati/tidak terpasang).
- Class & guild sengaja dibatasi 2 pilihan dulu; rank mulai dari F. Ekspansi (class/guild/rank lain) menyusul.

## Combat (model serangan)
- **Dua jalur serangan yang terpisah penuh** — `app/Services/Combat/`: `PhysicalAttack` (skill, **SP**, diskala **STR**, diperkuat `attack`, ditahan `defense`+**VIT**) dan `MagicalAttack` (sihir, **MP**, diskala **INT**, diperkuat `magic_attack`, ditahan `magic_defense`+**INT**). Keduanya turunan `AttackModule`; `CombatService` cuma memilih modul lalu memakainya, jadi menambah jalur baru tidak menyentuh mesin combat. Stat karakter: `attack`/`defense` (fisik) & `magic_attack`/`magic_defense` (sihir) — keduanya tumbuh sama saat naik level (+2/+1) dan bisa ditambah perlengkapan.
- **Monster punya kedua sisi juga:** `attack`/`defense` + `magic_attack`/`magic_defense`, dan `attack_kind` (`physical`|`magic`) menentukan serangan baliknya lewat jalur mana — karena itu pertahanan pemain yang mana yang dipakai. Monster ber-`attack_kind = magic` tapi `magic_attack = 0` otomatis kembali ke jalur fisik saat bertarung (jaring pengaman runtime) — tapi kombinasi itu kini **ditolak saat dibuat**, baik lewat `game:import` maupun form Panel Dewa, karena penulis konten tidak boleh ditipu monster sihir yang ternyata memukul. Diatur di Panel Dewa → Monster.
- Tiap giliran pemain memilih **serangan** (skill atau spell yang dikuasai). Damage ke monster = `max(1, damage_modul − ⌊pertahanan_jalur_itu/2⌋)`. Lalu **monster membalas** lewat jalurnya sendiri. Ulang sampai HP monster atau pemain habis.
- **Pukul** = skill `is_default` (power 1) yang dimiliki setiap karakter sejak dibuat. Skill/spell lain di-gate level/pengetahuan — cara perolehannya (scroll/beli/baca) menyusul.
- Karakter menyimpan daftar skill (`character_skill`) & spell (`character_spell`) yang dikuasai.
- Atur kekuatan lewat admin: `power` skill/spell vs `HP/pertahanan` monster. Monster contoh "Goblin Gua" kini HP 5 agar pas dengan Pukul=1.
- **HP/SP/MP persisten:** nilai terakhir selalu disimpan ke DB (saat kena serangan, menang, kalah). **Masuk dungeon/quest TIDAK menyembuhkan** — HP/SP/MP dibawa apa adanya (`StoryEngine::startQuest`). Pengecualian: karakter yang **tumbang** (hp ≤ 0) dibangkitkan penuh (respawn) agar bisa mencoba lagi. Naik level tetap memulihkan penuh.
- **SP & MP (resource serangan):** karakter punya `sp/max_sp` (stamina) & `mp/max_mp` (magic), default 30 (kolom + `Character::$attributes`). **Skill fisik memakai SP, sihir memakai MP.** Biaya = field eksplisit (`skills.stamina_cost` / `spells.mana_cost`) bila > 0, **jika 0 → otomatis = power** (jadi makin besar power makin besar biaya). Saat resource **kurang dari biaya**, serangan **DITOLAK** (422 "SP/MP tidak cukup") — giliran tidak terbuang, monster tidak membalas; tombolnya juga dinonaktifkan di klien. Logika di `CombatService::act` (+ `skillCost`/`spellCost`).
- **UI combat dipisah dua panel:** "Serangan Fisik — memakai SP" (amber) dan "Sihir — memakai MP" (indigo) di `CombatView`; log menyebut jalurnya ("Kamu memakai Pukul (fisik, −1, −1 SP)"). Galat aksi seperti resource kurang muncul sebagai banner dan **tidak** membatalkan pertarungan. Halaman Karakter punya kartu **Jalur Fisik** & **Jalur Sihir** (`CharacterStats`), HUD menampilkan 4 stat tempur. Admin set `stamina_cost` di form Skill.
- **Atribut RPG (STR/AGI/DEX/INT/VIT/LUK):** kolom `strength/agility/dexterity/intelligence/vitality/luck`. **Berbasis 1: nilai 1 = baseline (efek 0)**; hanya poin **di atas 1** yang berpengaruh (`CombatService::bonusStat` = `max(0, stat−1)`). Default karakter baru = 1, naik +1 tiap level. Pengaruh (konstanta tunable): **STR** +2% dmg fisik/poin · **INT** +2% dmg sihir/poin · **AGI** peluang menghindar (+1%/poin) · **DEX** (+1%) & **LUK** (+0.5%) peluang kritikal (×1.5) · **VIT** menambah pertahanan · **LUK** +1% emas/poin (semua dihitung dari poin di atas 1). RNG via `rollChance` (0 = tak pernah, ≥1 = selalu → uji deterministik). Ditampilkan di halaman Karakter (komponen `CharacterStats.vue`, lengkap dengan efek turunan); combat memberi feedback **KRITIKAL!** / **Menghindar!**.
- **Potion (consumable heal):** item `type=consumable` dengan `stats.heal` bisa dipakai untuk memulihkan HP (cap di `max_hp`, stok −1).
  - **Di luar pertarungan:** dari halaman Karakter (tombol **Gunakan** di inventaris) → `POST character.use-item`. Ditolak bila HP penuh; potion bisa membangkitkan karakter yang tumbang.
  - **Saat pertarungan:** tombol **Gunakan item** di `CombatView` → `POST combat.use-item`. Meminum potion **memakai giliran** (monster tetap menyerang balik), tidak bisa menang dengan minum.
  - Logika di `CombatService::useItem` & `StoryEngine::useHealingItem`/`healAmount`; semua server-authoritative.

> Catatan versi: starter kit terbaru sudah Laravel 13 / PHP 8.3+. Karena environment ini PHP 8.2,
> proyek dipasang dari tag **v1.0.2** (Laravel 12). PHP 8.4 tersedia di `F:\php84` bila kelak ingin upgrade.

## Kota — layer pemain (Tempat berfungsi)
Pemain punya **kota asal** (`characters.city_id`, home city) tempat ia berinteraksi dengan **Tempat**. Diberikan saat buat karakter (`Character::startingCity()` = kota ber-id terkecil); karakter lama di-assign **lazy** saat pertama membuka Kota. Travel antar kota belum ada (menyusul).
- **Menu "Kota"** (`/town`, `TownController@show`) → halaman `Town/Show.vue`: header kota (lore) + grid Tempat. Tiap Tempat → `Town/Place.vue` (`/town/{place:slug}`) yang menampilkan panel sesuai kategori. **Guard server:** tiap aksi memverifikasi Tempat berada di kota karakter (kalau tidak → 403).
- **Penginapan (`inn`)** — tombol **Istirahat**: pulihkan HP/SP/MP **penuh** dengan biaya emas (`TownService::restCost` = `10 × level`, tunable). Ditolak bila sudah penuh atau emas kurang. Sumber pemulihan SP/MP pertama selain potion.
- **Toko** (`potion_shop`, `blacksmith`, `market` — lihat `TownService::SHOP_STOCK`) — **Beli/Jual** pakai emas. Harga beli = `items.value`, harga jual = `max(1, ⌊value/2⌋)`. Stok = semua item dengan `type` cocok kategori & `value > 0` (potion_shop→consumable, blacksmith→weapon/armor). Jual ditolak untuk item terpasang (equipped). `Blacksmith` sudah berfungsi sebagai toko walau efek equip belum ada (stok kosong sampai ada item senjata/zirah ber-value).
- **City Hall & Guild** (`city_hall`, `adventurer_guild`, `merchant_guild`) — halaman **info** (lore + status afiliasi/rank). Fungsi misi & naik rank menyusul di slice Rank.
- **Potion SP & MP:** consumable kini mendukung `stats.restore_sp` / `restore_mp` (selain `stats.heal` untuk HP). Konten: `ramuan-stamina` (potion-sp) & `ramuan-mana` (potion-mp). `StoryEngine::useConsumable` memulihkan HP/SP/MP sekaligus (di luar combat); `CombatService::useItem` juga memulihkan ketiganya saat bertarung (tetap memakai giliran). Item bisa dipakai selama ada pool relevan yang belum penuh.
- Semua logika server-authoritative di `App\Services\TownService` (rest/buy/sell); klien hanya kirim `{place, item_id, qty}`.

## Rank & Misi (sistem guild)
**Misi = Quest** dengan field tambahan `affiliation` (`adventurer`/`merchant`/null) + `required_rank` (F..S). Diambil di **papan misi guild** (Tempat `adventurer_guild`/`merchant_guild`), bukan dari daftar quest bebas.
- **Satu misi aktif per waktu** (`characters.active_quest_id`). Ambil di guild → jadi misi aktif & langsung dimainkan. **Selesai (mencapai ending sukses)** → tercatat di `character_quest`, **hilang dari papan**, misi aktif dikosongkan → kembali ke guild untuk ambil lagi. Kalah (ending `result=defeat`) tidak menyelesaikan; bisa **Coba Lagi**.
- **Naik rank (F→E→D→C→B→A→S):** tiap misi selesai menambah `rank_progress` +1; saat mencapai ambang (`rank_rules.missions_required` untuk rank saat ini) → naik rank, progres reset. Ambang **diatur Panel Dewa** di **`/admin/ranks`** (default F:3, E:5, D:8, C:12, B:18, A:25; S = tertinggi). Misi di-gate `required_rank`, jadi pemain mengerjakan misi sesuai tingkat.
- **Alur teknis:** `RankService` (server-authoritative: `accept`/`complete`/`availableMissions`/progres). Penyelesaian dipicu di `StoryEngine::onEnterNode` saat masuk node `ending` non-`defeat` (resolve `RankService` lazily → tak ada circular dep). `TownController` menyajikan papan + `acceptMission`; `QuestController` (Misi `/quests`) kini = **jurnal misi**: misi aktif (Lanjutkan/Coba Lagi), progres rank, riwayat selesai. `quests.play` **di-gate**: hanya misi aktif yang bisa dimainkan (atau menampilkan layar akhir misi yang baru selesai).
- **Admin:** form Misi punya **Guild Penyelenggara** + **Rank Minimal**; tab **Rank** mengatur ambang. Role walikota/guildmaster (selain superadmin) menyusul.
- **Konten misi contoh:** `goblin-cave` + `tikus-gudang` (combat) + `patroli-tembok` (narasi) untuk Petualang; `antar-surat` untuk Merchant.

## Perlengkapan (Equip)
Senjata/zirah/aksesori yang **dipakai** menambah stat karakter. **3 slot** (`EquipmentService::SLOTS`, kunci = tipe item): `weapon`/`armor`/`accessory` — satu item per slot.
- **Stat dasar tidak diubah.** Bonus dihitung saat baca: `EquipmentService::bonuses()` (delta) & `effective()` (dasar + bonus). Status terpasang di pivot `character_items.equipped`. Melepas selalu mengembalikan stat persis.
- **Bonus yang didukung** (kunci `stats` item): `attack`, `defense`, **`magic_attack`, `magic_defense`**, dan 6 atribut `strength/agility/dexterity/intelligence/vitality/luck`. Plus `req_level` (level minimum untuk memakai). Contoh senjata: `{"attack": 4, "strength": 1, "req_level": 2}` · contoh tongkat: `{"magic_attack": 1, "req_level": 1}`.
- **Combat memakai stat efektif:** `CombatService` membaca AGI/DEX/INT/VIT/LUK/STR + defense **efektif**; serangan fisik mendapat tambahan **attack** dari equipment (porsi bonus saja, agar balance power-based tetap). Sihir ikut INT efektif (mis. dari aksesori).
- **Aksi pemain** (server-authoritative, `CharacterController@equip`/`unequip`, rute `character.equip`/`character.unequip`): Pakai/Lepas dari halaman **Karakter** (panel **Perlengkapan** + tombol di inventaris). Memakai item di slot terisi otomatis melepas yang lama. Item terpasang **tak bisa dijual** (lepas dulu).
- **Beli:** senjata/zirah di **Blacksmith**, aksesori di **Pasar** (`TownService::SHOP_STOCK`). Konten contoh: `pedang-besi`/`kapak-perang` (weapon), `zirah-kulit`/`zirah-rantai` (armor), `cincin-kekuatan`/`jimat-keberuntungan` (accessory).
- **Tier latihan (pemula):** `pedang-latihan`, `busur-latihan`, `tongkat-latihan`, `belati-latihan`, `tombak-latihan` — semuanya `attack: 1`, `req_level: 1`, 12–18 emas. Sengaja **tidak** memberi bonus atribut supaya tidak menyaingi `pedang-besi` (atk 2, 50 emas); pembedanya cuma flavor. Satu di antaranya jadi hadiah pembuka (lihat **Karakter & onboarding**).
- **Admin:** tipe item kini termasuk **Aksesori**; bonus diisi via field **Statistik/Efek (JSON)** di form Item.

## Perolehan Skill/Sihir (Buku & Toko Sihir)
Skill (SP) & sihir (MP) baru **dipelajari dari buku** yang dibeli di **Toko Sihir** (`magic_shop`).
- **Buku** = item `type=book`. Field `stats` menunjuk ability yang diajarkan: `{"teaches_skill": "<slug>"}` atau `{"teaches_spell": "<slug>"}`. Contoh konten: `buku-tebas` (skill), `buku-bola-api` & `buku-kilat` (sihir).
- **Membaca** (`LearningService::learn`, rute `character.learn`, tombol **Pelajari** di inventaris halaman Karakter): validasi kepemilikan, **belum dikuasai**, dan **level minimal** (dari `skills.level_req` / `spells.min_level`). Skill/sihir ditambah ke karakter, **buku habis** (−1). Setelah itu otomatis muncul sebagai serangan di combat.
- **Toko Sihir** = kategori Tempat baru `magic_shop` (label "Toko Sihir"). Jual buku (`TownService::SHOP_STOCK['magic_shop'] = ['book']`; Pasar juga jual buku). **Migrasi data** `2026_06_27_100001` memberi tiap kota yang sudah ada satu Toko Sihir ("Menara Mantra <kota>"); kota baru tambah lewat panel Dunia.
- **Halaman Karakter** kini menampilkan panel **Kemampuan** (skill & sihir yang dikuasai) + status buku ("dikuasai" / butuh level) di inventaris.
- **Sihir kini konten:** ditambahkan importer + `database/content/spells/*.json` (`bola-api`, `kilat`) — sebelumnya sihir hanya bisa dibuat lewat admin. `game:import` memuatnya (upsert; tak ikut terhapus saat `--fresh`).

## Chat & Pertemanan (real-time)
Panel **ChatDock** mengambang di kanan tiap halaman game (`resources/js/components/game/ChatDock.vue`, dipasang di `AppSidebarLayout`). Real-time via **Laravel Reverb (websocket)** + **Echo** (`resources/js/echo.ts`).
- **Chat Dunia** (tab Dunia): pesan global untuk semua pemain. `GET/POST chat/world`. Disiarkan event `WorldMessageSent` di channel publik `chat.world`.
- **Pertemanan** (tab Teman): **cari pemain** (by nama karakter/akun, `friends/search`), **kirim permintaan → terima** (`friends.request`/`accept`, hapus/tolak `DELETE friends/{f}`). Baru bisa DM setelah saling berteman. Status: none/outgoing/incoming/friends.
- **DM pribadi**: klik teman → percakapan. `GET/POST chat/dm/{friendship}`. Disiarkan `PrivateMessageSent` di channel **privat** `chat.dm.{friendshipId}` (otorisasi: hanya kedua pihak yang `accepted`, lihat `routes/channels.php`). Perubahan teman disiarkan `FriendshipChanged` ke `App.Models.User.{id}` agar panel menyegarkan diri.
- **Fana — 30 menit:** semua pesan (dunia + DM) lebih dari 30 menit otomatis dihapus. Dipangkas **saat dibaca** (`ChatService::prune`, dipanggil tiap fetch) **dan** lewat penjadwalan (`chat:prune` tiap 5 menit di `routes/console.php`). Pertemanan tetap tersimpan — hanya pesan yang fana.
- **Identitas chat** = nama karakter (`User::displayName()`); butuh punya karakter (`can_chat`). Server-authoritative; logika di `App\Services\{ChatService,FriendService}`, event di `app/Events/`, tabel `friendships` & `chat_messages`.
- **Reverb mati ≠ aksi gagal:** semua siaran real-time (chat dunia, DM, perubahan teman, notifikasi forum) dikirim lewat `App\Services\Concerns\BroadcastsQuietly` — kegagalan siaran ditangkap & dicatat `report()`, jadi pesan/permintaan teman tetap tersimpan dan responsnya tetap 200; yang hilang cuma update langsungnya. Dulu Reverb mati membuat responsnya 500 padahal datanya sudah masuk.
- **Catatan dev:** real-time butuh server Reverb berjalan (`php artisan reverb:start`, kini termasuk di `composer run dev`). Kunci `REVERB_*`/`VITE_REVERB_*` & `BROADCAST_CONNECTION=reverb` ada di `.env`. Reverb butuh MySQL hidup (cache). Reverb di Windows: berjalan via event-loop ReactPHP (tanpa ext-pcntl/sockets).

## Balai Warta (forum diskusi)
Forum **permanen** — kebalikan dari chat yang fana 30 menit. Menu **Balai Warta** di sidebar (`/forum`), bukan lewat Tempat di kota (gesekan terlalu tinggi kalau harus jalan ke sana tiap kali). Istilahnya netral: **Kategori → Topik → Balasan**.
- **Struktur:** `forum_categories` (dikelola Dewa) → `forum_topics` → `forum_posts`. **Pesan pertama topik = ForumPost ber-`is_first`**, jadi ubah/kutip/apresiasi memakai satu jalur kode. Hapus balasan = **soft delete** (jejak moderasi tetap ada). Kolom `scope` sudah ada di kategori (default `global`) untuk ekspansi per-negara/kota/guild — slice ini semuanya global.
- **Kategori bawaan** (dibuat di migrasi, bukan JSON konten): Warta Kerajaan (terkunci — hanya Dewa yang buka topik), Kedai Minum, Papan Strategi, Balai Rekrutmen, Ruang Keluhan. Tiap kategori bisa diberi `min_rank`.
- **Apresiasi & Reputasi:** satu apresiasi (+1) per pemain per pesan, bisa dibatalkan (`forum_votes`, unique post+user; kolom `value` disiapkan bila kelak ingin nilai negatif). Akumulasinya = `characters.reputation` — **murni sosial: tidak memberi XP, emas, atau stat** (mencegah farming lewat spam). Tak bisa mengapresiasi pesan sendiri.
- **Identitas penulis** memakai yang sudah ada: nama karakter + **Rank** + Level + gelar (`users.job`) + reputasi. Tidak ada tangga pangkat forum terpisah.
- **Aturan tulis:** wajib punya karakter; judul 4–120 & isi 2–5000 karakter; **teks biasa** (di-escape, baris baru dipertahankan — belum ada markdown/BBCode); jendela ubah **15 menit** (`ForumService::EDIT_WINDOW_MINUTES`, Dewa bebas kapan saja); throttle rute (10 topik & 20 balasan per menit). Kutipan **satu tingkat** (`reply_to_id`), bukan pohon bersarang.
- **Moderasi (Dewa):** sematkan, kunci, hapus topik — dijaga `ForumService::ensureModerator` (bukan middleware, supaya pesan galatnya ramah lewat flash). Hapus topik/pesan **menarik kembali reputasi** dari apresiasi yang ikut hilang. Role walikota/guildmaster sebagai moderator menyusul.
- **Lencana "balasan baru"** di sidebar: `users.forum_seen_at` + satu query hitung balasan orang lain di topik yang pemain ikut menulis (`ForumService::unreadCount`, dishare lewat `HandleInertiaRequests`). Bertambah langsung lewat event **`ForumReplyPosted`** ke channel pribadi `App.Models.User.{id}` — disiarkan langsung dan kegagalannya ditelan `BroadcastsQuietly`, supaya Reverb yang mati tidak menggagalkan penulisan balasan. Isi topik tidak disiarkan: tanpa polling, tanpa live-update.
- **Panel Dewa:** tab **Forum** (`/admin/forum-categories`) untuk CRUD kategori (nama/slug/deskripsi/urutan/terkunci/rank minimal). Hapus kategori = cascade seluruh topik & pesannya.
- Belum masuk: bursa jual-beli antar pemain (butuh sistem trade/escrow), sundul berbayar, pencarian, lampiran, scope per-lokasi.

## Menjalankan (dev)
```powershell
# dari F:\xampp82\htdocs\webio
composer run dev      # serve :8000 + Vite HMR + queue + logs + reverb (cara utama)
# atau, dengan aset hasil build saja:
php artisan serve     # http://localhost:8000  (jalankan `npm run build` lebih dulu)
```
Siapkan database & akun demo:
```powershell
php artisan migrate --seed
```

### Akun demo (dari seeder — GENERIK, ganti sandinya di lingkungan nyata)
| Peran | Email | Sandi |
|---|---|---|
| Dewa (superadmin) | `admin@webio.test` | `admin123` |
| Pemain 1 | `player1@webio.test` | `player123` |
| Pemain 2 | `player2@webio.test` | `player123` |

> Dua akun pemain disediakan agar fitur **chat & pertemanan** bisa diuji berpasangan.
> **Jangan** memasukkan email/sandi asli ke dalam seeder atau dokumen ini.

## Menulis konten (developer)
Konten = file JSON di `database/content/` (bukan CMS). Edit lalu sinkronkan:
```powershell
php artisan game:import            # idempoten (upsert by slug / quest+key)
php artisan game:import --fresh     # bersihkan konten + progres, lalu impor ulang
```
- `database/content/quests/*.json` — quest: `nodes[]` (narrative/choice/combat/reward/ending),
  tiap node punya `choices[]` (`requirements`/`effects`), plus `monsters[]`.
- `database/content/items/*.json` — katalog item.
- **Payload adegan di-allow-list** (berlaku untuk bentuk ringkas maupun long-form, dijaga `ImportGameContent::assertPayloadKeys`): `reward` → `xp`/`gold`/`item_slugs`, `ending` → `result`, `combat` → `on_win_node_key`/`on_lose_node_key`. Kunci lain (mis. `exp` alih-alih `xp`) **menggagalkan import** — dulu terimpor bersih lalu pemain tidak dapat apa pun. `xp`/`gold` harus angka ≥ 0 dan `item_slugs` harus daftar slug. Tipe node lain payload-nya bebas.
- Contoh lengkap: `database/content/quests/goblin-cave.json`.

### Bentuk misi ringkas (`hunt` / `errand`)
Misi berpola tidak perlu ditulis node-per-node. Dua arketipe dikembangkan `App\Services\QuestTemplate` saat `game:import`:
- **`hunt`** — `intro` → `fight` → `win` (reward) → `ending_win`, plus ending `lose`. Wajib: `monster` (dengan `slug`), `intro`, `fight`, `win`. Opsional: `lose`, `outro`, `reward`.
- **`errand`** — `beats[]` dirantai berurutan → `win` (reward) → `ending_win`. Tanpa ending kalah. Wajib: `beats` (minimal satu), `win`.
- Tanpa `reward`, node reward tidak dibuat dan prosa `win` pindah ke ending. Deteksi berbasis **keberadaan key**, bukan isinya: `"reward": {}` tetap membuat node reward; key `reward` hilang atau bernilai `null` yang tidak.
- Tiap field prosa menerima string **atau** `{"title": "...", "body": "...", "label": "..."}` untuk menimpa judul default (judul `intro`/beat = judul misi, `fight` = nama monster + "!", `win` = "Berhasil", `ending_win` = "Misi Tuntas", `lose` = "Kalah") dan/atau label tombol pilihan node itu (default "Hadapi" di `intro` milik `hunt`, "Lanjutkan" di tempat lain). `label` hanya boleh di adegan yang PUNYA tombol — `intro`, tiap beat, dan `win` bila ada `reward`; di `fight`/`lose`/`outro` (dan `win` tanpa reward) ia tak akan pernah tampil, jadi **import ditolak**. Judul/label kosong dianggap tidak ditulis (jatuh ke default), bukan dirender kosong.
- **Monster cukup `{"slug", "name", "level"}`** — stat diturunkan `Monster::statsForLevel()` (lv1 = hp 3 / atk 1 / def 0 / xp 30 / emas 10, naik linear). Field stat yang ditulis eksplisit selalu menimpa rumus; field tak dikenal (mis. `magik_attack`) membuat import gagal.
- Misi **bercabang** tetap ditulis long-form (`nodes`) — lihat `goblin-cave.json`, `patroli-tembok.json`, dan `antar-surat.json`. Bentuk ringkas dan `nodes` tidak boleh dicampur dalam satu file.
- Contoh ringkas: `tikus-gudang.json` (hunt) & `kabar-desa.json` (errand).

## Panel Admin — superadmin
- Akses di **`/admin`** (hanya user dengan `role = superadmin`; lainnya kena 403). Tautan muncul di Beranda & sidebar.
- CRUD penuh (UI Bahasa Indonesia) untuk: **Items**, **Skill**, **Sihir**, **Monster**, **Misi → Adegan (Node) → Pilihan** (editor cerita bertingkat; Misi punya **Guild Penyelenggara** + **Rank Minimal**), **Rank** (ambang naik rank), **Forum** (kategori Balai Warta), **Dunia** (lokasi, lihat di bawah), dan **Pemain**. Field JSON diisi via textarea. Skill/Sihir punya `power` (damage) & flag *default*.

### Pemain — kelola akun & karakter (`/admin/players`)
- Daftar semua user (badge peran Pemain/Dewa) + ringkasan karakter. **Edit** akun (nama, email, peran, gelar, ganti sandi opsional) **dan** seluruh stat karakter (level/xp/emas, HP/SP/MP, serangan/pertahanan, STR/AGI/DEX/INT/VIT/LUK, rank) — panel Dewa bisa menyetel apa pun. **Hapus** pemain → **cascade** menghapus karakter + semua progres (saves, sesi pertarungan, inventaris, skill/spell) lewat FK `cascadeOnDelete`.
- Pengaman: Dewa **tidak bisa** menghapus akun sendiri atau menurunkan perannya sendiri (`PlayerController`). Param route resource = `{player}` (binding by id).

### Dunia — database lokasi (`/admin/world`)
- Hierarki bertingkat, tiap level tabel sendiri: **Negara → Provinsi → Kota → Desa** (route `Route::resource(...)->shallow()`, nama `admin.world.*`). Semua tabel & route ada.
- **Permulaan (disengaja sederhana):** membuat **Negara** otomatis membuatkan **satu Kota Ibukota** (lewat provinsi implisit yang dibuat di `CountryController@store`), lalu langsung diarahkan ke halaman ibukota. Halaman Negara fokus pada kartu **Ibukota** saja; provinsi & banyak kota belum ditonjolkan di UI (struktur disiapkan untuk ekspansi). Ibukota = `Country::capitalCity()` (kota pertama negara).
- **Atribut lore negara:** `government_type` (Jenis), `ideology` (Nasionalitas), `ruler_title` + `ruler_name` (Penguasa), `dominant_race` (Ras) — diedit di form Negara, tampil di daftar & halaman Negara. Dunia sudah diisi 6 negara contoh (Astoria, Xianzhou, Zirnitra, Infernia, Alfheim, Nidavellir), tiap negara 1 ibukota + 7 tempat tematik (semua kategori `Place::CATEGORIES`).
- **Kota** = pusat aktivitas; punya dua koleksi anak: **Desa** dan **Tempat**.
- **Tempat** (`places`) = fasilitas di dalam kota dengan `category` tetap (lihat `App\Models\Place::CATEGORIES`: Guild Petualang, Guild Merchant, City Hall, Pasar, Blacksmith, Potion Shop, Toko Sihir, Penginapan) dan `name` **custom** (mis. Penginapan bernama "Rabbit Moon Inn"). Tambah kategori baru = satu baris di `Place::CATEGORIES`. **Sisi pemain:** Tempat ini sudah **berfungsi** (istirahat/toko/info) — lihat **Kota — layer pemain**.
- Semua level pakai `slug` unik (admin bind by id, slug untuk referensi game nanti). Hapus negara/provinsi/kota **cascade** ke seluruh isinya.
- Superadmin bawaan seeder: **`admin@webio.test`** (sandi `admin123`, gelar "Dewa Pencipta"). Akun asli **tidak** dicantumkan di repo — buat sendiri lewat perintah di bawah.
- Buat / promosikan superadmin lain:
  ```powershell
  php artisan game:superadmin email@contoh.test --password=RAHASIA_ANDA --name="Nama" --job="Gelar"  # akun baru
  php artisan game:superadmin user-lama@contoh.test                                                   # promosikan akun ada
  ```
  `role` (player|superadmin) + `job` (meta, mis. "Dewa Pencipta") ada di tabel `users`.
- **Edit profil dewa:** Dewa bisa mengubah **gelar** (`job`, mis. "Dewa Hujan") sendiri lewat **Pengaturan → Profil** (field "Gelar (Nama Dewa)", superadmin-only via `ProfileUpdateRequest`; pemain tak bisa — job mereka dikendalikan game) dan **ganti sandi** di **Pengaturan → Kata sandi**. Tautan cepat "Edit Profil" ada di Panel Dewa + menu user.
- Catatan UX: setelah membuat Misi, kembali ke daftar lalu klik **Ubah** untuk menambahkan Adegan (alur cerita) & memilih Adegan Awal.

> Catatan: item dari panel admin tersimpan langsung di DB. `php artisan game:import --fresh` akan mengosongkan & memuat ulang dari JSON — pakai slug yang berbeda dari konten JSON bila ingin keduanya hidup berdampingan.

## Arsitektur kode
| Bagian | Lokasi |
|---|---|
| Mesin cerita (node, requirement, effect, save) | `app/Services/StoryEngine.php` |
| Combat (damage, menang/kalah, reward) | `app/Services/CombatService.php` |
| Jalur serangan fisik & sihir (rumus) | `app/Services/Combat/{AttackModule,PhysicalAttack,MagicalAttack}.php` |
| Kota: istirahat/jual-beli (Tempat) | `app/Services/TownService.php` · `app/Http/Controllers/TownController.php` · `resources/js/pages/Town/` |
| Rank & Misi guild (ambil/selesai/naik) | `app/Services/RankService.php` · `TownController@acceptMission` · `app/Http/Controllers/Admin/RankController.php` |
| Perlengkapan (equip, stat efektif) | `app/Services/EquipmentService.php` · `CharacterController@equip/unequip` · `resources/js/components/game/EquipmentPanel.vue` |
| Pelajari skill/sihir dari buku | `app/Services/LearningService.php` · `CharacterController@learn` (Toko Sihir = `magic_shop`) |
| Chat & teman (real-time) | `app/Services/{ChatService,FriendService}.php` · `app/Http/Controllers/{Chat,Friend}Controller.php` · `app/Events/` · `resources/js/components/game/ChatDock.vue` · `resources/js/echo.ts` · `routes/channels.php` |
| Balai Warta (forum) | `app/Services/ForumService.php` · `app/Http/Controllers/ForumController.php` · `app/Http/Controllers/Admin/ForumCategoryController.php` · `app/Events/ForumReplyPosted.php` · `resources/js/pages/Forum/` · `resources/js/pages/admin/forum/` |
| Leveling | `app/Services/LevelService.php` |
| Import konten | `app/Console/Commands/ImportGameContent.php` |
| Halaman (Inertia) | `app/Http/Controllers/` · rute `routes/web.php` |
| Combat JSON | `app/Http/Controllers/Api/CombatController.php` |
| Lokasi/Dunia (admin) | `app/Http/Controllers/Admin/{Country,Province,City,Village,Place}Controller.php` · `resources/js/pages/admin/world/` |
| UI | `resources/js/pages/{Dashboard,Character,Quests}` · `resources/js/components/game/` |
| Model & migrasi | `app/Models/` · `database/migrations/2026_06_24_*` , `2026_06_25_*` |

## Test
```powershell
php artisan test     # 244 test (auth bawaan + CombatService/StoryEngine/GameFlow/Admin/World/Inventory/Profil/Pemain/Town/RankMission/Equipment/Learning/FriendChat/Forum/QuestTemplate)
```
Test memakai SQLite in-memory — **tidak** menyentuh database `webio`.

## Peta jalan (lanjutan)
- **Phase 2**: banyak quest, kurva level, multi save-slot.
  Sudah jalan: consumable heal (HP/SP/MP), shop/gold + istirahat penginapan (**Kota — layer pemain**), respawn, **equip** senjata/zirah/aksesori (**Perlengkapan**).
- **Tempat — lanjutan**: travel antar kota (home city → pindah), stok toko per-tempat (bukan katalog global).
  (Papan misi guild & kenaikan **rank** + Blacksmith berfungsi sudah jalan — lihat **Rank & Misi** dan **Perlengkapan**.)
- **Equip — lanjutan**: slot lebih banyak (helm/perisai/sepatu), syarat class/rank untuk item, set-bonus, durability.
- **Skill/sihir — lanjutan**: sumber lain (scroll drop monster, latih di guild), gate pengetahuan/class, pohon skill. (Belajar dari **buku** di Toko Sihir sudah jalan — lihat **Perolehan Skill/Sihir**.)
- **Rank & Misi — lanjutan**: role **walikota/guildmaster** (selain superadmin) untuk menata misi & ambang; reward rank (gelar/akses); misi berulang/harian; multi misi aktif (jurnal).
- **Balai Warta — lanjutan**: bursa jual-beli antar pemain (butuh trade/escrow), pencarian topik, sundul berbayar (sink emas), BBCode-lite, moderator selain Dewa (walikota/guildmaster), lapor pesan, scope kategori per-negara/kota/guild.
- **Phase 3**: CMS admin (mis. Filament) untuk penulis non-developer, animasi & audio, achievements, deploy Apache.
