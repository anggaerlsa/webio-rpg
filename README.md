# Webio — Game RPG Fantasi Interaktif

Game cerita/RPG berbasis web: quest & dungeon dengan **combat berbasis serangan**, kota yang hidup
(penginapan, toko, guild), sistem **rank & misi**, **perlengkapan**, perolehan skill dari buku, serta
**chat dunia & pertemanan real-time**. Login dan seluruh progres tersimpan di server.

> Dokumentasi lengkap (arsitektur, mekanik, panel admin, cara menulis konten) ada di **[GAME.md](GAME.md)**.

## Stack
- **Laravel 12** + **Inertia 2** + **Vue 3** + **Tailwind 3** + shadcn-vue
- **Laravel Reverb** (websocket) + **Echo** untuk chat real-time
- **PHP 8.2**, **MySQL/MariaDB**
- Logika game **server-authoritative** — semua HP/damage/RNG/reward dihitung di server (anti-cheat)

## Fitur
- **Karakter**: 1 akun = 1 karakter (gender, tanggal lahir → usia, class Warrior/Mage), afiliasi guild
- **Combat**: turn-based berbasis serangan — skill (SP) & sihir (MP), 6 atribut RPG (STR/AGI/DEX/INT/VIT/LUK),
  kritikal & menghindar, HP/SP/MP persisten
- **Kota**: kota asal, istirahat di penginapan, jual-beli (Blacksmith, Potion Shop, Toko Sihir, Pasar)
- **Rank & Misi**: ambil misi di guild (satu misi aktif), selesai → naik rank F→E→D→C→B→A→S
- **Perlengkapan**: 3 slot (senjata/zirah/aksesori) yang menambah attack/defense + atribut
- **Skill/Sihir**: dipelajari dari buku yang dibeli di Toko Sihir
- **Sosial**: chat dunia, cari pemain, pertemanan, dan DM pribadi — real-time; pesan otomatis terhapus
  setelah 30 menit
- **Panel Dewa** (`/admin`, superadmin): CRUD Items, Skill, Sihir, Monster, Misi, Rank, Dunia, Pemain

## Menjalankan (dev)
```bash
composer install
npm install
cp .env.example .env          # lalu isi DB_* dan REVERB_*
php artisan key:generate
php artisan migrate --seed    # migrasi + konten game + akun demo
composer run dev              # serve :8000 + Vite + queue + logs + reverb
```
Buka http://localhost:8000

### Akun demo (dari seeder)
| Peran | Email | Sandi |
|---|---|---|
| Dewa (superadmin) | `admin@webio.test` | `admin123` |
| Pemain 1 | `player1@webio.test` | `player123` |
| Pemain 2 | `player2@webio.test` | `player123` |

> Akun di atas hanya untuk pengembangan. **Ganti sandinya** (atau hapus akunnya) sebelum dipakai serius.

## Konten
Konten game = file JSON di `database/content/` (quests, items, skills, spells, monsters).
Sinkronkan dengan:
```bash
php artisan game:import          # idempoten (upsert by slug)
php artisan game:import --fresh  # bersihkan konten + progres, lalu impor ulang
```

## Test
```bash
php artisan test    # 134 test (SQLite in-memory — tidak menyentuh database dev)
```

## Lisensi
Lihat [LICENSE](LICENSE).
