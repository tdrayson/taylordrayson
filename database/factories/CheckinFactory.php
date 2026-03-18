<?php

namespace Database\Factories;

use App\Models\Checkin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Checkin>
 */
class CheckinFactory extends Factory
{
    /**
     * @var array<string, string>
     */
    private const VENUES = [
        'Costa Coffee' => 'Coffee Shop',
        'Pret A Manger' => 'Café',
        'The Crown' => 'Pub',
        'Tesco' => 'Supermarket',
        'Pizza Express' => 'Restaurant',
        'Wagamama' => 'Restaurant',
        "Nando's" => 'Restaurant',
        'Vue Cinema' => 'Cinema',
        'The Gym' => 'Gym',
        'Boots' => 'Pharmacy',
    ];

    /**
     * @var array<string, array{lat: float, lon: float}>
     */
    private const CITIES = [
        'London' => ['lat' => 51.5074, 'lon' => -0.1278],
        'Croydon' => ['lat' => 51.3762, 'lon' => -0.0982],
        'Brighton' => ['lat' => 50.8225, 'lon' => -0.1372],
        'Manchester' => ['lat' => 53.4808, 'lon' => -2.2426],
        'Bristol' => ['lat' => 51.4545, 'lon' => -2.5879],
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $venueName = fake()->randomElement(array_keys(self::VENUES));
        $category = self::VENUES[$venueName];
        $city = fake()->randomElement(array_keys(self::CITIES));
        $coords = self::CITIES[$city];

        return [
            'occurred_at' => fake()->dateTimeBetween('-6 months'),
            'venue_name' => $venueName,
            'category' => $category,
            'city' => $city,
            'country' => 'United Kingdom',
            'latitude' => $coords['lat'] + fake()->randomFloat(4, -0.05, 0.05),
            'longitude' => $coords['lon'] + fake()->randomFloat(4, -0.05, 0.05),
        ];
    }
}
