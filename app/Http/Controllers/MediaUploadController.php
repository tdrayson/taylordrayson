<?php

namespace App\Http\Controllers;

use App\Support\PendingUploads;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Uploads for the entry editor. A file is parked under a token and only becomes
 * an attachment when the entry it belongs to is saved.
 */
class MediaUploadController extends Controller
{
    /**
     * Images match the conversions in HasAttachments, which cover raster and
     * SVG; the rest are documents an article can offer as a download.
     */
    /**
     * Checked against the uploaded filename rather than with `mimes:`, which
     * guesses an extension back from the content: a .sql or .yml file reads as
     * text/plain, so `mimes:sql` rejects the very file it names.
     *
     * No html, htm or xhtml. Body attachments are served from our own origin,
     * and a page that renders there is stored cross-site scripting. Executables
     * need no entry here, Media Library refusing them already. No sql or yaml
     * either: those are content for a code block, not a download.
     *
     * @var list<string>
     */
    private const EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'heic',
        'zip', 'gz', 'tar',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'rtf',
        'txt', 'md', 'csv', 'json', 'xml',
    ];

    /**
     * Deliberately loose, detection being inexact: finfo reads a small JSON
     * file as text/plain, a zip arrives under either of two names depending on
     * the browser, and anything it cannot place at all arrives as
     * octet-stream. The extension list above is the gate that matters.
     */
    private const TYPES = 'mimetypes:image/jpeg,image/png,image/webp,image/gif,image/svg+xml,image/heic,'
        .'application/zip,application/x-zip-compressed,application/gzip,application/x-gzip,application/x-tar,'
        .'application/pdf,application/json,application/xml,application/rtf,application/octet-stream,'
        .'application/msword,application/vnd.ms-excel,application/vnd.ms-powerpoint,'
        .'application/vnd.openxmlformats-officedocument.wordprocessingml.document,'
        .'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,'
        .'application/vnd.openxmlformats-officedocument.presentationml.presentation,'
        .'text/plain,text/csv,text/markdown,text/xml,text/rtf';

    private const MAX_KILOBYTES = 25600;

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                self::TYPES,
                'max:'.self::MAX_KILOBYTES,
                function (string $attribute, mixed $value, Closure $fail): void {
                    $extension = strtolower($value instanceof UploadedFile ? $value->getClientOriginalExtension() : '');

                    if (! in_array($extension, self::EXTENSIONS, true)) {
                        $fail('The upload must be one of: '.implode(', ', self::EXTENSIONS).'.');
                    }
                },
            ],
        ], [], ['file' => 'upload']);

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
