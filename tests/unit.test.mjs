/**
 * Unit tests for the module's pure JavaScript helpers.
 *
 *   node --test tests/
 *
 * These are CHARACTERISTIC tests: they pin what the shipped code does today,
 * so a refactor that changes behaviour fails here rather than in a REDCap
 * project. They are not a specification — where a case below looks arguably
 * wrong, it is recorded as current behaviour and noted, not quietly corrected.
 *
 * The functions are lifted out of Google_Address_Autocomplete.php at run time
 * (see extract.mjs) rather than copied, so they cannot drift from the source.
 * Scope is limited to helpers that touch neither the DOM nor jQuery, which
 * today means recoverUnitFromText(), extractUnitParts(), and escapeRegExp().
 */

import test from 'node:test';
import assert from 'node:assert/strict';
import { loadFunctions } from './extract.mjs';

const { recoverUnitFromText, escapeRegExp, extractUnitParts } = loadFunctions([
	'escapeRegExp',
	'recoverUnitFromText',
	'extractUnitParts',
]);

/** Shorthand: assert recoverUnitFromText(typed, streetNumber) === expected. */
function unit(typed, streetNumber, expected, message) {
	assert.equal(recoverUnitFromText(typed, streetNumber), expected, message);
}

test('recovers the unit from AU/UK slash notation', () => {
	unit('3/27 Harris St', '27', '3');
	unit('3/27', '27', '3', 'street name is not required');
	unit('3 / 27 Harris St', '27', '3', 'spaces around the slash');
	unit('12b/27 Harris St', '27', '12B', 'alpha suffix is upper-cased');
});

test('recovers the unit when introduced by a unit word', () => {
	unit('Unit 3, 27 Harris St', '27', '3');
	unit('Unit 3 27 Harris St', '27', '3', 'comma is optional');
	unit('Flat 3/27 Harris St', '27', '3');
	unit('Apartment 12B, 27 Harris St', '27', '12B');
	unit('apt 5 27 Harris St', '27', '5', 'abbreviation, lower case');
	unit('Level 2, 27 Harris St', '27', '2');
	unit('Suite 100, 27 Harris St', '27', '100');
	unit('Shop 4, 1A Smith Rd', '1A', '4', 'alphanumeric street number');
	unit('Lot 7, 27 Harris St', '27', '7');
});

test('rejects text that does not clearly contain a unit', () => {
	unit('27 Harris St', '27', '', 'nothing precedes the street number');
	unit('Harris St 27', '27', '', 'trailing street number is not a unit prefix');
	unit('The Old Rectory, 27 Harris St', '27', '', 'a building name is not a unit');
});

test('treats a street number range as a range, not a unit', () => {
	// The guard that matters is the one on the hyphen: with streetNumber '29'
	// the prefix is "27-29 ", which would otherwise parse 27 as the unit.
	unit('Shop 2, 27-29 Harris St', '29', '', 'hyphen before the street number');
	unit('27–29 Harris St', '29', '', 'en dash');
	// With streetNumber '27' the function short-circuits earlier instead:
	// nothing precedes the number at all.
	unit('27-29 Harris St', '27', '');
});

test('matches the street number on a word boundary', () => {
	// '7' must not match inside '27', so there is no prefix to parse and the
	// unit is not recovered. Pinned because it is non-obvious: the typed text
	// plainly contains a unit, and the function still declines.
	unit('7/27 Harris St', '7', '', "'7' does not match inside '27'");
	unit('3/27 Harris St', '2', '', "'2' does not match inside '27'");
});

test('caps the unit prefix length so a building name is not mistaken for a unit', () => {
	// The guard admits a prefix of 24 characters and rejects 25. The prefix is
	// everything before the street number, so it includes the "3/" unit and the
	// separating space: 21 pad characters + " 3/" is exactly 24.
	const prefix24 = 'X'.repeat(21) + ' 3/';
	const prefix25 = 'X'.repeat(22) + ' 3/';
	assert.equal(prefix24.length, 24, 'guard the fixture itself');
	assert.equal(prefix25.length, 25, 'guard the fixture itself');
	unit(prefix24 + '27 Harris St', '27', '3', '24-character prefix is accepted');
	unit(prefix25 + '27 Harris St', '27', '', '25-character prefix is rejected');
});

test('caps the unit itself at five digits', () => {
	unit('Unit 12345/27 X', '27', '12345');
	unit('Unit 123456/27 X', '27', '', 'six digits is not a unit');
});

test('returns empty for missing or blank arguments', () => {
	unit('', '27', '');
	unit('3/27 Harris St', '', '');
	unit(null, '27', '');
	unit(undefined, '27', '');
	unit('3/27 Harris St', null, '');
	unit('3/27 Harris St', undefined, '');
	unit('   ', '27', '', 'whitespace-only text');
});

test('accepts a non-string street number', () => {
	// Google returns component values as strings, but the function coerces
	// rather than relying on that.
	unit('3/27 Harris St', 27, '3');
});

