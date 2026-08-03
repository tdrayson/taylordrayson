<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * @var array<string, array<int, string>>
     */
    private const NAMES_BY_TYPE = [
        'concert' => ['Arctic Monkeys', 'Coldplay', 'The 1975', 'Sam Fender'],
        'musical' => ['Hamilton', 'Wicked', 'Six the Musical', 'Starlight Express'],
        'theatre' => ['War Horse', 'Present Laughter', 'The Curious Incident'],
        'magic' => ['Penn & Teller', 'Derren Brown', 'The Illusionists'],
        'comedy' => ['No Such Thing As A Fish', 'Max Fosh', 'Jimmy Carr'],
        'circus' => ['Cirque du Soleil', 'Cirque Berserk!'],
        'immersive' => ['Secret Cinema', 'Phantom Peak'],
        'sport' => ['Surrey vs Kent', 'Birmingham City vs Blackpool'],
        'festival' => ['Godstoneberry Beer Festival', 'Oxted Beer Festival'],
        'convention' => ['Blackpool Magic Convention', 'London Brick Festival'],
        'conference' => ['WordCamp Europe'],
        'exhibition' => ['The Photography Show', 'Ideal Home Show'],
        'dance' => ['Spirit of the Dance', 'Lord of the Dance'],
    ];

    /**
     * @var array<int, string>
     */
    private const VENUES = [
        'O2 Arena',
        'Roundhouse',
        'Comedy Store',
        'Hammersmith Apollo',
        'Wembley Stadium',
        'Brixton Academy',
        'Alexandra Palace',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // The category drives the sample name only; it is no longer persisted as
        // a column. Tests that need an event's category attach it as a tag with
        // ->syncTagNames([...]) or the category() state below.
        $category = fake()->randomElement(array_keys(self::NAMES_BY_TYPE));

        return [
            'occurred_at' => fake()->dateTimeBetween('-6 months'),
            'name' => fake()->randomElement(self::NAMES_BY_TYPE[$category]),
            'venue_name' => fake()->randomElement(self::VENUES),
            'city' => fake()->randomElement(['London', 'Manchester', 'Brighton', 'Bristol']),
            'country' => 'United Kingdom',
            'timezone' => 'Europe/London',
        ];
    }
}
