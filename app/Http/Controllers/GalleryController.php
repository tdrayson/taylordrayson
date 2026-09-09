<?php

namespace App\Http\Controllers;

use App\Queries\PhotoStream;
use App\Support\OgMeta;
use Illuminate\Pagination\Paginator;
use Inertia\Inertia;
use Inertia\Response;

class GalleryController extends Controller
{
    /**
     * Photos shaped per request. Rather more than a screenful on a wide
     * display, so the grid is still filling as the next page is fetched.
     */
    private const PER_PAGE = 60;

    public function __construct(private readonly PhotoStream $photos) {}

    /**
     * The photo gallery: every real photo across every timeline entry, newest
     * first. The filtering and ordering live in PhotoStream so this page and the
     * "Life lately" widget on /now stay in lockstep.
     *
     * A page at a time, deferred, and appended as the visitor scrolls: shaping
     * all of them took seconds, and nothing could paint until it finished.
     */
    public function index(): Response
    {
        return Inertia::render('Photos', [
            'og' => OgMeta::gallery(),
            'total' => $this->photos->count(),
            'photos' => Inertia::scroll(
                fn () => $this->photos->paginate(self::PER_PAGE, Paginator::resolveCurrentPage()),
            )->defer(),
        ]);
    }
}
