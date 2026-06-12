<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Services\RecommenderService;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    public function __invoke(Request $request, Job $job, RecommenderService $recommender)
    {
        $allJobs = Job::select(['id', 'klien', 'pesawat', 'stage', 'inspektur_ids', 'tgl_pelaksanaan', 'durasi_hari', 'field_completed_at', 'completed_at'])->get()->toArray();
        $results = $recommender->recommend($job->toArray(), $allJobs);
        return response()->json($results);
    }
}
