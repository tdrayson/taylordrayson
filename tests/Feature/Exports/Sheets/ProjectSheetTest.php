<?php

use App\Enums\ExportFormat;
use App\Models\Project;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;

it('prints a project as its package manifest, with name, stage and links that fit as rows', function () {
    $project = Project::factory()->create([
        'title' => 'Deploy CLI',
        'stage' => 'active',
        'url' => 'https://deploy-cli.example.com',
        'github_url' => 'https://github.com/tdrayson/deploy-cli',
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($project);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->toContain('Deploy CLI')
        ->and($txt)->toContain('STAGE')
        ->and($txt)->toContain('Active')
        ->and($txt)->toContain('SITE')
        ->and($txt)->toContain('https://deploy-cli.example.com')
        ->and($txt)->toContain('CODE')
        ->and($txt)->toContain('https://github.com/tdrayson/deploy-cli')
        ->and(max(array_map('mb_strlen', explode("\n", $txt))))->toBeLessThanOrEqual(46);
});

it('drops a link row when its url is too long to share the line', function () {
    $project = Project::factory()->create([
        'title' => 'Indian Ocean',
        'stage' => 'active',
        'url' => null,
        // This repo's own real address: 42 characters, which with the
        // 4-character CODE label overflows the 46-character row.
        'github_url' => 'https://github.com/tdrayson/indian-ocean-2',
        'status' => 'published',
    ]);

    $data = ExportPresenter::for($project);
    $txt = Formats::find($data, ExportFormat::Txt)->render($data);

    expect($txt)->not->toContain('CODE')
        ->and($txt)->not->toContain('github.com');
});
