<?php

namespace App\Services;

use App\Models\InspekturList;
use Carbon\Carbon;

class RecommenderService
{
    private const PESAWAT_SPESIALISASI = [
        'LIFT' => ['PAA', 'Umum'], 'ESC' => ['PAA', 'Umum'], 'PAPA' => ['PAA', 'Umum'],
        'FIRE' => ['Kebakaran', 'Umum'], 'LISTRIK' => ['Listrik', 'Umum'],
        'BOILER' => ['PUBT', 'Umum'], 'PV' => ['PUBT', 'Umum'], 'PTP' => ['PUBT', 'Umum'],
    ];
    private const CRITICAL = ['BOILER', 'PV'];
    private const JABODETABEK = ['bekasi', 'jakarta', 'tangerang', 'depok', 'bogor', 'cikampek', 'cikarang'];

    public function recommend(array $targetJob, array $allJobs): array
    {
        $inspekturList = InspekturList::where('active', true)->get();
        $requiredSpecs = self::PESAWAT_SPESIALISASI[$targetJob['pesawat']] ?? ['Umum'];
        $today = Carbon::today();

        return $inspekturList->map(function ($insp) use ($targetJob, $allJobs, $requiredSpecs, $today) {
            $skpDays = $insp->skp_expired ? $today->diffInDays($insp->skp_expired, false) : -9999;

            if ($skpDays < 0) return ['inspektur' => $insp, 'score' => -1, 'qualified' => false, 'reason' => 'SKP expired'];
            $specOverlap = array_intersect($insp->spesialisasi ?? [], $requiredSpecs);
            if (empty($specOverlap)) return ['inspektur' => $insp, 'score' => -1, 'qualified' => false, 'reason' => 'Spesialisasi tidak sesuai'];
            if ($this->hasConflict($insp->id, $targetJob, $allJobs)) return ['inspektur' => $insp, 'score' => -1, 'qualified' => false, 'reason' => 'Bentrok jadwal'];

            $score = 0; $breakdown = []; $bonuses = [];

            $isPrimary = ($insp->spesialisasi[0] ?? '') === $requiredSpecs[0];
            $specScore = $isPrimary ? 30 : 15;
            $score += $specScore;
            $breakdown[] = ['label' => 'Spesialisasi', 'value' => $specScore, 'max' => 30];

            $activeJobs = array_filter($allJobs, fn($j) => !($j['completed_at'] ?? null) && ($j['stage'] ?? 0) >= 3 && ($j['stage'] ?? 0) <= 5 && in_array($insp->id, $j['inspektur_ids'] ?? []));
            $wl = count($activeJobs);
            $wlScore = max(0, 25 - $wl * 5);
            $score += $wlScore;
            $breakdown[] = ['label' => 'Workload', 'value' => $wlScore, 'max' => 25, 'detail' => "{$wl} job aktif"];

            $clientJobs = array_filter($allJobs, fn($j) => ($j['klien'] ?? '') === ($targetJob['klien'] ?? '') && in_array($insp->id, $j['inspektur_ids'] ?? []));
            $clientScore = min(15, count($clientJobs) * 5);
            $score += $clientScore;
            $breakdown[] = ['label' => 'Pengalaman Klien', 'value' => $clientScore, 'max' => 15];

            $pesawatJobs = array_filter($allJobs, fn($j) => ($j['pesawat'] ?? '') === ($targetJob['pesawat'] ?? '') && in_array($insp->id, $j['inspektur_ids'] ?? []));
            $pesawatScore = min(15, count($pesawatJobs) * 3);
            $score += $pesawatScore;
            $breakdown[] = ['label' => 'Pengalaman Pesawat', 'value' => $pesawatScore, 'max' => 15];

            $recencyScore = 15;
            $fieldJobs = array_filter($allJobs, fn($j) => ($j['stage'] ?? 0) >= 4 && in_array($insp->id, $j['inspektur_ids'] ?? []) && !empty($j['field_completed_at']));
            if (!empty($fieldJobs)) {
                $lastDates = array_column($fieldJobs, 'field_completed_at');
                sort($lastDates);
                $daysSince = Carbon::parse(end($lastDates))->diffInDays($today);
                $recencyScore = min(15, max(0, $daysSince * 5));
            }
            $score += $recencyScore;
            $breakdown[] = ['label' => 'Availability', 'value' => $recencyScore, 'max' => 15];

            if ($skpDays >= 365) { $score += 5; $bonuses[] = ['+5 SKP berlaku >1 thn', true]; }
            if ($this->domisiliMatch($insp->domisili, $targetJob['lokasi'] ?? '')) { $score += 5; $bonuses[] = ["+5 Domisili {$insp->domisili}", true]; }
            if (in_array($targetJob['pesawat'] ?? '', self::CRITICAL)) {
                if ($insp->senior_level >= 3) { $score += 10; $bonuses[] = ['+10 Senior critical', true]; }
                else { $score -= 5; $bonuses[] = ['-5 Junior critical', false]; }
            }
            if ($wl >= 4) { $score -= 10; $bonuses[] = ['-10 Overload', false]; }

            return ['inspektur' => $insp, 'score' => max(0, $score), 'qualified' => true, 'breakdown' => $breakdown, 'bonuses' => $bonuses, 'workloadCount' => $wl];
        })->sortByDesc('score')->values()->toArray();
    }

    private function hasConflict(string $inspId, array $target, array $allJobs): bool
    {
        if (empty($target['tgl_pelaksanaan'])) return false;
        $s = Carbon::parse($target['tgl_pelaksanaan']);
        $e = $s->copy()->addDays(($target['durasi_hari'] ?? 1) - 1);
        foreach ($allJobs as $j) {
            if (!in_array($j['stage'] ?? 0, [3, 4]) || ($j['completed_at'] ?? null) || !in_array($inspId, $j['inspektur_ids'] ?? []) || empty($j['tgl_pelaksanaan'])) continue;
            $js = Carbon::parse($j['tgl_pelaksanaan']);
            $je = $js->copy()->addDays(($j['durasi_hari'] ?? 1) - 1);
            if (!($e->lt($js) || $s->gt($je))) return true;
        }
        return false;
    }

    private function domisiliMatch(?string $d, ?string $l): bool
    {
        if (!$d || !$l) return false;
        $d = strtolower($d); $l = strtolower($l);
        $inD = !empty(array_filter(self::JABODETABEK, fn($c) => str_contains($d, $c)));
        $inL = !empty(array_filter(self::JABODETABEK, fn($c) => str_contains($l, $c)));
        return ($inD && $inL) || str_contains($l, $d);
    }
}