test('escapeRegExp neutralises a street number containing metacharacters', () => {
	// A street number is not supposed to contain these, but the escaping is
	// what stops a stray one becoming a live pattern.
	assert.equal(escapeRegExp('2.'), '2\\.');
	assert.equal(escapeRegExp('2+'), '2\\+');
	assert.equal(escapeRegExp('a(b)'), 'a\\(b\\)');
	// '.' is escaped, so it does not match the '7' in '27'.
	unit('Unit 3, 27 Harris St', '2.', '');
});

/* ------------------------------------------------------------------ *
 * extractUnitParts()
 *
 * Reads the unit and street number straight off the Places API component
 * list. The NEW API names the value properties shortText / longText, so a
 * fixture here doubles as a record of the shape the module expects back.
 * ------------------------------------------------------------------ */

/** Shorthand for a component: comp('subpremise', '3'). */
function comp(type, shortText, extra) {
	return Object.assign({ types: [type], shortText: shortText }, extra || {});
}

test('extractUnitParts reads the unit and street number off the components', () => {
	assert.deepEqual(
		extractUnitParts([comp('subpremise', '3'), comp('street_number', '27')]),
		{ unit: '3', streetNumber: '27' }
	);
	assert.deepEqual(
		extractUnitParts([comp('street_number', '27'), comp('subpremise', '3')]),
		{ unit: '3', streetNumber: '27' },
		'component order does not matter'
	);
});

test('extractUnitParts prefers shortText and falls back to longText', () => {
	assert.deepEqual(
		extractUnitParts([{ types: ['subpremise'], longText: '3' }]),
		{ unit: '3', streetNumber: '' },
		'longText is used when shortText is absent'
	);
	assert.deepEqual(
		extractUnitParts([{ types: ['subpremise'], shortText: 'S', longText: 'L' }]),
		{ unit: 'S', streetNumber: '' },
		'shortText wins when both are present'
	);
	assert.deepEqual(
		extractUnitParts([{ types: ['subpremise'], shortText: '', longText: '7' }]),
		{ unit: '7', streetNumber: '' },
		'an empty shortText falls through to longText'
	);
});

test('extractUnitParts keeps the first of a repeated component', () => {
	assert.deepEqual(
		extractUnitParts([comp('subpremise', '3'), comp('subpremise', '9')]),
		{ unit: '3', streetNumber: '' }
	);
	assert.deepEqual(
		extractUnitParts([comp('street_number', '27'), comp('street_number', '29')]),
		{ unit: '', streetNumber: '27' }
	);
});

test('extractUnitParts trims and coerces the value', () => {
	assert.deepEqual(
		extractUnitParts([comp('subpremise', '  3  ')]),
		{ unit: '3', streetNumber: '' }
	);
	assert.deepEqual(
		extractUnitParts([comp('street_number', 27)]),
		{ unit: '', streetNumber: '27' },
		'a non-string value is coerced rather than assumed'
	);
});

test('extractUnitParts only looks at types[0]', () => {
	// PINNED, AND ARGUABLY WRONG. The component type is read as types[0]
	// rather than searched for across the array, so a subpremise listed
	// second is missed entirely and unit recovery silently does not happen.
	// Both call sites in the module do this (see also the componentForm loop
	// in fillInAddress), so it is the module's convention rather than a local
	// slip — which is why it is recorded here rather than quietly changed.
	// If a real Google response is ever seen with the type in a later slot,
	// this test is the thing that should change, deliberately.
	assert.deepEqual(
		extractUnitParts([{ types: ['premise', 'subpremise'], shortText: '3' }]),
		{ unit: '', streetNumber: '' }
	);
});

test('extractUnitParts returns blanks for absent or malformed input', () => {
	const blank = { unit: '', streetNumber: '' };
	assert.deepEqual(extractUnitParts([]), blank, 'empty list');
	assert.deepEqual(extractUnitParts(null), blank, 'null');
	assert.deepEqual(extractUnitParts(undefined), blank, 'undefined');
	assert.deepEqual(extractUnitParts([comp('route', 'Harris St')]), blank, 'no relevant types');
	assert.deepEqual(extractUnitParts([{ types: [], shortText: 'x' }]), blank, 'empty types array');
});

test('extractUnitParts skips a malformed entry without losing the rest', () => {
	// The guard that matters: a null or types-less component must not abort
	// the scan, or one bad entry costs the unit recovery for the whole place.
	const expected = { unit: '', streetNumber: '27' };
	assert.deepEqual(extractUnitParts([null, comp('street_number', '27')]), expected);
	assert.deepEqual(extractUnitParts([{ shortText: 'x' }, comp('street_number', '27')]), expected);
	assert.deepEqual(
		extractUnitParts([undefined, comp('subpremise', '3'), comp('street_number', '27')]),
		{ unit: '3', streetNumber: '27' }
	);
});

test('extractUnitParts feeds recoverUnitFromText its street number', () => {
	// The two are used together in applyUnitFromComponents(): the street
	// number found here is what anchors the parse of the typed text when
	// Google omits the subpremise. This pins the seam between them.
	const parts = extractUnitParts([comp('street_number', '27'), comp('route', 'Harris St')]);
	assert.equal(parts.unit, '', 'Google omitted the subpremise');
	assert.equal(recoverUnitFromText('3/27 Harris St', parts.streetNumber), '3');
});
