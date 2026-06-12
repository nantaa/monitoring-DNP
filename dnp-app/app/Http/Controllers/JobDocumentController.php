<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Models\JobDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class JobDocumentController extends Controller
{
    public function store(Request $request, Job $job)
    {
        $request->validate([
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png,webp,docx,xlsx,zip|max:10240',
            'type'     => 'required|string',
            'stage'    => 'required|integer|min:1|max:7',
        ]);

        $file = $request->file('document');
        $path = $file->store("jobs/{$job->id}/stage{$request->stage}", 'public');

        $doc = $job->documents()->create([
            'stage'         => $request->stage,
            'type'          => $request->type,
            'original_name' => $file->getClientOriginalName(),
            'stored_path'   => $path,
            'mime_type'     => $file->getMimeType(),
            'file_size'     => $file->getSize(),
            'uploaded_by'   => $request->user()->name,
        ]);

        return response()->json([
            'id'            => $doc->id,
            'original_name' => $doc->original_name,
            'url'           => Storage::url($path),
            'uploaded_by'   => $doc->uploaded_by,
            'created_at'    => $doc->created_at,
        ]);
    }

    public function destroy(Job $job, JobDocument $document)
    {
        $this->authorize('update', $job);
        Storage::disk('public')->delete($document->stored_path);
        $document->delete();
        return response()->json(['deleted' => true]);
    }
}
