<?php

namespace App\Http\Controllers;

use App\Models\Job;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $role = $user->getRoleNames()->first();

        $baseQuery = Job::query();
        if ($role === 'inspektur') {
            $inspId = $user->inspektur?->id;
            if ($inspId) $baseQuery->whereJsonContains('inspektur_ids', $inspId);
        }

        $allJobs = (clone $baseQuery)->get();

        $stats = [
            'total_active'  => (clone $baseQuery)->whereBetween('stage', [1, 6])->count(),
            'overdue'       => $allJobs->filter(fn($j) => $j->status === 'overdue')->count(),
            'stage_counts'  => $allJobs->groupBy('stage')->map->count(),
            'total_nilai'   => (clone $baseQuery)->sum('nilai'),
        ];

        // Finance-specific
        if (in_array($role, ['finance', 'manager', 'admin'])) {
            $stats['unpaid_invoice'] = (clone $baseQuery)->where('stage', 7)->where('payment_status', '!=', 'paid')->sum('nilai');
            $stats['overdue_payment'] = (clone $baseQuery)->where('stage', 7)->where('payment_status', 'overdue')->count();
        }

        // Suket expiry in 90 days
        $suketExpiring = Job::where('stage', '>=', 6)->get()->filter(function ($job) {
            foreach ($job->units_tracking ?? [] as $unit) {
                if (!empty($unit['suket_expired_at']) && Carbon::parse($unit['suket_expired_at'])->diffInDays(now(), false) >= -90) return true;
            }
            return false;
        })->count();
        $stats['suket_expiring_soon'] = $suketExpiring;

        $recentJobs = (clone $baseQuery)->with('documents')->orderByDesc('updated_at')->limit(10)->get()->append('status');

        return Inertia::render('Dashboard/Index', [
            'stats'      => $stats,
            'recentJobs' => $recentJobs,
            'role'       => $role,
        ]);
    }
}
