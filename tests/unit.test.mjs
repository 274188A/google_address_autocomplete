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
 * today means recoverUnitFromText() and the escapeRegExp() it depends on.
 */

import test from 'node:test';
import assert from 'node:assert/strict';
import { loadFunctions } from './extract.mjs';

const { recoverUnitFromText, escapeRegExp } = loadFunctions([
	'escapeRegExp',
	'recoverUnitFromText',
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
