<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\TagResource;
use App\Models\Tag;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TagController extends Controller
{
    /**
     * Every tag, alphabetically, with how many entries carry it. Unlike the
     * /tags page this keeps unused tags and counts drafts: the caller is an
     * authoring client picking what to write, not a visitor browsing.
     */
    public function __invoke(): AnonymousResourceCollection
    {
        $tags = Tag::query()->withCount('taggables')->orderBy('name')->get();

        return TagResource::collection($tags);
    }
}
