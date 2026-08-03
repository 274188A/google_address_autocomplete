# Changelog

All notable changes to this module are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

REDCap reads the deployed version from the module **directory name**
(`redcap/modules/<module_name>_v<version>/`), not from this file or from `config.json`. When
releasing, rename the deployment directory and tag the commit to match the version here.

---

## [Unreleased]

### Fixed

- **Coordinates no longer stay attached to a different address.** The latitude and longitude writes
  sat inside `if (place.location)`, so selecting a place Google returns no location for left the
  previous address's coordinates in the fields — the same class of stale value as the place name,
  and a wrong record rather than a visibly missing one. They are now cleared before the write, like
  every other destination.
- **A fill no longer unlocks address fields it left blank.** Making cleared fields submit was done
  by enabling every mapped field on every selection, which also handed the participant every field
  the new address did not supply — after one selection the whole address was hand-editable, and the
  "disabled prevents manual edits" guard held only until then. A cleared field is now enabled only
  when it actually held a value, which is the only case where the blank has to reach the record.
  A field that was blank before the selection and after it stays locked. One limitation is
  unchanged: a field enabled by one selection stays enabled for the rest of the page.
- **Latitude and longitude are now saved.** They were disabled on page load along with every other
  destination field, but they are the only two written by field *name* rather than by
  `googleSearch_*` id, and the re-enable was missed on all four of their write sites. A disabled
  input is not submitted, so the coordinates appeared on the form and reached no record — and on an
  edit form the clear never reached the record either. Every write now goes through a single
  `updateAndEnable()` helper, which resolves either kind of lookup, so a write that does not enable
  is no longer something a caller can forget.

## [1.0.0] - 2026-08-03

First release of **Google Address Autocomplete**: a REDCap External Module that adds Google
Places address autocomplete to survey and data entry forms, with support for multiple
independent address field sets per project.

Since this is the first release, the sections below describe what the module contains rather
than what changed. Later releases will record changes against this baseline.

### Features

