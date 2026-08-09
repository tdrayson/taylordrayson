<?php

namespace App\Services\PetrolPrices;

/**
 * Canonical display name and logo domain for UK fuel brands, keyed by the
 * brand's own name.
 *
 * Keyed by name rather than by any feed's identifier: names are real-world
 * identity, so this map survives a change of data source, where a vendor's
 * internal ids would silently mis-brand every row if they were renumbered.
 * Lookup is case-insensitive because feeds report brands in mixed case.
 *
 * Domains exist because logo.dev is keyed by domain and no station feed carries
 * one. Brands with no resolvable domain are listed with null so the name still
 * canonicalises; they simply go without a logo.
 */
class FuelBrands
{
    /**
     * @var array<string, array{name: string, domain: string|null}>
     */
    private const BRANDS = [
        'APPLEGREEN' => ['name' => 'Applegreen', 'domain' => 'applegreenstores.com'],
        'ASCONA' => ['name' => 'Ascona', 'domain' => 'asconagroup.co.uk'],
        'ASDA' => ['name' => 'Asda', 'domain' => 'asda.com'],
        'BP' => ['name' => 'BP', 'domain' => 'bp.com'],
        'CENTRA' => ['name' => 'Centra', 'domain' => 'centra.ie'],
        'CERTAS ENERGY' => ['name' => 'Certas Energy', 'domain' => 'certasenergy.co.uk'],
        'CIRCLE K' => ['name' => 'Circle K', 'domain' => 'circlek.co.uk'],
        'CIRCLE-K' => ['name' => 'Circle K', 'domain' => 'circlek.co.uk'],
        'CO-OP' => ['name' => 'Co-op', 'domain' => 'coop.co.uk'],
        'COOP' => ['name' => 'Co-op', 'domain' => 'coop.co.uk'],
        'COSTCO' => ['name' => 'Costco', 'domain' => 'costco.co.uk'],
        'COSTCUTTER' => ['name' => 'Costcutter', 'domain' => 'costcutter.co.uk'],
        'EG GROUP' => ['name' => 'EG Group', 'domain' => 'eurogarages.com'],
        'EG ON THE MOVE' => ['name' => 'EG On the Move', 'domain' => 'eurogarages.com'],
        'EMO' => ['name' => 'Emo', 'domain' => 'emo.ie'],
        'ESSAR' => ['name' => 'Essar', 'domain' => 'essaroil.co.uk'],
        'ESSO' => ['name' => 'Esso', 'domain' => 'esso.co.uk'],
        'EURO GARAGES' => ['name' => 'EG Group', 'domain' => 'eurogarages.com'],
        'GLEANER' => ['name' => 'Gleaner', 'domain' => 'gleaner.co.uk'],
        'GO' => ['name' => 'Go', 'domain' => 'gofuels.co.uk'],
        'GULF' => ['name' => 'Gulf', 'domain' => 'gulfretail.co.uk'],
        'HARVEST ENERGY' => ['name' => 'Harvest Energy', 'domain' => 'harvestenergy.co.uk'],
        'HARVESTENERGY' => ['name' => 'Harvest Energy', 'domain' => 'harvestenergy.co.uk'],
        'HIGHLAND' => ['name' => 'Highland', 'domain' => 'highlandfuels.co.uk'],
        'JET' => ['name' => 'Jet', 'domain' => 'jetlocal.co.uk'],
        'MACE' => ['name' => 'Mace', 'domain' => 'mace.ie'],
        'MAXOL' => ['name' => 'Maxol', 'domain' => 'maxol.ie'],
        'MFG' => ['name' => 'MFG', 'domain' => 'motorfuelgroup.com'],
        'MORRISONS' => ['name' => 'Morrisons', 'domain' => 'morrisons.com'],
        'MOTO' => ['name' => 'Moto', 'domain' => 'moto-way.com'],
        'MOTOR FUEL GROUP' => ['name' => 'MFG', 'domain' => 'motorfuelgroup.com'],
        'MURCO' => ['name' => 'Murco', 'domain' => 'murco.co.uk'],
        'NICHOLLS' => ['name' => 'Nicholls', 'domain' => null],
        'NISA' => ['name' => 'Nisa', 'domain' => 'nisalocally.co.uk'],
        'OILFAST' => ['name' => 'Oilfast', 'domain' => 'oilfast.co.uk'],
        'PACE' => ['name' => 'Pace', 'domain' => 'pacefuelcare.co.uk'],
        'PRAX' => ['name' => 'Prax', 'domain' => 'prax.com'],
        'ROADCHEF' => ['name' => 'Roadchef', 'domain' => 'roadchef.com'],
        'RONTEC' => ['name' => 'Rontec', 'domain' => 'rontec-servicestations.co.uk'],
        "SAINSBURY'S" => ['name' => "Sainsbury's", 'domain' => 'sainsburys.co.uk'],
        'SAINSBURYS' => ['name' => "Sainsbury's", 'domain' => 'sainsburys.co.uk'],
        'SHELL' => ['name' => 'Shell', 'domain' => 'shell.com'],
        'SOLO' => ['name' => 'Solo', 'domain' => 'solooil.co.uk'],
        'SPAR' => ['name' => 'Spar', 'domain' => 'spar.co.uk'],
        'STAR' => ['name' => 'Star', 'domain' => null],
        'TESCO' => ['name' => 'Tesco', 'domain' => 'tesco.com'],
        'TEXACO' => ['name' => 'Texaco', 'domain' => 'texaco.com'],
        'TOTAL' => ['name' => 'Total', 'domain' => 'totalenergies.uk'],
        'TOTALENERGIES' => ['name' => 'TotalEnergies', 'domain' => 'totalenergies.uk'],
        'VALERO' => ['name' => 'Valero', 'domain' => 'valero.com'],
        'WAITROSE' => ['name' => 'Waitrose', 'domain' => 'waitrose.com'],
        'WELCOME BREAK' => ['name' => 'Welcome Break', 'domain' => 'welcomebreak.co.uk'],
        'WELCOME-BREAK' => ['name' => 'Welcome Break', 'domain' => 'welcomebreak.co.uk'],
    ];

    /**
     * Canonical display name for a brand, title-cased when it is not a known
     * brand so independents still read properly.
     */
    public static function name(?string $brand): ?string
    {
        if ($brand === null || trim($brand) === '') {
            return null;
        }

        return self::lookup($brand)['name'] ?? StationNormaliser::name($brand);
    }

    /**
     * The web domain logo.dev is keyed by, or null for an unlisted brand.
     */
    public static function domain(string $brand): ?string
    {
        return self::lookup($brand)['domain'] ?? null;
    }

    /**
     * @return array{name: string, domain: string|null}|null
     */
    private static function lookup(string $brand): ?array
    {
        return self::BRANDS[mb_strtoupper(trim($brand))] ?? null;
    }
}
