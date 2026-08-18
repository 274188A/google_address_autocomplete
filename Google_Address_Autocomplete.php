<?php namespace johnbarrett\Google_Address_Autocomplete;

use ExternalModules\AbstractExternalModule;

// REDCap derives the main class file name from the namespace; whether it also
// autoloads sibling classes is undocumented, so they are required explicitly.
require_once __DIR__ . '/AddressComponent.php';
require_once __DIR__ . '/AddressFieldSet.php';

class Google_Address_Autocomplete extends AbstractExternalModule
{
	// JSON_HEX_TAG is the one that matters: it stops a "</script>" sequence inside a
	// setting value from closing the inline script early.
	private const JSON_FLAGS = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

	// Shown by default. What the participant types is relayed to Google, which has to be
	// disclosed on the form; an administrator can reword or suppress it.
	private const DEFAULT_PRIVACY_NOTICE = 'Address suggestions come from Google. What you type in this box is sent to Google Maps to generate them.';

	// Framework 12+ runs redcap_* methods automatically, so config.json carries no
	// "permissions" block. The legacy hook_* names do not fire.
	public function redcap_survey_page($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance) {
		$this->addAddressAutoCompletion($project_id, $instrument);
	}

	public function redcap_data_entry_form($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance) {
		$this->addAddressAutoCompletion($project_id, $instrument);
	}

	/** The loader and styles are emitted once; each set then gets its own IIFE. */
	private function addAddressAutoCompletion($project_id, $instrument): void {
		$key = $this->getProjectSetting('google-api-key', $project_id);
		if (!$key) { return; }

		$sets = $this->getActiveSets($project_id, $instrument);
		if (!$sets) { return; }

		if ($this->getProjectSetting('import-google-api', $project_id)) {
			$this->emitBootstrapLoader($key);
		}

		$this->emitStyles();

		$privacyNoticeText = $this->resolvePrivacyNotice($project_id);
		foreach ($sets as $set) {
			$this->emitSetScript($set, $privacyNoticeText);
		}
	}

	/**
	 * The sets that should run on this instrument. A misconfigured set is skipped and
	 * logged, never fatal — one bad set must not cost the participant the others.
	 *
	 * @return AddressFieldSet[]
	 */
	private function getActiveSets($project_id, $instrument): array {
		$sets = $this->getSubSettings('address-set', $project_id);
		if (!is_array($sets)) { return []; }

		$active              = [];
		$claimedSources      = [];
		$claimedDestinations = [];

		foreach ($sets as $index => $raw) {
			if (!is_array($raw)) { continue; }

			// The configured position, not the position among active sets: a set's element
			// ids must not shift when a different set qualifies on a page.
			$set = AddressFieldSet::fromSubSetting($raw, $index);

			if (!$set->isActive()) { continue; }

			// Blank scope means "any form containing the source field", which the emitted
			// IIFE still guards client-side.
			if (!$set->appliesTo((string)$instrument)) { continue; }

			// Two sets cannot share a source field: both would wrap the same input.
			$source = $set->sourceKey();
			if (isset($claimedSources[$source])) {
				$this->log(sprintf(
					'Address Autocomplete: skipped set #%d on instrument "%s" because its '
						. 'Autocomplete Field "%s" is already used by set #%d.',
					$index + 1, $instrument, $source, $claimedSources[$source] + 1
				));
				continue;
			}
			$claimedSources[$source] = $index;

			// A shared destination field is bad configuration but not fatal: the last write
			// wins. Warn rather than drop a set the administrator can see is configured.
			foreach ($this->destinationFieldNames($set) as $fieldName) {
				if (isset($claimedDestinations[$fieldName])) {
					$this->log(sprintf(
						'Address Autocomplete: set #%d and set #%d both write to field "%s" on '
							. 'instrument "%s". One of them will overwrite the other.',
						$index + 1, $claimedDestinations[$fieldName] + 1, $fieldName, $instrument
					));
				} else {
					$claimedDestinations[$fieldName] = $index;
				}
			}

			$active[] = $set;
		}

		return $active;
	}