- **Multiple address field sets per project.** A project can capture more than one address —
  a participant's home address on one instrument and their GP practice address on another, say.
  The field-mapping settings live in a repeatable `sub_settings` group (`address-set`), read
  with `getSubSettings()`, following
  [lsgs/redcap-copy-data-on-save](https://github.com/lsgs/redcap-copy-data-on-save) as the
  reference implementation. Each set gets its own IIFE and its own element-id prefix
  (`googleSearch_<n>_*`), so sets sharing a page are fully isolated; the Google Maps bootstrap
  loader and the `<style>` block are emitted once per page regardless of how many sets are
  active.
- **Per-set instrument scoping** (`set-form`, a repeatable `form-list`). A set emits nothing at
  all on instruments it is not scoped to. Leaving it blank falls back to the client-side
  field-presence guard, which no-ops the script when the autocomplete field is not on the form.
- **Per-set tuning.** The prediction filters, unit recovery and description are configured
  independently for each set, so a home-address set and a medical-centre set can be tuned
  differently. The optional description identifies the set in browser console messages, which
  are prefixed `[Address Autocomplete #n]`.
- **Unit / sub-premise recovery** for Australian and UK addresses, where Google routinely
  omits the unit number. Handles both causes independently: reading the `subpremise`
  component when Google returns it (always on), and parsing the unit out of the typed text
  when Google omits it (opt-in, via **Recover unit/apartment number from typed text**). The
  result is written to the Street Number Field as `3/27`.
- **Prediction filtering** by CLDR region code (`set-region-codes`) and by place type
  (`set-primary-types`), applied as properties inside `try`/`catch` so a rejected value
  degrades to unfiltered predictions rather than breaking the field.
- **Geolocation bias**, so predictions near the participant rank higher.
- **Place Name Field** setting, for the selected place's display name.
- **A privacy notice under the address box, shown by default.** The module relays every
  keystroke in the address field to Google, which is a disclosure of personal information to a
  third party and — because Google processes it overseas — a cross-border disclosure (APP 8 for
  Australian projects, GDPR Chapter V for UK/EU). Without it a participant sees an ordinary-looking
  REDCap field with nothing indicating the transfer. Two settings control it: `privacy-notice` to
  reword the notice, `hide-privacy-notice` to suppress it where the consent form already covers the
  disclosure. The notice is inserted with `.text()` rather than `.html()`, so administrator
  wording can never inject markup.
- **Skip-and-log validation of each set.** A set with no autocomplete field, a disabled set, or a
  set whose autocomplete field is already claimed by an earlier set on the same page is skipped
  and the reason written to the module log; two sets writing to one destination field are both
  emitted with a warning logged. A misconfigured set never takes the rest of the page down.
- **A visible error banner when autocomplete cannot load**, with the original text input
  un-hidden so data entry is never blocked.
- Guards against the autocomplete field being absent from the current instrument, so the
  script no-ops instead of erroring on multi-instrument projects.
- Clearing the search box clears every destination field, so a cleared search cannot leave the
  previous address behind. Selecting a second address clears the fields the new one does not
  supply, including the Place Name Field — which is read from `place.displayName` rather than
  from `addressComponents`, so it is cleared and refilled explicitly rather than through the
  component loop. A name left bound to the wrong address would be a wrong record, where a blank
  field is a visibly missing one.
- Destination fields are enabled as they are cleared, not only as they are filled. They start
  `disabled` so that only autocomplete-populated values are saved, and a disabled input is not
  submitted — so on a record being edited, a cleared field has to be enabled for the clear to
  reach REDCap at all.
- REDCap's branching logic is re-evaluated (`doBranching()`) after any fill or clear.
- **An unmatched dropdown value warns to the console rather than interrupting the participant.**
  Where a Google component has no matching option in a REDCap select or radio field,
  `updateValue()` sets the field to its blank option and reports through
  `console.warn(logPrefix + …)`, matching how every other recoverable problem in the module
  reports. A modal `alert()` would block the thread mid-fill, once per unmatched field.

### Requirements and behaviour

- **Places API (New) only.** There is no legacy `google.maps.places.Autocomplete` path. The
  legacy API rendered as an ordinary text box with no error — the worst available failure mode —
  and Google no longer enables it for newly issued keys. A key without Places API (New) produces
  a visible error instead of a silently inert box.
- **`framework-version` is 16**, setting the minimum to **REDCap 14.6.4+** (LTS 15.0.9+).
  Framework 13 is the meaningful floor, since that is where `getSubSettings()` began including
  settings marked `hidden`; 16 is declared to stay current while remaining installable on LTS
  instances. Nothing in the module depends on framework 17.
- **`compatibility.php-version-min` is `8.2.0`.** REDCap enforces this at install time, so the
  module will not install on servers running PHP 8.1 or earlier.
- **The field-mapping settings live inside the repeatable group** and carry a `set-` prefix
  (`set-autocomplete`, `set-street-number`, `set-recover-unit`, `set-region-codes`,
  `set-primary-types`, and so on for every component field). The prefix keeps them distinct from
  any flat key, because sub-settings are stored as parallel arrays: a scalar read as a
  sub-setting would be string-offset into single characters rather than read as an instance,
  corrupting the value silently. `google-api-key`, `import-google-api`, `privacy-notice` and
  `hide-privacy-notice` are project-wide and carry no prefix.
- **The wrapper around each search box is identified by the class `.gaa-location-field`**, not by
  an id. A page can carry several wrappers, and a repeated id is invalid HTML that would make
  every lookup resolve to the first match.
- **Emitted element ids are `googleSearch_<n>_<component>`**, where `n` is the address field set's
  configured position. This is what stops two sets writing into each other's fields. The ids are
  internal to the module, but anything referencing them externally must match this form.
- **Hook methods are named `redcap_survey_page` / `redcap_data_entry_form`**, with their full
  documented signatures. From framework version 12 the legacy `hook_*` names no longer fire; hook
  methods are detected automatically by name.

### Implementation notes

- The Google Maps API is loaded through the official inline bootstrap loader, emitted from a
  nowdoc so PHP does not interpret JS template literals as variables. The loader body is wrapped
  in an IIFE and the key passed as its argument, so the module contributes nothing to global
  scope and cannot collide with another module on the same page.
- The Google API key is emitted with `json_encode()` rather than `htmlspecialchars()`. HTML
  entities are not decoded inside `<script>`, so entity-escaping would deliver a key containing
  a quote character to Google corrupted rather than escaped.
- Every project setting emitted into the inline JavaScript goes through `json_encode()`
  with `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT`, so no setting value can
  break out of the `<script>` block or out of a string literal.
- Three JavaScript encoders (`jsValue()`, `jsArray()`, `jsObject()`) back that emit. On
  `json_encode()` failure they fall back to `""`, `[]` and `{}`, never to an empty assignment —
  `var x = ;` is a syntax error that would kill the entire inline script.
- `addAddressAutoCompletion()` is an orchestrator: it resolves the active sets, emits the
  loader, styles and privacy notice once, then delegates one IIFE per set to `emitSetScript()`.
- The eight address-component field names are emitted once as a single JSON object
  (`destinationFields`) and looked up through a `byName()` helper, which escapes quotes and
  backslashes for the attribute selector.
- Address component values are coerced with `String(... || '')` and the component `types` array
  is guarded before use, so a component missing the requested `shortText` / `longText` cannot
  abort the fill. The component loop runs outside the `try` in the `gmp-select` handler, so a
  throw there would otherwise take the rest of the fill with it — unit recovery and the place
  name included. The coercion also keeps `undefined` out of `updateValue()`, where it would
  reach the select branch as `option[value="undefined"]`.
- A configured set is an `AddressFieldSet` readonly value object, built once at the boundary by
  `fromSubSetting()`. Sub-settings are stored flat, so a key added to `config.json` after a
  project was configured is simply absent — the factory's defensive reads exist for that case,
  and no constructor parameter may become required.
- `AddressComponent`, a backed enum, generates both the PHP field mapping and the emitted
  JavaScript `componentForm`, keeping the `shortText`/`longText` preference next to the field
  mapping rather than in the JS template. `place_name` returns `null` from `format()` and is
  excluded from `componentForm`, since it is read from `place.displayName` rather than from
  `addressComponents`.
- Internal methods carry parameter and return types. Both REDCap hook signatures are
  deliberately untyped: the framework calls them by name and `$project_id` can arrive null.
  `addAddressAutoCompletion()` is `private`, as it is not a hook name.
- **Line endings are pinned to LF** for `*.php` and the golden files. The module class file is
  largely inline `<script>`/`<style>` template echoed verbatim, so with `text=auto` a Windows
  checkout would emit CRLF markup and a Linux checkout LF — the same commit serving different
  bytes depending on where it was cloned.

### Tests

- **A golden-output test harness** (`tests/`). The module's only observable behaviour is the
  markup it echoes, so it is captured for fifteen settings fixtures and byte-compared afterwards.
  Runs against a ~40-line stub of `AbstractExternalModule` implementing the three framework
  methods the module calls; log messages are captured alongside the markup, so a change that
  stops warning about a misconfigured set fails too. `php tests/golden.php verify`.
- **Unit tests for the unit-recovery path** (`tests/unit.test.mjs`, `node --test
  tests/unit.test.mjs`, no dependencies). `recoverUnitFromText()`, `extractUnitParts()` and the
  `escapeRegExp()` they depend on are the most intricate logic in the module, and the golden
  harness proves only that code was *emitted*, never that it *works*. The functions are extracted
  from the PHP source at run time rather than copied, so the tests cannot drift from the shipped
  code. Documented alongside them: `extractUnitParts()` inspects only `types[0]`, so a component
  listing `subpremise` second is missed and the unit falls through to the typed-text recovery —
  recoverable when Unit Recovery is enabled for the set, lost when it is not. That is pinned as
  current behaviour rather than fixed, since the same convention is used everywhere the module
  reads a component type.

[1.0.0]: https://github.com/274188A/google_address_autocomplete/releases/tag/v1.0.0
