<?php

namespace App\Services\PetrolPrices;

use App\Services\ApiConnector;

/** The PetrolPrices.com forecourt lookup. Needs no credentials. */
class PetrolPricesConnector extends ApiConnector
{
    public function resolveBaseUrl(): string
    {
        return 'https://www.petrolprices.com';
    }
}