	/** @return array<string,string> Google component type => field name, excluding lat/lng. */
	private function destinationFields(AddressFieldSet $set): array {
		$fields = [];
		foreach (AddressComponent::cases() as $component) {
			$fieldName = $set->{$component->property()};
			if ($fieldName !== '') { $fields[$component->value] = $fieldName; }
		}
		return $fields;
	}

	/** @return string[] Every field this set writes to, to detect two sets sharing one. */
	private function destinationFieldNames(AddressFieldSet $set): array {
		$names      = [];
		$properties = array_map(
			static fn(AddressComponent $component): string => $component->property(),
			AddressComponent::cases()
		);
		$properties = array_merge($properties, ['latitude', 'longitude']);

		foreach ($properties as $property) {
			$fieldName = $set->$property;
			if ($fieldName !== '') { $names[$fieldName] = true; }
		}
		return array_keys($names);
	}

	/** The disclosure shown under every widget. Empty means "emit no notice". */
	private function resolvePrivacyNotice($project_id): string {
		if ($this->getProjectSetting('hide-privacy-notice', $project_id)) { return ''; }
		$custom = trim((string)$this->getProjectSetting('privacy-notice', $project_id));
		return ($custom !== '') ? $custom : self::DEFAULT_PRIVACY_NOTICE;
	}

	// Every setting emitted into JS goes through jsValue/jsArray/jsObject. None of them
	// may return an empty string: "var x = ;" is a syntax error that would kill the whole
	// inline script, so a json_encode() failure falls back to "", [] or {}.

	private function jsValue($value): string {
		$json = json_encode((string)$value, self::JSON_FLAGS);
		return ($json === false) ? '""' : $json;
	}

	private function jsArray($csv): string {
		$parts = array_map(trim(...), explode(',', (string)$csv));
		$parts = array_values(array_filter($parts, static fn(string $part): bool => $part !== ''));
		$json  = json_encode($parts, self::JSON_FLAGS);
		return ($json === false) ? '[]' : $json;
	}

	private function jsObject($map): string {
		$json = json_encode((object)$map, self::JSON_FLAGS);
		return ($json === false) ? '{}' : $json;
	}

	/** Google's inline bootstrap: defines importLibrary now, loads on first use. Once per page. */
	private function emitBootstrapLoader($key): void {
		// json_encode, not htmlspecialchars: HTML entities are not decoded inside <script>,
		// so an html-escaped key would reach Google corrupted.
		$keyJs = $this->jsValue($key);

		// Nowdoc, so PHP does not interpolate JS template literals like ${c}. A nowdoc
		// cannot carry the key, so it is passed as an IIFE argument — nothing this module
		// emits may touch global scope, where it could collide with another module.
		echo '<script>(function(__addressAutoKey){';
		echo <<<'SCRIPT'
(g=>{var h,a,k,p="The Google Maps JavaScript API",c="google",l="importLibrary",q="__ib__",m=document,b=window;b=b[c]||(b[c]={});var d=b.maps||(b.maps={}),r=new Set,e=new URLSearchParams,u=()=>h||(h=new Promise(async(f,n)=>{await (a=m.createElement("script"));e.set("libraries",[...r]+"");for(k in g)e.set(k.replace(/[A-Z]/g,t=>"_"+t[0].toLowerCase()),g[k]);e.set("callback",c+".maps."+q);a.src=`https://maps.${c}apis.com/maps/api/js?`+e;d[q]=f;a.onerror=()=>h=n(Error(p+" could not load."));a.nonce=m.querySelector("script[nonce]")?.nonce||"";m.head.append(a)}));d[l]?console.warn(p+" only loads once. Ignoring:",g):d[l]=(f,...n)=>r.add(f)&&u().then(()=>d[l](f,...n))})({key:__addressAutoKey,v:"weekly"});
SCRIPT;
		echo '})(' . $keyJs . ');</script>';
	}

	/** Keyed on a class never an id, because a page can carry several wrappers. */
	private function emitStyles(): void {
		?>
		<style>
			.gaa-location-field { position: relative; }
			.gaa-location-field gmp-place-autocomplete {
				width: 100%;
				font-size: 13px;
			}
			.gaa-location-field .gaa-privacy-notice {
				font-size: 11px;
				line-height: 1.4;
				color: #666;
				margin-top: 3px;
			}
		</style>
		<?php
	}

