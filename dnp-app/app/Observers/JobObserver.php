<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\Job;
use Illuminate\Support\Facades\Auth;

class JobObserver
{
    public function created(Job $job): void
    {
        AuditLog::create([
            'table_name'    => 'dnp_jobs',
            'record_id'     => $job->id,
            'operation'     => 'INSERT',
            'old_data'      => null,
            'new_data'      => $job->toArray(),
            'changed_by'    => Auth::id(),
            'changed_by_name' => Auth::user()?->name,
        ]);
    }

    public function updated(Job $job): void
    {
        $dirty = $job->getDirty();
        if (empty($dirty)) return;

        AuditLog::create([
            'table_name'    => 'dnp_jobs',
            'record_id'     => $job->id,
            'operation'     => 'UPDATE',
            'old_data'      => array_intersect_key($job->getOriginal(), $dirty),
            'new_data'      => $dirty,
            'changed_by'    => Auth::id(),
            'changed_by_name' => Auth::user()?->name,
        ]);
    }

    public function deleted(Job $job): void
    {
        AuditLog::create([
            'table_name'    => 'dnp_jobs',
            'record_id'     => $job->id,
            'operation'     => 'DELETE',
            'old_data'      => $job->toArray(),
            'new_data'      => null,
            'changed_by'    => Auth::id(),
            'changed_by_name' => Auth::user()?->name,
        ]);
    }
}
