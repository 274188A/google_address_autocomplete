<?php namespace johnbarrett\Google_Address_Autocomplete;

/**
 * Golden-output harness.
 *
 * The module's entire job is to echo an inline <script>. So correctness under
 * refactoring is checkable directly: for identical settings input, the emitted
 * output must be byte-identical before and after.
 *
 *   php tests/golden.php capture   write tests/golden/<fixture>.html
 *   php tests/golden.php verify    re-render and diff against those files
 *
 * Exits non-zero on any mismatch, so it works as a pre-commit gate.
 *
 * This runs OUTSIDE REDCap against a stub base class, so it proves the emitted
 * markup is unchanged — not that the module works on a real REDCap server.
 */

require_once __DIR__ . '/stub/AbstractExternalModule.php';
require_once __DIR__ . '/fixtures.php';
require_once __DIR__ . '/../Google_Address_Autocomplete.php';

// No use statement for Google_Address_Autocomplete: this file now shares the
// module's namespace, so the class resolves without one.

const GOLDEN_DIR = __DIR__ . '/golden';

/**
 * Render one fixture and return what the module echoed, plus anything it logged.
 */
function gaa_render(array $fixture): string {
	$module = new Google_Address_Autocomplete();
	$module->projectSettings = $fixture['project'];
	$module->subSettings     = ['address-set' => $fixture['sets']];

	ob_start();
	try {
		// Entered through the REDCap hook rather than the internal method, so the
		// fixtures exercise the same entry point the framework uses.
		$module->redcap_data_entry_form(1, '1', $fixture['instrument'], 1, null, 1);
	} finally {
		$output = ob_get_clean();
	}

	// Log messages are part of the observable behaviour — a refactor that stops
	// warning about a duplicate destination field is a regression the emitted
	// markup alone would not reveal.
	$logs = $module->logMessages
		? "\n<!-- LOGS\n" . implode("\n", $module->logMessages) . "\n-->\n"
		: "\n<!-- LOGS: none -->\n";

	return $output . $logs;
}

$mode = $argv[1] ?? 'verify';
if (!in_array($mode, ['capture', 'verify'], true)) {
	fwrite(STDERR, "usage: php tests/golden.php [capture|verify]\n");
	exit(2);
}

if (!is_dir(GOLDEN_DIR) && !mkdir(GOLDEN_DIR, 0777, true) && !is_dir(GOLDEN_DIR)) {
	fwrite(STDERR, "Could not create " . GOLDEN_DIR . "\n");
	exit(2);
}

$fixtures = gaa_fixtures();
$failed   = 0;

foreach ($fixtures as $name => $fixture) {
	$path   = GOLDEN_DIR . '/' . $name . '.html';
	$actual = gaa_render($fixture);

	if ($mode === 'capture') {
		file_put_contents($path, $actual);
		printf("captured  %-34s %6d bytes\n", $name, strlen($actual));
		continue;
	}

	if (!is_file($path)) {
		printf("MISSING   %-34s (run: capture)\n", $name);
		$failed++;
		continue;
	}

	$expected = file_get_contents($path);
	if ($expected === $actual) {
		printf("ok        %s\n", $name);
		continue;
	}

	$failed++;
	printf("DIFF      %s\n", $name);

	// Write the actual output next to the golden so the mismatch can be diffed
	// with a real diff tool rather than eyeballed from this summary.
	file_put_contents($path . '.actual', $actual);

	// Trim the common prefix and suffix before reporting, so a single changed
	// line is reported as one line. Naive index-pairing would report every
	// following line as differing whenever a line is added or removed, and a
	// noisy diff is one you learn to wave through.
	$expectedLines = explode("\n", $expected);
	$actualLines   = explode("\n", $actual);

	$start = 0;
	$eEnd  = count($expectedLines) - 1;
	$aEnd  = count($actualLines) - 1;

	while ($start <= $eEnd && $start <= $aEnd && $expectedLines[$start] === $actualLines[$start]) {
		$start++;
	}
	while ($eEnd >= $start && $aEnd >= $start && $expectedLines[$eEnd] === $actualLines[$aEnd]) {
		$eEnd--;
		$aEnd--;
	}

	printf("  first change at line %d; %d line(s) expected vs %d actual\n",
		$start + 1, $eEnd - $start + 1, $aEnd - $start + 1);

	// The bootstrap loader is emitted as one ~800-char line, so "line 1 differs"
	// on its own is not actionable. Narrow to the differing characters and show
	// a window around them.
	$e = $expectedLines[$start] ?? '';
	$a = $actualLines[$start] ?? '';
	if (strlen($e) > 160 || strlen($a) > 160) {
		$col = 0;
		while ($col < strlen($e) && $col < strlen($a) && $e[$col] === $a[$col]) {
			$col++;
		}
		$from = max(0, $col - 40);
		printf("  first differing character at column %d:\n", $col + 1);
		printf("    - …%s…\n", substr($e, $from, 120));
		printf("    + …%s…\n", substr($a, $from, 120));
	} else {
		for ($i = $start; $i <= $eEnd && $i < $start + 5; $i++) {
			printf("    - %s\n", trim($expectedLines[$i]));
		}
		for ($i = $start; $i <= $aEnd && $i < $start + 5; $i++) {
			printf("    + %s\n", trim($actualLines[$i]));
		}
	}
	printf("  full output written to %s.actual\n", basename($path));
}

echo "\n";
if ($mode === 'capture') {
	printf("Captured %d golden file(s) into tests/golden/.\n", count($fixtures));
	exit(0);
}

if ($failed > 0) {
	printf("FAILED: %d of %d fixture(s) differ.\n", $failed, count($fixtures));
	exit(1);
}

printf("PASS: all %d fixture(s) byte-identical.\n", count($fixtures));
exit(0);
