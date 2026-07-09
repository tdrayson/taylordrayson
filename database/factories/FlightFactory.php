<?php

namespace Database\Factories;

use App\Models\Flight;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Flight>
 */
class FlightFactory extends Factory
{
    /**
     * @var array<string, array{lat: float, lon: float, name: string, city: string, country: string}>
     */
    private const AIRPORTS = [
        'LHR' => ['lat' => 51.4700, 'lon' => -0.4543, 'name' => 'Heathrow Airport', 'city' => 'London', 'country' => 'United Kingdom'],
        'JFK' => ['lat' => 40.6413, 'lon' => -73.7781, 'name' => 'John F. Kennedy International Airport', 'city' => 'New York', 'country' => 'United States'],
        'CDG' => ['lat' => 49.0097, 'lon' => 2.5479, 'name' => 'Charles de Gaulle Airport', 'city' => 'Paris', 'country' => 'France'],
        'AMS' => ['lat' => 52.3105, 'lon' => 4.7683, 'name' => 'Schiphol Airport', 'city' => 'Amsterdam', 'country' => 'Netherlands'],
        'DXB' => ['lat' => 25.2532, 'lon' => 55.3657, 'name' => 'Dubai International Airport', 'city' => 'Dubai', 'country' => 'United Arab Emirates'],
        'SIN' => ['lat' => 1.3644, 'lon' => 103.9915, 'name' => 'Changi Airport', 'city' => 'Singapore', 'country' => 'Singapore'],
        'LAX' => ['lat' => 33.9425, 'lon' => -118.4081, 'name' => 'Los Angeles International Airport', 'city' => 'Los Angeles', 'country' => 'United States'],
        'FCO' => ['lat' => 41.8003, 'lon' => 12.2389, 'name' => 'Leonardo da Vinci Airport', 'city' => 'Rome', 'country' => 'Italy'],
        'BCN' => ['lat' => 41.2971, 'lon' => 2.0785, 'name' => 'Barcelona-El Prat Airport', 'city' => 'Barcelona', 'country' => 'Spain'],
        'LIS' => ['lat' => 38.7742, 'lon' => -9.1342, 'name' => 'Humberto Delgado Airport', 'city' => 'Lisbon', 'country' => 'Portugal'],
    ];

    /**
     * @var array<string, string>
     */
    private const AIRLINES = [
        'BAW' => 'British Airways',
        'UAE' => 'Emirates',
        'KLM' => 'KLM',
        'AFR' => 'Air France',
        'DLH' => 'Lufthansa',
        'IBE' => 'Iberia',
        'SIA' => 'Singapore Airlines',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $codes = array_keys(self::AIRPORTS);
        $originIata = fake()->randomElement($codes);

        $destinationCodes = array_values(array_diff($codes, [$originIata]));
        $destinationIata = fake()->randomElement($destinationCodes);

        $airlineIcao = fake()->randomElement(array_keys(self::AIRLINES));
        $flightNumber = $airlineIcao.fake()->numberBetween(100, 9999);

        return [
            'occurred_at' => fake()->dateTimeBetween('-6 months'),
            'flight_number' => $flightNumber,
            'airline_icao' => $airlineIcao,
            'origin_iata' => $originIata,
            'destination_iata' => $destinationIata,
            'distance' => fake()->numberBetween(300000, 9000000),
            'cabin_class' => fake()->randomElement(['economy', 'business', null]),
            'reason' => fake()->randomElement(['personal', 'business']),
            'meta' => [
                'aircraft' => fake()->randomElement(['Boeing 737-800', 'Airbus A320', 'Boeing 777-300ER']),
            ],
        ];
    }
}
