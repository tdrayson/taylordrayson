<?php

namespace App\Http\Controllers;

use App\Support\PendingUploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Uploads for the entry editor. A file is parked under a token and only becomes
 * an attachment when the entry it belongs to is saved.
 */
class MediaUploadController extends Controller
{
    /** Matches the conversions in HasAttachments, which cover raster and SVG. */
    private const ACCEPTED = 'mimetypes:image/jpeg,image/png,image/webp,image/gif,image/svg+xml,image/heic';

    private const MAX_KILOBYTES = 25600;

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', self::ACCEPTED, 'max:'.self::MAX_KILOBYTES],
        ], [], ['file' => 'image']);

        $file = $request->file('file');
        $name = $file->getClientOriginalName();
        $token = PendingUploads::store($file);

        return response()->json(['data' => [
            'id' => 'pending:'.$token,
            'name' => $name,
            'url' => route('media.pending.show', $token),
        ]]);
    }

    /**
     * Serve a parked upload so the editor can preview it before the save. Behind
     * auth with the rest of the authoring surface: nothing here is public until
     * it is attached to a published entry.
     */
    public function show(string $token): BinaryFileResponse|Response
    {
        $path = PendingUploads::path($token);

        abort_if($path === null, 404);

        return response()->file($path);
    }
}
