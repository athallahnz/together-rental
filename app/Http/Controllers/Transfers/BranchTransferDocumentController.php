<?php

namespace App\Http\Controllers\Transfers;

use App\Http\Controllers\Controller;
use App\Models\AssetInspectionMedia;
use App\Models\BranchTransferDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BranchTransferDocumentController extends Controller
{
    public function show(Request $request, BranchTransferDocument $document): StreamedResponse
    {
        Gate::authorize('transfers.view');
        $document->loadMissing('transfer');
        $accessible = $request->user()->accessibleBranches()->pluck('id');
        abort_unless(
            $accessible->contains($document->transfer->from_branch_id)
                || $accessible->contains($document->transfer->to_branch_id),
            404,
        );
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->download(
            $document->path,
            $document->original_name ?? basename($document->path),
        );
    }

    public function inspectionMedia(Request $request, AssetInspectionMedia $media): StreamedResponse
    {
        Gate::authorize('transfers.view');
        $media->loadMissing('inspection.transferItem.transfer');
        $transfer = $media->inspection?->transferItem?->transfer;
        abort_unless($transfer !== null, 404);

        $accessible = $request->user()->accessibleBranches()->pluck('id');
        abort_unless(
            $accessible->contains($transfer->from_branch_id)
                || $accessible->contains($transfer->to_branch_id),
            404,
        );
        abort_unless(Storage::disk('local')->exists($media->path), 404);

        $metadata = is_array($media->metadata) ? $media->metadata : [];
        $filename = is_string($metadata['original_name'] ?? null)
            ? $metadata['original_name']
            : basename($media->path);

        return Storage::disk('local')->download($media->path, $filename);
    }
}
