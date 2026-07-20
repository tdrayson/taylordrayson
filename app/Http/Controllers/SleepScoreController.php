<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class SleepScoreController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('SleepScore', [
            'og' => ['title' => 'How the sleep score works'],
        ]);
    }
}
