<?php

namespace App\Http\Controllers;

use App\Queries\PhotoStream;
use App\Support\OgMeta;
use Inertia\Inertia;
use Inertia\Response;

class GalleryController extends Controller
{
    public function __construct(private readonly PhotoStream $photos) {}

    /**
     * The photo gallery: every real photo across every timeline entry, newest
     * first. The filtering and ordering live in PhotoStream so this page and the
     * "Life lately" widget on /now stay in lockstep.
     */
    public function index(): Response
    {
        return Inertia::render('Photos', [
            'og' => OgMeta::gallery(),
            'photos' => ($this->photos)(),
        ]);
    }
}
