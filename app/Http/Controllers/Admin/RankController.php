<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RankRule;
use App\Services\RankService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengaturan ambang naik rank (berapa misi untuk naik dari tiap rank).
 * Hanya superadmin (Panel Dewa). Role walikota/guildmaster menyusul.
 */
class RankController extends Controller
{
    public function index(): Response
    {
        // Urut sesuai tangga rank (F→A); S tak punya baris.
        $rules = RankRule::all()
            ->sortBy(fn (RankRule $r) => array_search($r->rank, RankService::LADDER, true))
            ->values()
            ->map(fn (RankRule $r) => [
                'rank' => $r->rank,
                'next' => app(RankService::class)->nextRank($r->rank),
                'missions_required' => $r->missions_required,
            ]);

        return Inertia::render('admin/ranks/Index', ['rules' => $rules]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rules' => ['required', 'array'],
            'rules.*.rank' => ['required', 'string'],
            'rules.*.missions_required' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        foreach ($data['rules'] as $row) {
            RankRule::where('rank', $row['rank'])->update([
                'missions_required' => $row['missions_required'],
            ]);
        }

        return back()->with('success', 'Ambang rank diperbarui.');
    }
}
