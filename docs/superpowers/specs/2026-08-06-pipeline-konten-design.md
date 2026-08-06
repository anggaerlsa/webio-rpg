# M1 — Pipeline Konten: bentuk misi ringkas

Tanggal: 6 Agustus 2026 · Status: disetujui, siap direncanakan

## Masalah

Mesin Webio jauh lebih maju daripada isinya: combat dua jalur, equipment, rank & misi,
kota & ekonomi, forum, chat real-time — tapi hanya 4 quest, 3 monster, 2 skill, 2 sihir.
Pemain menghabiskan seluruh isi game dalam belasan menit.

Penyebabnya bukan kemalasan menulis, tapi ongkos menulis. Misi paling sederhana
(`tikus-gudang`) butuh 66 baris JSON, dan pecahannya:

- **~14 baris** isi asli — 5 judul, 5 narasi, 2 angka reward
- **~52 baris** perancah — `key`, `type`, array `choices`, `next`, `is_auto`,
  `on_win_node_key`, `on_lose_node_key`, `result`, plus `image: null` / `defense: 0` /
  `loot: []` pada blok monster

Struktur misi "bunuh monster" selalu sama: intro → tarung → reward → ending, plus ending
kalah. Semua itu bisa disimpulkan.

## Kriteria sukses

Satu misi baru lengkap (narasi + monster + reward) bisa ditambahkan dalam **~10 menit**
tanpa menulis perancah. Bukan "banyak konten" — konten datang sendiri kalau menambahnya
murah.

## Keputusan yang sudah diambil

| Keputusan | Pilihan | Alasan |
|---|---|---|
| Jalur authoring | JSON ringkas + `game:import` | Ikut versi di git, bisa di-diff & di-review, tanpa UI baru. Angga penulis tunggal untuk sekarang. |
| Arketipe | `hunt` + `errand` saja | Menutupi 3 dari 4 quest yang ada. Misi bercabang tetap ditulis panjang — itu justru nilai jualnya. |
| Definisi monster | `level` + rumus, field eksplisit menimpa | Keputusan aslinya cuma "sekuat apa"; tuning manual tetap mungkin. |
| Loot & tabel loot bersama | **Ditunda** | Monster baru 3 — belum ada duplikasi untuk dihapus. Tabel bersama sekarang = abstraksi spekulatif. |
| Cara membangun | Expander di importer (opsi A) | Yang mahal itu menulis, bukan menyimpan; perbaikannya harus di titik tulis. Nol perubahan runtime. |

Alternatif yang ditolak: template sebagai entitas DB dengan node virtual (menambah lapisan
tak langsung ke `StoryEngine` yang sedang stabil) dan generator file (perancah cuma
dipindah, file tetap 66 baris).

## Bentuk ringkas

Nama field yang sudah ada tidak diganti (`affiliation`, `required_rank`, `min_level`) —
dua nama untuk satu hal butuh pemetaan dan bikin bingung.

### `hunt`

```json
{
    "slug": "tikus-gudang",
    "title": "Tikus Gudang",
    "description": "Gudang logistik guild dipenuhi tikus sebesar anjing...",
    "affiliation": "adventurer",
    "required_rank": "F",
    "min_level": 1,
    "order": 2,
    "hunt": {
        "monster": { "slug": "tikus-raksasa", "name": "Tikus Raksasa", "level": 1 },
        "intro": "Bau apek dan cericit menyambutmu. Di antara tumpukan peti, sepasang mata merah berkilat.",
        "fight": "Tikus itu melompat dengan gigi kuning terbuka.",
        "win": "Tikus itu kabur ke lubang dindingnya. Juru tulis menyelipkan beberapa koin.",
        "lose": "Memalukan — kau mundur dari seekor tikus. Pulihkan diri dan coba lagi.",
        "reward": { "xp": 15, "gold": 15 }
    }
}
```

19 baris, seluruhnya isi. Mengembang jadi:

| Kunci node | Tipe | Sumber | Menuju |
|---|---|---|---|
| `intro` | narrative | `hunt.intro` | `fight` |
| `fight` | combat | `hunt.fight` + `hunt.monster` | payload `on_win_node_key: win`, `on_lose_node_key: lose` |
| `win` | reward | `hunt.win` + `hunt.reward` | `ending_win` (pilihan `is_auto`) |
| `ending_win` | ending | `hunt.outro` (opsional) | payload `result: victory` |
| `lose` | ending | `hunt.lose` | payload `result: defeat` |

Kunci node mengikuti konvensi yang sudah dipakai konten sekarang.

