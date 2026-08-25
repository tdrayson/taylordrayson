# Bundled data

## city-timezones.csv

`latitude,longitude,timezone`, one city per line, used by `App\Support\CityTimezones`
to resolve a coordinate to an IANA timezone.

Derived from the [GeoNames](https://www.geonames.org/) `cities15000` export, which
is licensed [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/).

Committed rather than downloaded, so a lookup can never fail and a deploy never
depends on geonames.org. Rebuild with `php artisan timezones:update-cities` and
commit the result; cities do not move, so this needs doing about once a year.
