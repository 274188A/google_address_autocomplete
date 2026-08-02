/**
 * Extract a JavaScript function from the module's PHP source, by name.
 *
 * The module echoes its JavaScript from inside a PHP heredoc-ish template, so
 * there is no .js file to import. Copying the function into the test file
 * instead would let the copy drift from the source silently — the tests would
 * keep passing while testing code that is no longer shipped. So the source of
 * truth stays Google_Address_Autocomplete.php, and the functions are lifted out
 * of it at test time.
 *
 * Only works for functions that are pure JavaScript: no PHP interpolation, no
 * DOM, no jQuery. recoverUnitFromText(), extractUnitParts() and escapeRegExp()
 * qualify. Anything with a <?php ?> block inside its body is rejected
 * deliberately — better a loud failure than a test that silently exercises
 * mangled code.
 */

import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const MODULE_PATH = join(dirname(fileURLToPath(import.meta.url)), '..', 'Google_Address_Autocomplete.php');

/**
 * Return the full text of `function <name>(...) { ... }`, brace-matched.
 *
 * Brace counting is naive about braces inside string and regex literals.
 * recoverUnitFromText() contains regex literals with {1,5} quantifiers, whose
 * braces are balanced, so the count survives them. extractBody() asserts the
 * result parses, which is what actually catches a bad extraction.
 */
function extractFunction(source, name) {
	const start = source.indexOf('function ' + name + '(');
	if (start === -1) {
		throw new Error(
			`Could not find "function ${name}(" in Google_Address_Autocomplete.php. ` +
			`If the function was renamed or removed, update tests/unit.test.mjs to match.`
		);
	}

	const open = source.indexOf('{', start);
	if (open === -1) { throw new Error(`No opening brace for ${name}().`); }

	let depth = 0;
	for (let i = open; i < source.length; i++) {
		const ch = source[i];
		if (ch === '{') { depth++; }
		else if (ch === '}') {
			depth--;
			if (depth === 0) {
				return source.slice(start, i + 1);
			}
		}
	}
	throw new Error(`Unbalanced braces while extracting ${name}().`);
}

/**
 * Extract the named functions and evaluate them in a bare context, returning
 * the resulting callables.
 *
 * Evaluated in THIS realm, via indirect eval, rather than in a vm context.
 * A vm context is a separate realm with its own Object.prototype, so objects
 * built inside it (extractUnitParts() returns one) are not reference-equal to
 * host-realm objects and assert.deepEqual rejects them as "same structure but
 * not reference-equal" — a confusing failure that says nothing about the code
 * under test. Node still provides no `document` or jQuery, so a function that
 * reaches for one throws either way, which was the only property the vm
 * context was actually buying.
 */
export function loadFunctions(names) {
	const source = readFileSync(MODULE_PATH, 'utf8');
	const defs = names.map((n) => extractFunction(source, n));

	for (const [i, def] of defs.entries()) {
		if (def.includes('<?php') || def.includes('<?=')) {
			throw new Error(
				`${names[i]}() contains PHP interpolation and cannot be tested in isolation.`
			);
		}
	}

	// Indirect eval, so the definitions land in their own scope rather than
	// leaking into this module's.
	const indirectEval = eval;
	const script = '(function () {\n'
		+ defs.join('\n\n')
		+ '\nreturn { ' + names.join(', ') + ' };\n})()';
	try {
		return indirectEval(script);
	} catch (err) {
		throw new Error(`Extracted source did not parse: ${err.message}`);
	}
}
