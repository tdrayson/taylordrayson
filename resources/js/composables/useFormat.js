import { defineSetting } from '../useSettings';
import { duration as clockDuration, number } from '../lib/format';
import { kgToLbs } from '../lib/format';
import { metresToMiles, metresToKm, milesToKm, milesToMetres, kmToMetres, kmToMiles } from '../lib/distance';
import { sillyMeasure } from '../lib/sillyUnits';

// Two reactive, localStorage-backed settings (Project A's factory). Module-level
// so every useFormat() consumer shares one source. Values are whitelisted; an
// out-of-range stored value falls back to the default.
const distanceUnitDef = defineSetting('distanceUnit', 'mi', ['mi', 'km']);
const weightUnitDef = defineSetting('weightUnit', 'kg', ['kg', 'lbs']);
const temperatureUnitDef = defineSetting('temperatureUnit', 'c', ['c', 'f']);
const sillyUnitsDef = defineSetting('sillyUnits', 'off', ['off', 'on']);

// Stat units silly units can convert, as the kind of quantity and the factor to its base unit.
const STAT_UNITS = {
    kcal: { kind: 'energy' },
    'kcal/day': { kind: 'energy', suffix: ' a day' },
    bpm: { kind: 'heartRate' },
    m: { kind: 'height' },
    h: { kind: 'duration', scale: 3600 },
};

/**
 * Reactive-aware display formatters. Each reads its setting's `.value` INSIDE
 * the function body, so calling it in a template/computed registers the setting
 * as a render dependency and the view updates live when the visitor toggles.
 */
