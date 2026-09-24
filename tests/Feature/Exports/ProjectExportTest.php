<?php

use App\Models\Project;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Formats;

it('publishes a project as labelled fields in order', function () {
    $project = Project::factory()->create([
        'occurred_at' => '2026-09-13 12:00:00',
        'title' => 'Deploy CLI',
        'slug' => 'deploy-cli',
        'stage' => 'active',
        'url' => 'https://deploy-cli.example.com',
        'github_url' => 'https://github.com/example/deploy-cli',
        'long_description' => 'A CLI for deploying projects.',
        'status' => 'published',
    ]);

    $export = ExportPresenter::for($project);

    expect(array_map(fn ($f) => $f->key, $export->fields))
        ->toBe(['project', 'stage'])
        ->and($export->field('project')->display)->toBe('Deploy CLI')
        ->and($export->field('stage')->display)->toBe('Active')
        ->and($export->field('stage')->raw)->toBe('active')
        ->and($export->body)->toBe('A CLI for deploying projects.');

    $links = array_map(fn ($l) => $l->key, $export->links);

    expect($links)->toContain('site', 'code', 'type', 'day');
});

it('offers neither geojson nor ics for a project', function () {
    $project = Project::factory()->create(['occurred_at' => '2026-09-13 12:00:00', 'url' => null, 'github_url' => null, 'status' => 'published']);

    $formats = array_keys(Formats::for(ExportPresenter::for($project)));

    expect($formats)->not->toContain('geojson')
        ->and($formats)->not->toContain('ics');

    $links = array_map(fn ($l) => $l->key, ExportPresenter::for($project)->links);

    expect($links)->not->toContain('site', 'code');
});

it('never leaks an id, a timestamp or a password', function () {
    $project = Project::factory()->create(['occurred_at' => '2026-09-13 12:00:00', 'status' => 'published']);

    $json = json_encode(ExportPresenter::for($project)->toArray());

    expect($json)->not->toContain('password')
        ->not->toContain('created_at')
        ->not->toContain('updated_at');
});
