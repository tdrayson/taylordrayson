<?php

namespace Tests\Feature;

use App\Actions\Fuel\ResolveFuelStation;
use App\Models\FuelStation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ResolveFuelStationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function creates_a_new_station_with_brand_and_address(): void
    {
        $action = new ResolveFuelStation;

        $station = $action([
            'name' => 'Shell London',
            'brand' => 'Shell',
            'address' => '123 Main Street',
            'city' => 'London',
            'country' => 'UK',
            'latitude' => 51.5074,
            'longitude' => -0.1278,
        ]);

        $this->assertInstanceOf(FuelStation::class, $station);
        $this->assertEquals('Shell London', $station->name);
        $this->assertEquals('Shell', $station->brand);
        $this->assertEquals('123 Main Street', $station->address);
        $this->assertTrue($station->exists);
    }

    #[Test]
    public function matches_existing_station_case_insensitively(): void
    {
        $original = FuelStation::factory()->create(['name' => 'BP Manchester']);
        $action = new ResolveFuelStation;

        $station = $action([
            'name' => 'bp manchester',
            'brand' => 'BP',
            'address' => '456 Oak Road',
        ]);

        $this->assertEquals($original->id, $station->id);
        $this->assertCount(1, FuelStation::all());
    }

    #[Test]
    public function fills_missing_brand_without_overwriting_stored_address(): void
    {
        $original = FuelStation::factory()->create([
            'name' => 'Esso Birmingham',
            'brand' => null,
            'address' => '789 Elm Street',
        ]);
        $action = new ResolveFuelStation;

        $station = $action([
            'name' => 'esso birmingham',
            'brand' => 'Esso',
            'address' => 'New Address',
        ]);

        $this->assertEquals($original->id, $station->id);
        $this->assertEquals('Esso', $station->brand);
        $this->assertEquals('789 Elm Street', $station->address); // not overwritten
    }

    #[Test]
    public function never_overwrites_non_null_stored_values(): void
    {
        $original = FuelStation::factory()->create([
            'name' => 'Tesco Liverpool',
            'brand' => 'Tesco',
            'address' => '321 Pine Avenue',
            'city' => 'Liverpool',
            'country' => 'UK',
        ]);
        $action = new ResolveFuelStation;

        $station = $action([
            'name' => 'tesco liverpool',
            'brand' => 'TescoUpdated',
            'address' => 'New Address',
            'city' => 'New City',
            'country' => 'New Country',
            'latitude' => 53.4084,
            'longitude' => -2.9916,
        ]);

        $this->assertEquals($original->id, $station->id);
        $this->assertEquals('Tesco', $station->brand);
        $this->assertEquals('321 Pine Avenue', $station->address);
        $this->assertEquals('Liverpool', $station->city);
        $this->assertEquals('UK', $station->country);
    }

    #[Test]
    public function fills_multiple_missing_values_on_existing_record(): void
    {
        $original = FuelStation::factory()->create([
            'name' => 'Texaco Cardiff',
            'brand' => null,
            'address' => null,
            'city' => null,
            'country' => null,
        ]);
        $action = new ResolveFuelStation;

        $station = $action([
            'name' => 'texaco cardiff',
            'brand' => 'Texaco',
            'address' => '999 Rose Lane',
            'city' => 'Cardiff',
            'country' => 'Wales',
        ]);

        $this->assertEquals($original->id, $station->id);
        $this->assertEquals('Texaco', $station->brand);
        $this->assertEquals('999 Rose Lane', $station->address);
        $this->assertEquals('Cardiff', $station->city);
        $this->assertEquals('Wales', $station->country);
    }

    #[Test]
    public function ignores_null_values_when_filling(): void
    {
        $original = FuelStation::factory()->create([
            'name' => "Sainsbury's Dublin",
            'brand' => null,
            'address' => '111 High Street',
        ]);
        $action = new ResolveFuelStation;

        $station = $action([
            'name' => "sainsbury's dublin",
            'brand' => "Sainsbury's",
            'address' => null,
            'city' => null,
        ]);

        $this->assertEquals($original->id, $station->id);
        $this->assertEquals("Sainsbury's", $station->brand);
        $this->assertEquals('111 High Street', $station->address); // not affected
    }

    #[Test]
    public function trims_the_stored_name_so_padded_input_cannot_create_duplicates(): void
    {
        $action = new ResolveFuelStation;

        $first = $action(['name' => '  Shell London  ']);
        $second = $action(['name' => 'shell london']);

        $this->assertEquals('Shell London', $first->name);
        $this->assertEquals($first->id, $second->id);
        $this->assertCount(1, FuelStation::all());
    }
}
