// Format waktu relatif Bahasa Indonesia untuk Balai Warta (forum permanen, jadi
// perlu skala sampai bulan/tahun — beda dari chat yang cuma hidup 30 menit).
export function waktuLalu(iso: string | null | undefined): string {
    if (!iso) return '—';

    const detik = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);
    if (detik < 60) return 'baru saja';

    const menit = Math.floor(detik / 60);
    if (menit < 60) return `${menit} menit lalu`;

    const jam = Math.floor(menit / 60);
    if (jam < 24) return `${jam} jam lalu`;

    const hari = Math.floor(jam / 24);
    if (hari < 30) return `${hari} hari lalu`;

    const bulan = Math.floor(hari / 30);
    if (bulan < 12) return `${bulan} bulan lalu`;

    return `${Math.floor(bulan / 12)} tahun lalu`;
}
