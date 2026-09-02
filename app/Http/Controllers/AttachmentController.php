<?php

namespace App\Http\Controllers;

use App\Actions\Attachments\UpdateAttachment;
use App\Http\Requests\AttachmentRequest;
use App\Models\Attachment;
use Illuminate\Http\RedirectResponse;

class AttachmentController extends Controller
{
    public function __invoke(AttachmentRequest $request, Attachment $attachment, UpdateAttachment $update): RedirectResponse
    {
        $update($attachment, $request->validated('alt'), $request->validated('caption'));

        return back();
    }
}
