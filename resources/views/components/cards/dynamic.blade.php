@props(['entry'])

@php
$componentMap = [
    \App\Models\Activity::class   => 'cards.activity',
    \App\Models\Sleep::class      => 'cards.sleep',
    \App\Models\Calorie::class    => 'cards.calories',
    \App\Models\Media::class      => 'cards.media',
    \App\Models\Event::class      => 'cards.event',
    \App\Models\Appearance::class => 'cards.appearance',
    \App\Models\Podcast::class    => 'cards.podcast',
    \App\Models\Flight::class     => 'cards.flight',
    \App\Models\Checkin::class    => 'cards.checkin',
    \App\Models\Fuel::class       => 'cards.fuel',
    \App\Models\Project::class    => 'cards.project',
    \App\Models\Article::class    => 'cards.article',
    \App\Models\Note::class       => 'cards.note',
];
$component = $componentMap[get_class($entry)] ?? null;
@endphp

@if($component)
    <x-dynamic-component :component="$component" :entry="$entry" />
@endif
