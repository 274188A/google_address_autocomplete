# Development

[← Back to the README](../README.md)

There is no build system and no package manager. Deployment is copying four files —
`Google_Address_Autocomplete.php`, `AddressComponent.php`, `AddressFieldSet.php` and
`config.json`. See [Installation](installation.md).

REDCap reads the deployed version from the module **directory name** (`_v<version>`), not from
`config.json`, so a release means renaming the deployment directory and tagging the commit to
match [CHANGELOG.md](../CHANGELOG.md).

[Execution flow](execution-flow.md) traces a page render end to end — which address field sets
are emitted server-side, how the Google widget is attached to the form, and what a selection
writes where — with a diagram and its exit paths for each of the three phases.

## Tests

Two harnesses, neither needing a REDCap server. **Run both** — they cover different layers and
neither catches the other's regressions.

| Harness | Runs | Proves |
|---|---|---|
| Golden output (`tests/golden.php`) | PHP | The emitted markup is byte-identical for identical settings |
| Unit (`tests/unit.test.mjs`) | Node | The pure JavaScript helpers still behave as they do today |

```
D:\PHP\v8.5.10\php.exe tests/golden.php verify
node --test tests/unit.test.mjs
```

Both exit non-zero on failure, so either works as a pre-commit gate. PHP is not on `PATH` on the
maintainer's machine, hence the full path above — substitute your own install path.

**[tests/README.md](../tests/README.md) is the authoritative reference** for both harnesses —
layout, what each fixture covers, how the unit tests extract functions from the PHP source, and
which current behaviours are pinned deliberately. What follows is the short version.

### Golden output

The module's only observable behaviour is the markup it echoes, so correctness under refactoring
is checkable directly: for identical settings input, the output must be byte-identical.

```
D:\PHP\v8.5.10\php.exe tests/golden.php verify    # exit 1 on any mismatch
D:\PHP\v8.5.10\php.exe tests/golden.php capture   # re-record, only when a markup change is intended
```

Twenty fixtures in `tests/fixtures.php` cover one and two sets, each optional field mapping, the
privacy-notice states, instrument and event scoping, and both misconfiguration warnings. Five of
them assert an *empty* render — a disabled set, a set scoped out by instrument or by event, a null
event id, and a missing API key — so a change that starts emitting on a page that should stay
untouched fails as loudly as a change to the markup itself. Log messages are
captured alongside the markup, so a change that stops warning about a misconfigured set fails
too. It runs against a stub of `AbstractExternalModule` implementing the three framework methods
the module calls.

The `sparse-missing-keys` fixture is the one to keep working: it supplies only the required
`set-autocomplete` key, reproducing what REDCap returns when a setting was added to `config.json`
after a project was configured. The module's defensive reads exist for that case.

### Unit tests

`node --test tests/unit.test.mjs`. No dependencies and no `package.json` — `node:test` and
`node:assert` are built in. They cover the unit-recovery path (`recoverUnitFromText()`,
`extractUnitParts()`, `escapeRegExp()`), which is pure, regex-heavy and the most intricate logic
in the module.

The functions are **extracted from `Google_Address_Autocomplete.php` at run time, not copied**, so
they cannot drift from what ships. Adding another pure helper means adding its name to the
`loadFunctions([...])` call.