export function useFormat() {
    const silly = () => sillyUnitsDef.value.value === 'on';

    // Distance from metres in the visitor's real unit, whatever silly units says.
    function realDistance(metres, precision = 0) {
        const km = distanceUnitDef.value.value === 'km';
        const converted = km ? metresToKm(metres, precision) : metresToMiles(metres, precision);
        return { value: number(converted, precision), unit: km ? 'km' : 'mi' };
    }

    // Distance from metres (activity distance, elevation-free routes, etc.).
    function distance(metres, precision = 0) {
        const parts = distanceParts(metres, precision);
        return parts && `${parts.value} ${parts.unit}`;
    }

    // Same, but split so a caller can render the unit inside an <abbr> (StatGrid).
    // Under silly units `exact` carries the real distance for a title.
    function distanceParts(metres, precision = 0) {
        if (metres === null || metres === undefined) {
            return null;
        }
        if (silly()) {
            const real = realDistance(metres, precision);
            return { ...sillyMeasure('distance', metres), exact: `${real.value} ${real.unit}` };
        }
        return realDistance(metres, precision);
    }

    // Distance from a value already in MILES (e.g. flight route.distance).
    function distanceFromMiles(miles, precision = 0) {
        if (miles === null || miles === undefined) {
            return null;
        }
        return distance(milesToMetres(miles), precision);
    }

    // The real distance for a title attribute, only while silly units hide it.
    function exactDistance(metres, precision = 0) {
        return distanceParts(metres, precision)?.exact ?? null;
    }

    // exactDistance for a value already in miles.
    function exactDistanceFromMiles(miles, precision = 0) {
        return miles === null || miles === undefined ? null : exactDistance(milesToMetres(miles), precision);
    }

    // The visitor's current distance unit as a plain string ('mi' or 'km'), for
    // use as a suffix label. Reads the setting inside the function body so a
    // caller using it in a template/computed stays reactive to the toggle.
    function distanceUnitLabel() {
        return distanceUnitDef.value.value;
    }

    // Convert a display value (typed by the visitor, in their chosen unit) to a
    // field's storage unit, ready to send to the server. `store` is the schema's
    // 'store' key ('m' for metres, 'mi' for miles). Empty values pass through
    // unchanged so the caller can skip conversion for blank inputs.
    function toStorage(value, store) {
        if (value === '' || value === null || value === undefined) {
            return value;
        }

        const km = distanceUnitDef.value.value === 'km';

        if (store === 'm') {
            // Metres are stored as integers, so round after converting.
            return Math.round(km ? kmToMetres(Number(value)) : milesToMetres(Number(value)));
        }

        if (store === 'mi') {
            return km ? kmToMiles(Number(value)) : Number(value);
        }

        return value;
    }

    // The inverse of toStorage: convert a stored value back to the visitor's
    // display unit, for repopulating the query builder from a saved filter.
    function toDisplay(stored, store) {
        if (stored === '' || stored === null || stored === undefined) {
            return stored;
        }

        const km = distanceUnitDef.value.value === 'km';

        if (store === 'm') {
            return km ? metresToKm(Number(stored), 2) : metresToMiles(Number(stored), 2);
        }

        if (store === 'mi') {
            return km ? milesToKm(Number(stored), 2) : Number(stored);
        }

        return stored;
    }

    // Weight from kilograms in the visitor's real unit. 'auto' precision shows
    // 1dp only when fractional, matching the current weightLabel behaviour.
    function realWeight(kg, precision = 'auto') {
        const lbs = weightUnitDef.value.value === 'lbs';
        const converted = lbs ? kgToLbs(kg) : Number(kg);
        const digits = precision === 'auto' ? (converted % 1 ? 1 : 0) : precision;
        return `${number(converted, digits)} ${lbs ? 'lbs' : 'kg'}`;
    }

    // Weight from kilograms, in daft units when silly units is on.
    function weight(kg, precision = 'auto') {
        if (kg === null || kg === undefined) {
            return null;
        }
        if (silly()) {
            const parts = sillyMeasure('weight', kg);
            return `${parts.value} ${parts.unit}`;
        }
        return realWeight(kg, precision);
    }

    // The real weight for a title attribute, only while silly units hide it.
    function exactWeight(kg, precision = 'auto') {
        return kg === null || kg === undefined || !silly() ? null : realWeight(kg, precision);
    }

    // A quantity with no unit setting of its own (height, duration, energy,
    // heart rate): daft units when silly units is on, otherwise `real`, the
    // caller's usual display of it.
    function measure(kind, amount, real) {
        if (amount === null || amount === undefined) {
            return null;
        }
        if (!silly()) {
            return real;
        }
        const parts = sillyMeasure(kind, amount);
        return `${parts.value} ${parts.unit}`;
    }

    // `real` for a title attribute, only while silly units hide it.
    function exactMeasure(kind, amount, real) {
        return amount === null || amount === undefined || !silly() ? null : real;
    }

    // A duration, in daft units or as "1h 05m".
    function duration(seconds) {
        return measure('duration', seconds, clockDuration(seconds));
    }

    // A stat's value and unit, swapped for daft units when silly units is on and
    // the unit is one we can convert. `exact` carries the original for a title.
    function statParts(value, unit) {
        const known = STAT_UNITS[unit];
        const amount = Number(String(value ?? '').replace(/,/g, ''));

        if (!silly() || !known || value === null || value === '' || Number.isNaN(amount)) {
            return { value, unit };
        }

        const parts = sillyMeasure(known.kind, amount * (known.scale ?? 1));
        return { value: parts.value, unit: parts.unit + (known.suffix ?? ''), exact: `${value} ${unit}` };
    }

    // A stat held as raw seconds, as statParts' parts while silly units is on, else null.
    function durationParts(seconds) {
        if (seconds === null || seconds === undefined || !silly()) {
            return null;
        }
        return { ...sillyMeasure('duration', seconds), exact: clockDuration(seconds) };
    }

    // Whole degrees in the visitor's unit, from Celsius.
    function degrees(celsius) {
        if (celsius === null || celsius === undefined) {
            return null;
        }
        const fahrenheit = temperatureUnitDef.value.value === 'f';
        return Math.round(fahrenheit ? (celsius * 9) / 5 + 32 : Number(celsius));
    }

    // Temperature from Celsius with its unit, e.g. "18°C" or "64°F".
    function temperature(celsius) {
        const value = degrees(celsius);
        return value === null ? null : `${value}°${temperatureUnitDef.value.value.toUpperCase()}`;
    }

    return {
        distance,
        distanceParts,
        distanceFromMiles,
        distanceUnitLabel,
        exactDistance,
        exactDistanceFromMiles,
        exactWeight,
        toStorage,
        toDisplay,
        weight,
        measure,
        exactMeasure,
        duration,
        statParts,
        durationParts,
        degrees,
        temperature,
        distanceUnit: distanceUnitDef.value,
        setDistanceUnit: distanceUnitDef.set,
        weightUnit: weightUnitDef.value,
        setWeightUnit: weightUnitDef.set,
        temperatureUnit: temperatureUnitDef.value,
        setTemperatureUnit: temperatureUnitDef.set,
        sillyUnits: sillyUnitsDef.value,
        setSillyUnits: sillyUnitsDef.set,
    };
}