Prosa `hunt.win` dipakai **hanya** oleh node reward. Node `ending_win` memakai field opsional
`hunt.outro`; bila tidak ditulis, ia memakai judul & isi penutup default. Ini menghindari
satu prosa muncul dua kali di dua adegan berurutan.

Bila `reward` tidak ditulis, node `win` tidak dibuat sama sekali: `fight` menunjuk langsung
ke `ending_win`, dan prosa `hunt.win` pindah ke `ending_win` (menimpa `outro` bila keduanya
ditulis — `win` yang menang karena lebih spesifik). Aturan yang sama berlaku untuk `errand`:
tanpa `reward`, beat terakhir menunjuk langsung ke `ending_win`.

### `errand`

```json
"errand": {
    "beats": [
        "Juru tulis menyerahkan surat bersegel lilin merah.",
        "Jalan berdebu menuju desa sepi. Tak ada yang mengganggumu."
    ],
    "win": "Surat sampai di tangan yang benar. Bayaran diserahkan tanpa ribut.",
    "reward": { "xp": 10, "gold": 20 }
}
```

`beats` jadi node narrative `beat_1..beat_n` yang dirantai berurutan → `win` (reward) →
`ending_win`. Tidak ada ending kalah karena tidak ada tarung.

### Default & penimpaan

Judul node dan label pilihan tidak perlu ditulis:

| Bagian | Default |
|---|---|
| Judul `intro` | judul misi |
| Judul `fight` | nama monster + `!` |
| Judul `win` | "Berhasil" |
| Judul `ending_win` | "Misi Tuntas" |
| Isi `ending_win` bila `outro` kosong | teks penutup default |
| Judul `lose` | "Kalah" |
| Isi `lose` bila kosong | teks default kalah |
| Label pilihan antar-beat | "Lanjutkan" |
| Label pilihan `intro` → `fight` (hunt) | "Hadapi" |
| Pilihan `win` → `ending_win` | otomatis (`is_auto: true`) |

Setiap field prosa menerima **string atau objek** `{"title": "...", "body": "..."}`; bentuk
objek menimpa judul default.

Long-form tetap didukung penuh: bila ada key `nodes`, importer memakai jalur lama tanpa
perubahan. `goblin-cave` tidak disentuh.

## Arsitektur

Dua unit baru, tidak lebih:

1. **`app/Services/QuestTemplate.php`** — `expand(array $quest): array`. Bila ada
   `hunt`/`errand`, kembalikan data yang sama dengan `nodes` + `monsters` terisi penuh;
   bila ada `nodes`, kembalikan apa adanya. Berdiri sendiri sehingga bisa dites tanpa
   menjalankan perintah import.
2. **`Monster::statsForLevel(int $level): array`** — rumus stat, statis di model. Bukan
   file baru; rumus ini milik domain monster dan kelak bisa dipakai Panel Dewa.

Alur: `game:import` → baca JSON → `QuestTemplate::expand()` → `importQuests()` yang sudah
ada, tanpa perubahan.

`StoryEngine`, `CombatService`, dan editor Panel Dewa: **nol perubahan**. Yang masuk DB
tetap node biasa, jadi hasil ekspansi tetap bisa disunting lewat panel.

Konsekuensi yang diterima sadar: menyunting node hasil ekspansi lewat Panel Dewa lalu
menjalankan `game:import` kembali akan menimpa suntingan itu. Ini sudah perilaku
`game:import` sekarang, hanya jadi lebih terasa.

## Rumus level monster

| Stat | Rumus | lv1 | lv3 | lv5 |
|---|---|---|---|---|
| `max_hp` | `3 + 2×(lv−1)` | 3 | 7 | 11 |
| `attack` | `lv` | 1 | 3 | 5 |
| `defense`, `magic_defense` | `⌊(lv−1)/2⌋` | 0 | 1 | 2 |
| `magic_attack` | `0` | 0 | 0 | 0 |
| `xp_reward` | `20 + 10×lv` | 30 | 50 | 70 |
| `gold_reward` | `5 + 5×lv` | 10 | 20 | 30 |

Level 1 sengaja dipas ke konten yang sudah terbukti: `tikus-raksasa` saat ini hp 3 / atk 1 /
def 0 / xp 30 / emas 10 — **sama persis dengan rumus lv1**, jadi cukup ditulis `"level": 1`.
`goblin` (hp 5, atk 3, xp 60, emas 20) memang elite untuk level 1 dan tetap menimpa field
yang dibutuhkannya. Field eksplisit selalu menang.

