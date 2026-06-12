<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Models\InspekturList;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Carbon\Carbon;

class JobController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Job::query()->with('documents');

        if ($user->hasRole('inspektur')) {
            $query->whereJsonContains('inspektur_ids', $user->inspektur?->id);
        }

        if ($request->filled('stage')) $query->where('stage', $request->stage);
        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(fn($qb) => $qb->where('klien', 'ilike', "%{$q}%")->orWhere('kode', 'ilike', "%{$q}%")->orWhere('lokasi', 'ilike', "%{$q}%"));
        }

        $jobs = $query->orderByDesc('updated_at')->paginate(50)->withQueryString();

        return Inertia::render('Jobs/Index', [
            'jobs'    => $jobs,
            'filters' => $request->only(['stage', 'search']),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'klien'           => 'required|string|max:255',
            'lokasi'          => 'required|string|max:255',
            'pesawat'         => 'required|string',
            'units'           => 'required|integer|min:1',
            'nilai'           => 'required|integer|min:0',
            'owner_marketing' => 'required|string|max:255',
            'no_po'           => 'nullable|string',
            'tgl_po'          => 'nullable|date',
            'pic_klien'       => 'nullable|string',
            'pic_klien_phone' => 'nullable|string',
            'notes'           => 'nullable|string',
        ]);

        $seq = Job::max('id') ? Job::count() + 1 : 1;
        $year = now()->year;
        // Generate sequential code
        $lastCode = Job::orderByDesc('created_at')->value('kode');
        $lastSeq = $lastCode ? (int) substr($lastCode, -4) : 0;
        $kode = "DNP/{$year}/" . str_pad($lastSeq + 1, 4, '0', STR_PAD_LEFT);

        $job = Job::create([
            ...$request->only(['klien', 'lokasi', 'pesawat', 'units', 'nilai', 'owner_marketing', 'no_po', 'tgl_po', 'pic_klien', 'pic_klien_phone', 'notes']),
            'kode'            => $kode,
            'stage'           => 1,
            'stage_started_at'=> now()->toDateString(),
            'created_by'      => $request->user()->id,
            'history'         => [['stage' => 1, 'ts' => now()->toISOString(), 'by' => $request->user()->name, 'action' => 'Job dibuat']],
        ]);

        return redirect()->back()->with('success', "Job {$kode} berhasil dibuat.");
    }

    public function update(Request $request, Job $job)
    {
        $this->authorize('update', $job);
        $data = $request->except(['_token', '_method', 'id', 'kode', 'created_at', 'updated_at', 'created_by']);
        $job->update($data);
        return redirect()->back()->with('success', 'Job berhasil diperbarui.');
    }

    public function advanceStage(Request $request, Job $job)
    {
        $this->authorize('advanceStage', $job);
        $this->validateStageGate($job);

        $newStage = $job->stage + 1;
        $history  = $job->history ?? [];
        $history[] = ['stage' => $newStage, 'ts' => now()->toISOString(), 'by' => $request->user()->name, 'action' => "Maju ke Stage {$newStage}"];

        $updates = ['stage' => $newStage, 'stage_started_at' => now()->toDateString(), 'history' => $history];
        if ($newStage === 7) $updates['completed_at'] = null;
        $job->update($updates);

        return redirect()->back()->with('success', "Job berhasil maju ke Stage {$newStage}.");
    }

    public function destroy(Request $request, Job $job)
    {
        $this->authorize('delete', $job);
        $job->delete();
        return redirect()->back()->with('success', 'Job berhasil dihapus.');
    }

    private function validateStageGate(Job $job): void
    {
        $errors = match($job->stage) {
            1 => $job->nilai <= 0 ? ['nilai' => 'Nilai kontrak harus diisi'] : [],
            2 => [],
            3 => !$job->tgl_pelaksanaan || empty($job->inspektur_ids) ? ['stage3' => 'Tanggal dan inspektur harus diisi'] : [],
            4 => [],
            5 => $job->peer_review_status !== 'approved' ? ['peer_review' => 'LHPP harus disetujui Manager'] : [],
            6 => collect($job->units_tracking ?? [])->some(fn($u) => ($u['status'] ?? '') !== 'issued') ? ['suket' => 'Semua unit harus terbit suketnya'] : [],
            default => [],
        };

        if (!empty($errors)) abort(422, collect($errors)->first());
    }
}