If you change `recoverUnitFromText()`, re-run the worked examples in
[Unit and apartment numbers](form-behaviour.md#unit-and-apartment-numbers) — that table documents
the parser's behaviour and has to stay true to it.

### Limits

The harnesses prove the emitted markup is unchanged and that the extracted pure helpers still
behave the same. Neither proves the module works on a real server — in particular they cannot
tell you whether REDCap autoloads sibling classes in the module namespace, which is why
`AddressFieldSet` and `AddressComponent` are `require_once`d explicitly.

The emitted JavaScript is still largely unexecuted: everything touching the DOM, jQuery or the
Google Places API is covered by neither harness. Changes there warrant a browser check on a real
form — the PHP conditionals produce materially different JavaScript depending on which settings
are mapped, and an unsubstituted `<?php` tag or an empty assignment (`var x = ;`) kills the whole
inline script.

There is a second, narrower blind spot. Fixtures supply *settings*, never form markup, so no
harness can express "this destination arrived holding a value". Everything conditional on the
state of the form at load — `disableEmptyDestinationFields()`, the seeded widget and its fallback
line, the `boxHeldText` clear guard — is verified by hand against a live form only. Re-check those
on a fresh record after touching them, and remember `@DEFAULT` fires only while the field is
blank, so a record already saved shows nothing.

`php -l` is likewise a syntax check, not a compatibility check. The maintainer's PHP is 8.5 while
`config.json` sets a floor of 8.2, so 8.3+ syntax lints clean locally and would fatal on the
server. That ceiling has to be held by review.

## Design notes

The code carries short comments where a line is genuinely non-obvious. The reasoning behind the
decisions those comments guard is collected here instead, so the module itself stays readable.
[CHANGELOG.md](../CHANGELOG.md) records the bugs that prompted several of them.

### Places API (New) only

The legacy `google.maps.places.Autocomplete` path was removed in 1.2 and must not come back. It
rendered as an ordinary text box with no error — the worst failure mode available — and Google no
longer enables the legacy Places API for newly issued keys. A missing `PlaceAutocompleteElement`
therefore surfaces as an error banner, never as a silent downgrade.

### `places` is the only library imported

Nothing may reference `google.maps.Circle`, `LatLngBounds`, or anything else from `maps` or
`core`. They are `undefined`, and a reference inside an async callback — a geolocation success
handler, say — throws where nothing catches or logs it. That is exactly how the location bias was
silently broken before 1.2, and why `applyGeolocationBias()` assigns a plain `CircleLiteral`
(`{center, radius}`) rather than constructing a `Circle`.

### Nothing reaches global scope, and nothing carries a fixed DOM id

All behaviour lives inside one IIFE per set, and the API key is passed to the bootstrap loader as
an IIFE argument rather than parked on `window`. A collision with another module on the same page
is the failure mode this avoids.

Every element id is built from `autocompletePrefix` (`googleSearch_<set index>_`), which is unique
per set; the wrapper is found by the class `.gaa-location-field`. Several sets can share a page,
and a repeated id is invalid HTML that makes every lookup resolve to the first match — set B would
write into set A's fields. A fixed `#locationField` was exactly that bug.

The prefix uses the **configured** position of the set, not its position among the sets that
qualified on the current page, so a set's ids stay stable however the others are scoped.

### Destination fields load disabled unless pre-populated

Destination fields are `disabled` on load **only if they arrive empty**
(`disableEmptyDestinationFields()`), and re-enabled individually as each receives a value. That
prevents manual edits to fields the module populates itself. A disabled input is not submitted, so
anything that writes a value **must** also enable its element:

- every write goes through `updateAndEnable()`, never a bare `updateValue()`;
- every clear goes through `clearAndEnable()`, which enables only when the field actually held
  something. A blank has to reach the record only when it overwrites a value, and enabling
  unconditionally made the whole address hand-editable after one selection.

The empty check is the exception, and it exists because a destination can arrive already holding a
value the module did not write: `@DEFAULT` or `@SETVALUE`, piping, a data import, or the value
saved last time on an edit form. Disabling those unconditionally meant REDCap received nothing for
the field and the save wrote a blank over a value REDCap itself had rendered moments earlier —
`@DEFAULT` looked broken while the action tag was working correctly.

That narrows the guarantee deliberately: a pre-populated address is hand-editable, an
autocomplete-populated one is not until it receives a value. Silent data loss is the worse
failure, and for a "has your postal address changed?" question an editable default is the wanted
behaviour anyway.

Latitude and longitude are the ones to watch: they are the only destinations resolved by field
*name* rather than by `googleSearch_*` id, which is how they came to be written without ever being
enabled. `fieldElement()` resolves both kinds, so `updateAndEnable()` does not need to know which
it was handed. `destinationFieldNames()` is the single list of both kinds, so a new destination
cannot be disabled by one path and missed by the other.

Known limitation, deliberately not fixed: a field enabled by one fill stays enabled for the rest
of the page.

### An address already on record is shown, and only a deliberate clear wipes it

The source field is hidden and the widget is created empty, so a value already in that field is
invisible on the form. `seedWidgetWithExistingAddress()` writes it into the widget after
insertion, so the participant can see what is held and leave it alone if it is still correct.

Writability of `.value` is not documented for `PlaceAutocompleteElement` — only reading it is
relied on elsewhere — so the write is read back and verified rather than assumed. An element that
ignores it gets a static `.gaa-existing-address` line above the widget instead. Either way the
address is shown; what must not happen is a form that silently holds an address the participant
was never given the chance to confirm.

The seeder sets `boxHeldText` and **must not** set `fieldHoldsSelectedAddress`. The two are not
interchangeable: `fieldHoldsSelectedAddress` means "this session's autocomplete wrote `$field`"
and gates the typed-text rescue in `degradeToManualEntry()`, so setting it here would discard a
half-typed address on exactly the pre-populated forms this seeding exists for — the bug the rescue
gate was written to avoid, re-entered from the other side.

`boxHeldText` also gates the clear-on-empty path in the `input` listener. Clearing the box clears
the record only once the box has held text. An untouched empty box on a pre-populated form
receives `input` events too, and clearing on those wiped a defaulted address the participant never
edited — which, with the fields now enabled, would have saved the blank.

### Failure degrades, never blocks

`showAutocompleteError()` un-hides the original input, prepends a red banner and re-enables the
destination fields. `degradeToManualEntry()` handles the harder case of a widget that reached the
DOM and then became unusable: it rescues the typed text into the source field, removes the widget,
then hands off to `showAutocompleteError()`.

The rescue is gated on `fieldHoldsSelectedAddress`, not on the source field being empty. On an
edit form that field arrives pre-populated with the address saved last time, so "is it empty"
discarded the participant's typed text and revealed the stale address instead. The only value
worth protecting is one *this session's* autocomplete wrote.

Both paths deliberately leave the destination fields holding their previously saved components —
the module has no idea what the right ones are, and unlocking them lets the participant correct
them.

### `gmp-error` tolerates a burst before degrading

Three denied requests within ten seconds, the count reset at the top of the `gmp-select` handler
and never on `input`. Degrading on the first error made a momentary denial permanent.

The window is not optional. `gmp-select` needs the participant to actually pick something, so
without a window three unrelated blips minutes apart would add up to a degrade. The reset happens
*before* the `await`: a served-and-chosen prediction is the proof that Google answered, not
`fetchFields()` succeeding afterwards.

The event carries no documented, stable indication of *why* a request was denied, so a permanent
cause cannot be distinguished from a transient one here. Do not "improve" this by branching on a
guessed detail property — a condition that is silently never true reads like working code forever.

### `subpremise` is not a component

`componentForm` doubles as the registry of "components with a destination element", and every
entry is cleared through `updateValue(autocompletePrefix + type)` on each selection. No
`googleSearch_*subpremise` element exists, so an entry would only log "Could not find the element"
every time. `extractUnitParts()` handles the unit instead, walking the raw component list
independently, and `applyUnitFromComponents()` runs *after* the component loop so that `3/27`
overwrites the bare street number that loop just wrote.

### Settings are baked in, and escaped once

Settings are compiled into the IIFE at emit time rather than read at runtime, so an unconfigured
feature emits no code and cannot misfire. Every value emitted into JavaScript goes through
`jsValue()`, `jsArray()` or `jsObject()`:

- they fall back to `""`, `[]` and `{}` when `json_encode()` fails, because `var x = ;` is a
  syntax error that kills the whole IIFE and leaves a plain text box on the form;
- they apply `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT`. `JSON_HEX_TAG` is the
  one that matters: it stops a `</script>` sequence inside a setting value closing the block early.

Do **not** use `htmlspecialchars()` for this. These values land in a *JavaScript* string context,
and HTML entities are not decoded inside `<script>`, so html-escaping corrupts the value rather
than protecting it. That was a real bug in the API key emit.

Field names reach JavaScript as data, never as selector text: the component field names are
emitted as the `destinationFields` JSON object, and every lookup goes through `byName()`, which
escapes quotes and backslashes for the attribute selector.

### Address field sets

The field mappings live in a repeatable `sub_settings` group (`address-set`), read with
`getSubSettings('address-set', $project_id)`.

- `repeatable: true` goes on the `sub_settings` **parent**, never on the children.
- Child keys are stored **flat and globally**. `set-city` is one top-level parallel array across
  all instances, so every child key must be unique across the whole `config.json` — hence the
  `set-` prefix. Reusing a key silently shares storage with whatever else holds it.
- Never assume a child key exists. One added to `config.json` after a project was configured
  simply does not appear in the returned instance array, so every read is `$set['set-x'] ?? ''`.
  The `sparse-missing-keys` fixture exists for exactly this.
- Do not rename a child key without a migration. An old scalar value read as an instance array
  gets string-offset into single characters (`"addr1"[0] === "a"`), which corrupts silently rather
  than failing.
- `set-event` (`event-list`) scopes a set to events, alongside `set-form` for instruments.
  Both are read through the same `stringList()` helper, which casts to string — an
  event-list stores numeric ids while the hook passes an int, so an uncast strict
  comparison would never match and would scope every set out on every page.
- The event picker is hidden on classic projects by `redcap_module_configuration_settings`
  rather than `branchingLogic`, which the framework documents as unreliable inside
  `sub_settings`. That hiding is cosmetic: `getSubSettings()` still returns hidden values,
  so a stored event scope keeps applying either way.
- The API key, the bootstrap loader and the privacy notice are project-wide rather than per set:
  the Maps API can only be bootstrapped once per page, so a second key would be silently ignored.

### The privacy notice is a compliance control

The module relays participant keystrokes to a third party overseas, so the disclosure shows by
*default* and an administrator must deliberately suppress it. It is inserted with `.text()`, never
`.html()`, and `addPrivacyNotice()` is called only from the success path — if the widget never
loads, nothing reaches Google and there is nothing to disclose.

### Framework version

`config.json` declares framework version 16, which requires REDCap 14.6.4+ (LTS 15.0.9). Nothing
depends on 16 specifically; framework **13** is the real floor, because that is where
`getSubSettings()` began including `hidden` settings.

Two consequences bind together and must not be split: `config.json` carries no `permissions`
block (deprecated, and required absent from v12 onward), and hook methods must be named `redcap_*`
— the legacy `hook_*` names do not fire. Changing one without the other silently disables the
module.

## Documentation layout

`README.md` is what REDCap serves through **View Documentation** in the module manager
(`"documentation": "README.md"` in `config.json`). Two rules follow from that, and both matter:

- **Links out of `README.md` are absolute GitHub URLs.** Relative paths do not resolve in
  REDCap's documentation viewer. Links *between* pages in `docs/` are relative — those pages are
  only ever read on GitHub.
- **`README.md` carries no raw HTML.** `<details>` blocks are used in `docs/` to keep long
  reference pages short, but REDCap's markdown renderer may escape or strip them, so they stay
  out of the README.

Keep `docs/` in sync with behaviour changes, and add an entry to
[CHANGELOG.md](../CHANGELOG.md) (Keep a Changelog format, `[Unreleased]` at the top).

## AI-assisted development

Parts of this module — code, review and documentation, including these pages — were produced
with assistance from [Claude](https://claude.ai) (Anthropic), used via Claude Code. Everything
was reviewed by the maintainer before release, and behaviour is verified against a live REDCap
instance. Responsibility for the code rests with the maintainer, not the tool.
