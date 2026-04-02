<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'iata_code',
    'icao_code',
    'name',
    'country',
])]
class Airline extends Model {}
