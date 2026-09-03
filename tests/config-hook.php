<?php namespace johnbarrett\Google_Address_Autocomplete;

/**
 * Checks the config-dialog hook's array walk against the real config.json.
 *
 * The hook itself is unreachable here — it short-circuits on class_exists('\REDCap') — so
 * this exercises withoutEventSetting(), the walk behind it. The shape it consumes is the
 * framework's: a list of setting definitions with sub_settings nested under the parent.
 */

require_once __DIR__ . '/stub/AbstractExternalModule.php';
require_once __DIR__ . '/../Google_Address_Autocomplete.php';

$failures = 0;

function gaa_check(string $label, bool $passed, string $detail = ''): void {
	global $failures;
	if ($passed) {
		echo "ok        $label\n";
		return;
	}
	$failures++;
	echo "FAIL      $label" . ($detail !== '' ? " — $detail" : '') . "\n";
}

$config = json_decode(file_get_contents(__DIR__ . '/../config.json'), true);
if (!is_array($config)) {
	fwrite(STDERR, "config.json did not parse\n");
	exit(2);
}

$settings = $config['project-settings'];
$module   = new Google_Address_Autocomplete();

$keysOf = static function (array $settings): array {
	foreach ($settings as $setting) {
		if (($setting['key'] ?? '') === 'address-set') {
			return array_column($setting['sub_settings'] ?? [], 'key');
		}
	}
	return [];
};

$before = $keysOf($settings);
gaa_check('config.json declares set-event', in_array('set-event', $before, true));

$after = $keysOf($module->withoutEventSetting($settings));
gaa_check('set-event is removed', !in_array('set-event', $after, true));
gaa_check('exactly one setting is removed', count($after) === count($before) - 1,
	count($before) . ' -> ' . count($after));
gaa_check('every other sub-setting survives, in order',
	$after === array_values(array_diff($before, ['set-event'])));
gaa_check('sub_settings stay contiguously indexed',
	$after === array_values($after) && array_keys($after) === range(0, count($after) - 1));

// Called twice, as it would be if a dialog re-rendered: must not remove anything more.
$twice = $keysOf($module->withoutEventSetting($module->withoutEventSetting($settings)));
gaa_check('idempotent', $twice === $after);

// A project configured before this setting existed, or any other module's block.
$foreign = [['key' => 'something-else', 'sub_settings' => [['key' => 'set-event']]]];
gaa_check('leaves another block\'s set-event alone',
	$module->withoutEventSetting($foreign) === $foreign);
gaa_check('tolerates a config with no address-set block',
	$module->withoutEventSetting([['key' => 'google-api-key']]) === [['key' => 'google-api-key']]);
gaa_check('tolerates an address-set with no sub_settings',
	$module->withoutEventSetting([['key' => 'address-set']]) === [['key' => 'address-set']]);

echo $failures ? "\nFAILED: $failures check(s).\n" : "\nPASS: all checks.\n";
exit($failures ? 1 : 0);
