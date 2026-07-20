<?php

namespace App\Http\Controllers;

use App\Support\OgMeta;
use Inertia\Inertia;
use Inertia\Response;

class DesignSystemController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('DesignSystem', [
            'og' => OgMeta::designSystem(),
        ]);
    }
}
