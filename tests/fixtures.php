<?php namespace johnbarrett\Google_Address_Autocomplete;

/**
 * Settings scenarios for the golden-output harness.
 *
 * Each fixture is: a name, the project-wide settings, the 'address-set'
 * sub-settings, and the instrument being rendered. The harness feeds these to
 * the module and captures what it echoes.
 *
 * A fully populated set, used as the base for scenarios that only vary one
 * thing. Sub-settings arrive from REDCap as one array per configured set.
 */
function gaa_full_set(): array {
	return [
		'set-description'   => 'Home address',
		'set-disabled'      => false,
		'set-form'          => ['demographics'],
		'set-autocomplete'  => 'addr_full',
		'set-street-number' => 'addr_number',
		'set-street'        => 'addr_street',
		'set-city'          => 'addr_city',
		'set-county'        => 'addr_county',
		'set-state'         => 'addr_state',
		'set-zip'           => 'addr_zip',
		'set-country'       => 'addr_country',
		'set-latitude'      => 'addr_lat',
		'set-longitude'     => 'addr_lng',
		'set-place-name'    => 'addr_place',
		'set-recover-unit'  => true,
		'set-region-codes'  => 'au,nz',
		'set-primary-types' => 'street_address,premise',
	];
}

function gaa_fixtures(): array {
	$full = gaa_full_set();

	// Lat/lng unmapped.
	$noLatLng = $full;
	$noLatLng['set-latitude']  = '';
	$noLatLng['set-longitude'] = '';

	// Place name unmapped — pairs with the componentForm filter check.
	$noPlaceName = $full;
	$noPlaceName['set-place-name'] = '';

	// Unit recovery off, and no prediction filters.
	$noUnitRecovery = $full;
	$noUnitRecovery['set-recover-unit']  = false;
	$noUnitRecovery['set-region-codes']  = '';
	$noUnitRecovery['set-primary-types'] = '';

	// THE DEFENSIVE-READ CASE. Only the required key is present; every optional
	// key is absent entirely, exactly as REDCap returns it when a key was added
	// to config.json after the project was configured. Must not warn or fatal.
	$sparse = [
		'set-autocomplete' => 'addr_full',
	];

	// Second set on the same instrument, writing to different fields — proves
	// the two IIFEs get distinct element-id prefixes.
	$second = [
		'set-description'   => 'GP practice address',
		'set-disabled'      => false,
		'set-form'          => ['demographics'],
		'set-autocomplete'  => 'gp_full',
		'set-street-number' => 'gp_number',
		'set-street'        => 'gp_street',
		'set-city'          => 'gp_city',
		'set-county'        => '',
		'set-state'         => 'gp_state',
		'set-zip'           => 'gp_zip',
		'set-country'       => 'gp_country',
		'set-latitude'      => '',
		'set-longitude'     => '',
		'set-place-name'    => 'gp_place',
		'set-recover-unit'  => false,
		'set-region-codes'  => 'au',
		'set-primary-types' => '',
	];

	$disabled = $full;
	$disabled['set-disabled'] = true;

	// Two sets claiming the same source field — the later one is skipped + logged.
	$duplicateSource = $full;
	$duplicateSource['set-description'] = 'Duplicate source';

	// Two sets writing to the same destination field — warns but both run.
	$duplicateDest = $second;
	$duplicateDest['set-autocomplete'] = 'gp_full';
	$duplicateDest['set-city']         = 'addr_city';   // collides with $full

	$baseProject = [
		'google-api-key'      => 'TEST-API-KEY-123',
		'import-google-api'   => true,
		'privacy-notice'      => '',
		'hide-privacy-notice' => false,
	];

	return [
		'single-set-full' => [
			'project'    => $baseProject,
			'sets'       => [$full],
			'instrument' => 'demographics',
		],
		'two-sets' => [
			'project'    => $baseProject,
			'sets'       => [$full, $second],
			'instrument' => 'demographics',
		],
		'no-unit-recovery-no-filters' => [
			'project'    => $baseProject,
			'sets'       => [$noUnitRecovery],
			'instrument' => 'demographics',
		],
		'no-latlng' => [
			'project'    => $baseProject,
			'sets'       => [$noLatLng],
			'instrument' => 'demographics',
		],
		'no-place-name' => [
			'project'    => $baseProject,
			'sets'       => [$noPlaceName],
			'instrument' => 'demographics',
		],
		'sparse-missing-keys' => [
			'project'    => $baseProject,
			'sets'       => [$sparse],
			'instrument' => 'demographics',
		],
		'privacy-notice-custom' => [
			'project'    => ['privacy-notice' => 'Custom notice for this study.'] + $baseProject,
			'sets'       => [$full],
			'instrument' => 'demographics',
		],
		'privacy-notice-hidden' => [
			'project'    => ['hide-privacy-notice' => true] + $baseProject,
			'sets'       => [$full],
			'instrument' => 'demographics',
		],
		'no-bootstrap-loader' => [
			'project'    => ['import-google-api' => false] + $baseProject,
			'sets'       => [$full],
			'instrument' => 'demographics',
		],
		'set-disabled' => [
			'project'    => $baseProject,
			'sets'       => [$disabled],
			'instrument' => 'demographics',
		],
		'duplicate-source-field' => [
			'project'    => $baseProject,
			'sets'       => [$full, $duplicateSource],
			'instrument' => 'demographics',
		],
		'duplicate-destination-field' => [
			'project'    => $baseProject,
			'sets'       => [$full, $duplicateDest],
			'instrument' => 'demographics',
		],
		'other-instrument-scoped-out' => [
			'project'    => $baseProject,
			'sets'       => [$full],
			'instrument' => 'some_other_form',
		],
		'blank-form-scope-any-instrument' => [
			'project'    => $baseProject,
			'sets'       => [['set-form' => []] + $full],
			'instrument' => 'any_form_at_all',
		],
		'no-api-key' => [
			'project'    => ['google-api-key' => ''] + $baseProject,
			'sets'       => [$full],
			'instrument' => 'demographics',
		],
	];
}