Monster penyihir tidak dapat rumus: `magic_attack` tetap 0 dan `attack_kind` tetap
`physical` kecuali ditulis eksplisit. Belum ada konten penyihir untuk mengkalibrasi rumusnya.

Semua konstanta di satu tempat, jadi menyeimbangkan ulang seluruh game = ubah lima angka
lalu `game:import`.

**Batas yang diketahui:** rumus HP mengasumsikan kekuatan serang pemain naik seiring level,
padahal damage sekarang berasal dari `power` skill — bukan level. Pemain level 5 yang hanya
menguasai Pukul (power 1) butuh 11 giliran melawan monster level 5. Itu membosankan, dan
bukan masalah M1; kurva skill adalah M2. Ditandai komentar `ponytail:` di rumusnya.

## Validasi

`game:import` berjalan dalam satu transaksi: satu file rusak = seluruh import batal. Itu
dipertahankan, tapi pesan galat harus menyebut slug misi dan field yang salah.

| Kondisi | Perlakuan |
|---|---|
| Ada `hunt` dan `errand` sekaligus | Gagal: pilih salah satu |
| Ada `hunt`/`errand` bersama `nodes` | Gagal: bentuk ringkas & long-form tak bisa dicampur |
| `hunt` tanpa `monster`, atau `monster` tanpa `slug` | Gagal, sebut slug misi |
| `errand.beats` kosong atau bukan array | Gagal |
| `level` bukan integer ≥ 1 | Gagal |
| Prosa wajib kosong — `hunt`: `intro`, `fight`, `win`; `errand`: `beats`, `win` | Gagal — misi tanpa narasi itu bug |
| Field monster tak dikenal (mis. `magik_attack`) | Gagal, sebut nama field |
| `lose` kosong pada `hunt` | Boleh, pakai teks default |
| `reward` kosong | Boleh, node reward dilewati |

Tidak dibangun: validasi skema penuh (JSON Schema), perbaikan otomatis, mode impor
sebagian. File salah = benerin filenya.

## Test

Semua di `tests/Feature/QuestTemplateTest.php`, tanpa fixture, tanpa mock:

1. `hunt` mengembang jadi 5 node dengan kunci & tipe benar; `fight.payload` menunjuk
   `win`/`lose`. Varian tanpa `reward`: 4 node, `fight` menunjuk langsung `ending_win`
2. `errand` dengan 2 beat → beat dirantai berurutan → `win` → `ending_win`
3. `Monster::statsForLevel(1)` menghasilkan tepat `max_hp` 3, `attack` 1, `defense` 0,
   `xp_reward` 30, `gold_reward` 10 — angka literal, mengunci rumus ke keseimbangan
   `tikus-raksasa` yang sudah terbukti (assertion tidak boleh membaca ulang file konten,
   karena file itu ikut diubah di migrasi konten)
4. Field monster eksplisit menimpa rumus (hp 5 goblin bertahan walau `level` diisi)
5. Judul & label default terpasang; bentuk objek `{"title","body"}` menimpanya
6. Long-form (`nodes`) lewat tanpa disentuh — jaminan `goblin-cave` tidak rusak
7. Setiap baris tabel validasi → satu assertion gagal dengan pesan yang benar
8. Integrasi: `game:import` atas file ringkas nyata → quest bisa dimainkan
   (`StoryEngine::startQuest` sampai ending)

Test 3 dan 8 yang paling penting: satu mengunci keseimbangan ke konten nyata, satu
membuktikan node hasil generate benar-benar jalan di mesin cerita.

## Migrasi konten

`tikus-gudang`, `patroli-tembok`, dan `antar-surat` diubah ke bentuk ringkas — sekaligus
bukti template cukup untuk konten nyata. `goblin-cave` tetap long-form. 165 test yang sudah
ada harus tetap lulus.

## Di luar lingkup M1

- Tabel loot bersama, drop scroll, loot berbasis level
- Arketipe `gather` / `escort` (mekanik pendukungnya belum ada)
- Wizard "Buat Misi Cepat" di Panel Dewa
- Kurva skill & alokasi poin atribut (M2)
- Travel antar kota & stok toko per-tempat (M3)

## Milestone berikutnya

| # | Milestone | Isi |
|---|---|---|
| M0 | Bersihkan utang | Event chat jadi queued (Reverb mati ≠ 500), rapikan klaim GAME.md |
| M2 | Progression jangka panjang | Poin atribut, stat per-class, pohon skill, misi harian, reward rank |
| M3 | Dunia terasa hidup | Travel antar kota, stok toko per-tempat, lore/NPC, role walikota & guildmaster |