	/**
	 * One self-contained IIFE per set. Settings are baked in at emit time, so an
	 * unconfigured feature emits no code at all, and every DOM id is built from
	 * autocompletePrefix — which is what keeps two sets on one page apart.
	 */
	private function emitSetScript(AddressFieldSet $set, string $privacyNoticeText): void {
		$destinationFields = $this->destinationFields($set);
		?>
		<script>
		(function() {
			// Unique per set. Every element id is built from it, which is what keeps two
			// sets on one page out of each other's fields.
			var autocompletePrefix = <?php echo $this->jsValue($set->elementPrefix()); ?>;
			var autocompleteFieldName = <?php echo $this->jsValue($set->autocomplete); ?>;

			var logPrefix = '[Address Autocomplete ' + <?php echo $this->jsValue($set->label()); ?> + '] ';

			// One JSON object rather than a selector per field, so nothing lands unescaped.
			var destinationFields = <?php echo $this->jsObject($destinationFields); ?>;
			var latitudeFieldName  = <?php echo $this->jsValue($set->latitude); ?>;
			var longitudeFieldName = <?php echo $this->jsValue($set->longitude); ?>;

			// Empty when the administrator has suppressed the disclosure.
			var privacyNoticeText = <?php echo $this->jsValue($privacyNoticeText); ?>;

			// The escape matters: an unescaped quote would end the attribute selector and
			// throw, taking the whole IIFE with it.
			function byName(name) {
				if (!name) { return $(); }
				return $('[name="' + String(name).replace(/["\\]/g, '\\$&') + '"]');
			}

			// Iterates destinationFields, not componentForm: that has no place_name entry
			// and would leave the field stuck disabled.
			function setDestinationFieldsDisabled(disabled) {
				$.each(destinationFields, function(componentType, fieldName) {
					byName(fieldName).prop('disabled', disabled);
				});
				// Unmapped lat/lng are '', and byName('') is an empty set — a no-op.
				byName(latitudeFieldName).prop('disabled', disabled);
				byName(longitudeFieldName).prop('disabled', disabled);
			}

			// Raw typed text, kept so recoverUnitFromText() can recover a unit Google omitted.
			var lastTypedText = '';

			// Whether the address field holds an address this session's autocomplete wrote,
			// rather than one saved against the record earlier. The degrade path needs the
			// distinction.
			var fieldHoldsSelectedAddress = false;

			// Google component type -> the Place API property to read off it.
			//
			// Do not add subpremise. This doubles as the registry of components that have a
			// destination field, and no googleSearch_*subpremise element is ever created, so
			// an entry would only log "Could not find the element". extractUnitParts()
			// captures the unit instead.
			var componentForm = {
<?php foreach (AddressComponent::addressComponents() as $component): ?>
				<?php echo ($set->{$component->property()} !== '' ? $component->value . ": '" . $component->format() . "'," : ""); ?>
<?php endforeach; ?>
			};

			$(document).ready(function() {
				// Fallback for a set with no instrument scope configured server-side.
				var $autocompleteField = byName(autocompleteFieldName);
				if ($autocompleteField.length === 0) {
					return;
				}

				// Assign the ids updateValue() looks these up by. Lat/lng get none: they are
				// found by name instead.
				$.each(destinationFields, function(componentType, fieldName) {
					byName(fieldName).attr('id', autocompletePrefix + componentType);
				});

				// Disabled until a prediction is chosen or autocomplete fails to load.
				setDestinationFieldsDisabled(true);

				// The wrapper is found by class, not id: several sets can share a page.
				$autocompleteField.wrap('<div class="gaa-location-field"></div>');
				$autocompleteField.hide();

				initAutocomplete($autocompleteField);
			});

			// Resolves immediately when this module emitted the loader. The polling is for
			// the case where another module supplies the API late.
			function waitForImportLibrary(timeoutMs) {
				timeoutMs = timeoutMs || 15000;
				return new Promise(function(resolve, reject) {
					function ready() {
						return typeof google !== 'undefined' && google.maps &&
						       typeof google.maps.importLibrary === 'function';
					}
					if (ready()) { resolve(); return; }

					var elapsed = 0;
					var interval = 150;
					var poll = setInterval(function() {
						elapsed += interval;
						if (ready()) {
							clearInterval(poll);
							resolve();
						} else if (elapsed >= timeoutMs) {
							clearInterval(poll);
							reject(new Error(
								'Google Maps did not become available within ' +
								(timeoutMs / 1000) + 's. A browser extension (ad blocker) ' +
								'may be blocking requests to googleapis.com.'
							));
						}
					}, interval);
				});
			}

			/** The only library the module imports. Nothing may reference maps or core. */
			function loadPlacesLibrary() {
				return waitForImportLibrary().then(function() {
					return google.maps.importLibrary('places');
				});
			}

			// A missing PlaceAutocompleteElement means the key has no Places API (New). Show
			// the error rather than degrade to something that looks broken and reports nothing.
			function initAutocomplete($field) {
				loadPlacesLibrary()
					.then(function(placesLib) {
						if (typeof placesLib.PlaceAutocompleteElement !== 'function') {
							showAutocompleteError($field,
								'PlaceAutocompleteElement is not available. Check that ' +
								'Places API (New) is enabled for this API key.'
							);
							return;
						}
						initWithNewApi(placesLib.PlaceAutocompleteElement, $field);
					})
					.catch(function(err) {
						console.error(logPrefix + 'Failed to initialise.', err);
						// initWithNewApi() runs inside this chain, so a throw after the widget
						// was inserted lands here too and the widget must be torn down.
						if (liveAutocomplete) {
							degradeToManualEntry($field,
								'Falling back to manual entry after the widget failed post-insertion: ' +
								(err.message || 'unknown error'));
							return;
						}
						showAutocompleteError($field, err.message || 'Could not load Google Maps.');
					});
			}

			// Set once the widget is given up on. gmp-error can fire repeatedly, and a
			// second run would stack another banner.
			var autocompleteFailed = false;

			// The widget once it is in the DOM, not merely constructed. Only an inserted
			// widget has to be torn down, and only it can be holding typed text.
			var liveAutocomplete = null;

			// Denials tolerated before giving up. A permanent cause denies every request, and
			// requests go out per keystroke, so this is still reached within a second.
			var MAX_CONSECUTIVE_ERRORS = 3;

			// Denials further apart than this are not one burst. Without the window, three
			// unrelated blips minutes apart would add up to a degrade.
			var ERROR_BURST_WINDOW_MS = 10000;

			var consecutiveErrors = 0;
			var lastErrorAt       = 0;

			// Shown when the API never loaded. The usual cause is a blocked request.
			var LOAD_FAILURE_MESSAGE =
				'&#9888; Address autocomplete could not load. ' +
				'If you have an ad blocker, please allow <b>googleapis.com</b> and reload. ' +
				'You can still type the address manually.';

			// Shown when Google loaded but denied the request. Deliberately says nothing
			// about ad blockers or reloading: the cause is server-side.
			var REQUEST_DENIED_MESSAGE =
				'&#9888; Address suggestions are unavailable right now. ' +
				'Please type your address manually.';

			// Every failure path ends here. The message is participant-facing; detail is not.
			function showAutocompleteError($field, detail, message) {
				if (autocompleteFailed) { return; }
				autocompleteFailed = true;

				$field.show();
				$field.attr('placeholder', 'Address autocomplete unavailable — type manually');

				// Nothing will fill these in now, and a disabled input is not submitted, so
				// manual entry would otherwise save blank.
				setDestinationFieldsDisabled(false);
				$field.closest('.gaa-location-field').prepend(
					'<div style="color:#c00;font-size:12px;margin-bottom:4px;">' +
					(message || LOAD_FAILURE_MESSAGE) +
					'</div>'
				);
				console.warn(logPrefix + detail);
			}

			// Called only from the success path: if the widget never loads, nothing is sent
			// and there is nothing to disclose. Inserted with .text(), never .html().
			function addPrivacyNotice($field) {
				if (!privacyNoticeText) { return; }
				var $wrapper = $field.closest('.gaa-location-field');
				if ($wrapper.find('.gaa-privacy-notice').length) { return; }
				$('<div></div>')
					.addClass('gaa-privacy-notice')
					.text(privacyNoticeText)
					.appendTo($wrapper);
			}

			function initWithNewApi(PlaceAutocompleteElement, $field) {
				// try/catch so a bad filter value degrades to unfiltered predictions rather
				// than aborting initialisation and leaving a plain text box on the form.
				var placeAutocomplete = new PlaceAutocompleteElement();
				try {
					var regionCodes  = <?php echo $this->jsArray($set->regionCodes); ?>;
					var primaryTypes = <?php echo $this->jsArray($set->primaryTypes); ?>;
					if (regionCodes.length)  { placeAutocomplete.includedRegionCodes  = regionCodes; }
					if (primaryTypes.length) { placeAutocomplete.includedPrimaryTypes = primaryTypes; }
				} catch (e) {
					console.warn(logPrefix + 'Could not apply prediction filters; predictions will be unfiltered.', e);
				}

				placeAutocomplete.id = autocompletePrefix + 'autocomplete';
				placeAutocomplete.setAttribute('placeholder', 'Enter your address here');

				// Backend rejection: bad key, billing off, referrer restriction, an invalid
				// filter value, an exhausted quota, a transient 5xx. Without the teardown the
				// widget stays on the form looking usable while nothing is ever written.
				//
				// A burst is tolerated first, so a momentary denial is not permanent. The
				// event carries no documented, stable indication of why the request was
				// denied, so do not branch on a guessed detail property: a condition that is
				// silently never true reads like working code forever.
				placeAutocomplete.addEventListener('gmp-error', function(e) {
					var now = Date.now();
					if (now - lastErrorAt > ERROR_BURST_WINDOW_MS) { consecutiveErrors = 0; }
					lastErrorAt = now;
					consecutiveErrors++;

					if (consecutiveErrors < MAX_CONSECUTIVE_ERRORS) {
						console.warn(logPrefix + 'Google denied the request (' + consecutiveErrors +
							' of ' + MAX_CONSECUTIVE_ERRORS + '); leaving the widget in place.', e);
						return;
					}

					console.error(logPrefix + 'Google denied ' + consecutiveErrors +
						' consecutive requests. Check the API key and any prediction filter values.', e);
					degradeToManualEntry($field,
						'Falling back to manual entry after Google denied ' +
						consecutiveErrors + ' consecutive requests.');
				});

				// Live from this point on: anything that fails after this must tear the widget
				// down, not just un-hide $field.
				$field.before(placeAutocomplete);
				liveAutocomplete = placeAutocomplete;

				addPrivacyNotice($field);

				// Record what the user types, for unit recovery. The widget's shadow root is
				// closed, but input events are composed, so they cross it and retarget to the
				// host. isTrusted filters out the value the widget writes back itself.
				placeAutocomplete.addEventListener('input', function(e) {
					if (!e.isTrusted) { return; }
					var typed = placeAutocomplete.value || '';
					lastTypedText = typed;
					// A cleared box must not leave the previous address behind.
					if (typed === '') { fillInAddress(null, $field); }
				});

				applyGeolocationBias(placeAutocomplete);

				placeAutocomplete.addEventListener('gmp-select', async function(event) {
					// A prediction was served and chosen, so the denials have cleared. Reset
					// before the fetch: fetchFields() failing is a different request's problem.
					// Typing is not evidence Google answered, so input never resets it.
					consecutiveErrors = 0;

					var place = null;
					try {
						var prediction = event.placePrediction;
						if (prediction && typeof prediction.toPlace === 'function') {
							place = prediction.toPlace();
						} else if (event.place) {
							place = event.place;
						}

						if (place) {
							await place.fetchFields({
								fields: ['addressComponents', 'location', 'formattedAddress', 'displayName']
							});
						}
					} catch (e) {
						console.warn(logPrefix + 'Could not process place.', e);
						place = null;
					}
					fillInAddress(place, $field);
				});
			}

			// For a widget that reached the DOM: unlike the never-loaded paths there is live
			// state, a widget to remove and typed text that exists nowhere else. The privacy
			// notice stays: requests may already have gone to Google.
			function degradeToManualEntry($field, detail) {
				// Guard only. Do not set autocompleteFailed here: showAutocompleteError() at
				// the end would then early-return and the whole degrade would do nothing.
				if (autocompleteFailed) { return; }

				var placeAutocomplete = liveAutocomplete;

				// Rescue whatever the participant typed; it exists only inside the widget.
				// The guard is fieldHoldsSelectedAddress, not "is $field empty": on an edit
				// form $field arrives holding the address saved last time, and a half-typed
				// fragment must not replace an address the participant actually chose.
				var typed = '';
				try {
					typed = (placeAutocomplete && placeAutocomplete.value) || lastTypedText || '';
				} catch (e) {
					typed = lastTypedText || '';
				}
				if (typed && !fieldHoldsSelectedAddress) {
					$field.val(typed);
					$field.change();
				}

				// Remove rather than hide, so it cannot sit above the text input as a second
				// address box and its listeners cannot fire.
				try {
					if (placeAutocomplete) { placeAutocomplete.remove(); }
				} catch (e) {
					console.warn(logPrefix + 'Could not remove the autocomplete widget.', e);
				}
				liveAutocomplete = null;

				showAutocompleteError($field, detail, REQUEST_DENIED_MESSAGE);
			}

			// locationBias accepts a CircleLiteral directly, so no google.maps.Circle is
			// constructed: Circle belongs to the maps library, which is never imported, and
			// referencing it would throw inside this callback where nothing catches it.
			function applyGeolocationBias(placeAutocomplete) {
				if (!navigator.geolocation) { return; }
				navigator.geolocation.getCurrentPosition(function(position) {
					placeAutocomplete.locationBias = {
						center: {
							lat: position.coords.latitude,
							lng: position.coords.longitude
						},
						radius: position.coords.accuracy
					};
				}, function(err) {
					console.log(logPrefix + 'Geolocation unavailable; predictions will not be location-biased.', err);
				});
			}

			// Lat/lng are the only destinations with no googleSearch_* id: they are found by
			// field name instead.
			function fieldElement(id) {
				if (id === 'latitude')  { return byName(latitudeFieldName); }
				if (id === 'longitude') { return byName(longitudeFieldName); }
				return $('#' + id);
			}

			// Destination fields load disabled and a disabled input is not submitted, so a
			// write that does not also enable is a value REDCap never receives.
			function updateAndEnable(id, value) {
				updateValue(id, value);
				fieldElement(id).prop('disabled', false);
			}

			// Enables only if the field held something: a blank has to reach the record only
			// when it overwrites a value, and a field that was already empty stays locked.
			// Read before the clear and through .val(), which is what REDCap submits for all
			// three kinds updateValue() handles; .val() is null for an unselected select.
			function clearAndEnable(id) {
				var element  = fieldElement(id);
				var hadValue = element.length > 0 && String(element.val() || '') !== '';
				updateValue(id, '');
				if (hadValue) { element.prop('disabled', false); }
			}

			// Handles radios, selects and rc-autocomplete dropdowns. Does not enable the
			// field — callers go through updateAndEnable().
			function updateValue(id, value) {
				var element = fieldElement(id);

				if (element.length === 0) {
					console.log(logPrefix + 'Could not find the element with the following id:', id);
					return;
				}

				var eleType = element.prop('type');
				element.val(value);

				var eleName = element.attr('name');
				if (element.hasClass('hiddenradio')) {
					$('input[name="'+eleName+'___radio"][value="'+value+'"]').prop('checked', true);
				} else if (eleType.indexOf("select") >= 0) {
					// Match in order: exact value, underscored value, "Other", the option whose
					// text matches, then warn and leave blank.
					if ($('#'+id+' option[value="'+value+'"]').length > 0) {
						$('#'+id+' option[value="'+value+'"]').prop('selected', true);
					} else {
						var valUnderscore = value.replace(/\s+/g,"_");
						if ($('#'+id+' option[value="'+valUnderscore+'"]').length > 0) {
							$('#'+id+' option[value="'+valUnderscore+'"]').prop('selected', true);
						} else if ($('#'+id+' option[value="Other"]').length > 0) {
							$('#'+id+' option[value="Other"]').prop('selected', true);
						} else {
							var optionsWithMatchingContent = $('#'+id+' option').filter(function(){
								return $(this).html() === value;
							});

							if (optionsWithMatchingContent.length === 1) {
								optionsWithMatchingContent.prop('selected', true);
							} else {
								console.warn(logPrefix + "The value '" + value + "' is not a valid value for the '" + eleName + "' field; leaving it blank.");
								$('#'+id+' option[value=""]').prop('selected', true);
							}
						}
					}
				}

				element.change();

				if (element.hasClass('rc-autocomplete')) {
					var autocompleteField = element.closest('td').find('.ui-autocomplete-input');
					autocompleteField.val(element.find('option:selected').text());
					autocompleteField.change();
				}
			}

			// Walks the raw component list, independently of componentForm, which has no
			// subpremise entry.
			function extractUnitParts(components) {
				var parts = { unit: '', streetNumber: '' };
				if (!components || !components.length) { return parts; }
				for (var i = 0; i < components.length; i++) {
					var comp = components[i];
					if (!comp || !comp.types) { continue; }
					var type = comp.types[0];
					var val  = comp.shortText || comp.longText || '';
					if (type === 'subpremise' && !parts.unit) {
						parts.unit = String(val).trim();
					} else if (type === 'street_number' && !parts.streetNumber) {
						parts.streetNumber = String(val).trim();
					}
				}
				return parts;
			}

			function escapeRegExp(str) {
				return String(str).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
			}

			// Google frequently omits subpremise for AU/UK unit addresses: "3/27 Harris St"
			// comes back as street number 27 with no subpremise. Anchored to the street
			// number Google did return, and returns '' rather than guessing.
			function recoverUnitFromText(typed, streetNumber) {
				if (!typed || !streetNumber) { return ''; }
				var text = String(typed).trim();
				var sn   = String(streetNumber).trim();
				if (!text || !sn) { return ''; }

				// On a word boundary, so a street number of "7" is not matched inside "27".
				var snMatch = new RegExp('(^|[^0-9A-Za-z])' + escapeRegExp(sn) + '(?![0-9A-Za-z])').exec(text);
				if (!snMatch) { return ''; }

				var snIndex = snMatch.index + snMatch[1].length;
				if (snIndex <= 0) { return ''; }   // nothing precedes it, so no unit

				var prefix = text.slice(0, snIndex);

				// "27-29 Harris St" is a street number range, not a unit.
				if (/[-–—]\s*$/.test(prefix)) { return ''; }

				// A unit prefix is short; anything longer is a building or place name.
				if (prefix.replace(/\s+/g, ' ').trim().length > 24) { return ''; }

				// Must END with a unit token, which is what rejects "Harris St 27" and
				// "The Old Rectory, 27 Harris St".
				var unitMatch = /(?:^|[\s,])(?:(?:unit|apt|apartment|flat|suite|ste|shop|villa|lot|level|lvl|room|rm)\.?\s*)?([0-9]{1,5}[A-Za-z]?)\s*[\/,]?\s*$/i.exec(prefix);
				return unitMatch ? unitMatch[1].toUpperCase() : '';
			}

			// Through updateAndEnable(), or the field stays disabled and REDCap never
			// receives the value.
			function applyUnitToStreetNumber(unit, streetNumber) {
				var id = autocompletePrefix + 'street_number';
				if (!document.getElementById(id) || !unit || !streetNumber) { return; }
				updateAndEnable(id, unit + '/' + streetNumber);
			}

			// Rewrites a leading bare street number to "3/27", keeping the stored address
			// consistent with the components. No-ops when Google supplied the subpremise.
			function patchFormattedAddress($field, unit, streetNumber) {
				var current = $field.val();
				if (!current || !unit || !streetNumber) { return; }
				var leading = new RegExp('^\\s*' + escapeRegExp(streetNumber) + '(?![0-9A-Za-z])');
				if (leading.test(current)) {
					$field.val(current.replace(leading, unit + '/' + streetNumber));
					$field.change();
				}
			}

			// Runs after the component loop, so it overwrites the bare street number that
			// loop just wrote. Does nothing unless a Street Number Field is mapped.
			function applyUnitFromComponents(components, $field) {
				var parts = extractUnitParts(components);
				var unit  = parts.unit;
				<?php if ($set->recoverUnit): ?>
				// Google omitted subpremise — fall back to parsing the typed text.
				if (!unit) { unit = recoverUnitFromText(lastTypedText, parts.streetNumber); }
				<?php endif; ?>
				lastTypedText = '';   // consume, so a later selection cannot reuse it

				if (!unit || !parts.streetNumber) { return; }
				if (!document.getElementById(autocompletePrefix + 'street_number')) { return; }
				applyUnitToStreetNumber(unit, parts.streetNumber);
				patchFormattedAddress($field, unit, parts.streetNumber);
			}

			// Populates or clears every destination field from the selected Place.
			function fillInAddress(place, $field) {
				// Clear first, or a component absent from the new place keeps its old value.
				for (var component in componentForm) {
					clearAndEnable(autocompletePrefix + component);
				}

				if (place && place.addressComponents && place.addressComponents.length > 0) {
					$field.val(place.formattedAddress || '');
					$field.change();
					// A blank address is nothing worth protecting from the degrade path.
					fieldHoldsSelectedAddress = ($field.val() !== '');

					// Cleared first: a place with no location must not leave the previous
					// address's coordinates behind, which is a wrong record, not a blank one.
					<?php echo ($set->latitude  ? "clearAndEnable('latitude');\n"  : ""); ?>
					<?php echo ($set->longitude ? "clearAndEnable('longitude');\n" : ""); ?>
					if (place.location) {
						<?php echo ($set->latitude  ? "updateAndEnable('latitude',  place.location.lat());\n" : ""); ?>
						<?php echo ($set->longitude ? "updateAndEnable('longitude', place.location.lng());\n" : ""); ?>
					}

					for (var i = 0; i < place.addressComponents.length; i++) {
						var comp = place.addressComponents[i];
						// A component without types would throw on types[0], outside any catch.
						if (!comp || !comp.types || !comp.types.length) { continue; }
						var addressType = comp.types[0];
						if (componentForm[addressType] && document.getElementById(autocompletePrefix + addressType)) {
							// Runs outside the gmp-select try/catch, so a component missing the
							// requested property would abort the rest of the fill uncaught.
							var val = String(comp[componentForm[addressType]] || '');   // 'shortText' or 'longText'
							if (addressType === 'administrative_area_level_2') {
								val = $.trim(val.replace('County', ''));
							}
							updateAndEnable(autocompletePrefix + addressType, val);
						}
					}

					applyUnitFromComponents(place.addressComponents, $field);
					<?php echo ($set->placeName ? "
					// place_name is not in componentForm, so the clear loop above does not reach
					// it. Clear then refill unconditionally, or a name captured for an earlier
					// selection stays attached to a different address.
					var placeNameId = autocompletePrefix + 'place_name';
					if (document.getElementById(placeNameId)) {
						clearAndEnable(placeNameId);
						if (place.displayName) {
							updateAndEnable(placeNameId, place.displayName);
						}
					}\n" : ""); ?>
				} else {
					// No place selected — clear the original field and lat/lng
					$field.val('');
					$field.change();
					fieldHoldsSelectedAddress = false;
					<?php echo ($set->latitude  ? "clearAndEnable('latitude');\n"  : ""); ?>
					<?php echo ($set->longitude ? "clearAndEnable('longitude');\n" : ""); ?>
					<?php echo ($set->placeName ? "
					var clearedPlaceNameId = autocompletePrefix + 'place_name';
					if (document.getElementById(clearedPlaceNameId)) {
						clearAndEnable(clearedPlaceNameId);
					}\n" : ""); ?>
				}

				if (typeof doBranching === 'function') { doBranching(); }
			}
		})();
		</script>
		<?php
	}
}
