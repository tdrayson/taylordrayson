import { defineSetting } from '../useSettings';
import { number } from '../lib/format';
import { kgToLbs } from '../lib/format';
import { metresToMiles, metresToKm, milesToKm } from '../lib/distance';

// Two reactive, localStorage-backed settings (Project A's factory). Module-level
// so every useFormat() consumer shares one source. Values are whitelisted; an
// out-of-range stored value falls back to the default.
const distanceUnitDef = defineSetting('distanceUnit', 'mi', ['mi', 'km']);
const weightUnitDef = defineSetting('weightUnit', 'kg', ['kg', 'lbs']);

/**
 * Reactive-aware display formatters. Each reads its setting's `.value` INSIDE
 * the function body, so calling it in a template/computed registers the setting
 * as a render dependency and the view updates live when the visitor toggles.
 */
export function useFormat() {
    // Distance from metres (activity distance, elevation-free routes, etc.).
    function distance(metres, precision = 0) {
        if (metres === null || metres === undefined) {
            return null;
        }
        const km = distanceUnitDef.value.value === 'km';
        const converted = km ? metresToKm(metres, precision) : metresToMiles(metres, precision);
        return `${number(converted, precision)} ${km ? 'km' : 'mi'}`;
    }

    // Same, but split so a caller can render the unit inside an <abbr> (StatGrid).
    function distanceParts(metres, precision = 0) {
        if (metres === null || metres === undefined) {
            return null;
        }
        const km = distanceUnitDef.value.value === 'km';
        const converted = km ? metresToKm(metres, precision) : metresToMiles(metres, precision);
        return { value: number(converted, precision), unit: km ? 'km' : 'mi' };
    }

    // Distance from a value already in MILES (e.g. flight route.distance).
    function distanceFromMiles(miles, precision = 0) {
        if (miles === null || miles === undefined) {
            return null;
        }
        const km = distanceUnitDef.value.value === 'km';
        const converted = km ? milesToKm(miles, precision) : miles;
        return `${number(converted, precision)} ${km ? 'km' : 'mi'}`;
    }

    // Weight from kilograms. 'auto' precision shows 1dp only when fractional,
    // matching the current weightLabel behaviour.
    function weight(kg, precision = 'auto') {
        if (kg === null || kg === undefined) {
            return null;
        }
        const lbs = weightUnitDef.value.value === 'lbs';
        const converted = lbs ? kgToLbs(kg) : Number(kg);
        const digits = precision === 'auto' ? (converted % 1 ? 1 : 0) : precision;
        return `${number(converted, digits)} ${lbs ? 'lbs' : 'kg'}`;
    }

    return {
        distance,
        distanceParts,
        distanceFromMiles,
        weight,
        distanceUnit: distanceUnitDef.value,
        setDistanceUnit: distanceUnitDef.set,
        weightUnit: weightUnitDef.value,
        setWeightUnit: weightUnitDef.set,
    };
}
