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
        'concert' => ['Arctic Monkeys', 'Coldplay', 'The 1975', 'Ed Sheeran', 'Foo Fighters', 'Oasis', 'Sam Fender'],
        'theatre' => ['Hamilton', 'The Lion King', 'Wicked', 'Les Misérables', 'Phantom of the Opera', 'Matilda'],
        'comedy' => ['Michael McIntyre', 'Jimmy Carr', 'Peter Kay', 'Kevin Hart', 'James Acaster', 'Ricky Gervais'],
        'festival' => ['Glastonbury', 'Reading Festival', 'Wireless', 'All Points East', 'BST Hyde Park'],
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
        $type = fake()->randomElement(array_keys(self::NAMES_BY_TYPE));
        $name = fake()->randomElement(self::NAMES_BY_TYPE[$type]);

        return [
            'occurred_at' => fake()->dateTimeBetween('-6 months'),
            'type' => $type,
            'name' => $name,
            'venue_name' => fake()->randomElement(self::VENUES),
            'city' => fake()->randomElement(['London', 'Manchester', 'Brighton', 'Bristol']),
            'country' => 'United Kingdom',
        ];
    }
}
