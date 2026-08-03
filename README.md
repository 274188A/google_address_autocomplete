# Google Address Autocomplete

A [REDCap](https://projectredcap.org/) External Module that adds Google Maps address
autocomplete to survey and data entry forms.

As a participant types into the address field, Google Places offers predictions. Choosing one
splits the address into whichever REDCap fields you have mapped — street number, street, city,
state, postcode, country, latitude/longitude and place name — and stores the full formatted
address in the original field.

A project can configure **several independent address field sets**: the participant's home
address on one instrument, their GP practice address on another.

Single-file PHP module. Nothing to build, no dependencies to install.

---

## Quick start

You will need **REDCap 14.6.4+**, **HTTPS**, and a Google Maps API key with **Places API (New)**
enabled. See [Requirements](#requirements) for the detail behind each.

1. **Copy** `Google_Address_Autocomplete.php` and `config.json` into
   `redcap/modules/<module_name>_v<version>/`.
   *Don't rename that directory* — REDCap derives the module prefix and version from it.

2. **Enable** the module in **Control Center → External Modules**, then enable it on your project.

3. **Configure** it on the project (**External Modules → Configure**) and set:
   - **Google API Key** — your Maps Platform key
   - **Autocomplete Field** — the text field the search box attaches to
   - at least one destination field, e.g. **Street Field** and **City Field**

4. **Name the instrument** under **Instrument(s) this set applies to**, so nothing is emitted on
   unrelated forms.

5. **Hard-refresh** the form (Ctrl+F5) and start typing an address.

Not working? Go straight to [Troubleshooting](#troubleshooting).

> **Before going live**, work through [Securing your API key](#securing-your-api-key) and
> [Privacy](#privacy). The key is visible in the page source — inherent to the client-side Maps
> API — and the module sends participant-typed text to Google, which is a disclosure you may need
> to declare.

---

## Contents

**Getting it working**
- [Quick start](#quick-start) · [Requirements](#requirements) · [Installation](#installation) ·
  [Settings](#settings) · [Setting up your REDCap fields](#setting-up-your-redcap-fields)

**Going further**
- [Address field sets](#address-field-sets) · [Unit and apartment numbers](#unit-and-apartment-numbers) ·
  [How it behaves on the form](#how-it-behaves-on-the-form)

**Before you go live**
- [Securing your API key](#securing-your-api-key) · [Privacy](#privacy)

**When something's wrong**
- [Troubleshooting](#troubleshooting)

**About the project**
- [Development](#development) ·
  [Execution flow](https://github.com/274188A/google_address_autocomplete/blob/main/EXECUTION_FLOW.md) ·
  [AI-assisted development](#ai-assisted-development) · [Changelog](#changelog) · [License](#license)

---

## Requirements

| | |
|---|---|
| **REDCap 14.6.4+** | The module declares External Modules framework version 16; REDCap will not enable it on an older release. LTS 15.0.9+ carries framework 16. |
| **HTTPS** | Google's geolocation and Places APIs will not run over plain HTTP. |
| **A Google Maps API key** | With **Places API (New)** enabled, plus a billing account. |

> **Framework floor.** Framework 13 is the meaningful floor for this module — that is where
> `getSubSettings()` began including settings marked `hidden`, which the address field sets rely
> on. Framework 16 is declared to keep the module installable on current LTS instances.

> **The "(New)" matters.** Google no longer enables the legacy Places API for newly issued keys,
> and this module has no legacy fallback. If your key only has the legacy Places API, the address
> box shows an error banner rather than autocompleting. Enable **Places API (New)** in the Google
> Cloud console for the key you use here.

## Installation

1. Copy `Google_Address_Autocomplete.php` and `config.json` into your REDCap modules directory:

   ```
   redcap/modules/<module_name>_v<version>/
   ```

   **Do not rename that directory.** REDCap derives the module's prefix from the folder name and
   reads the version from the `_v<version>` suffix. Renaming it creates what REDCap sees as a
   different module, and every project's saved settings are orphaned.

2. Enable the module in **Control Center → External Modules**, then enable it on your project.

3. Open **External Modules → Configure** on the project and fill in the settings below.

4. **Hard-refresh** the form (Ctrl+F5). The JavaScript is inlined into the page, so a cached page
   will keep running the old version.

> **When you deploy a change to `config.json`**, disable the module on the project and re-enable
> it. REDCap caches `config.json`, so a new or changed setting will otherwise not appear in the
> configuration dialog.

## Settings

All settings are per-project, under **External Modules → Configure**. They come in two parts: a
handful that apply to the whole project, and one repeating **Address Field Set** block that you
add once per address the project captures.

### Project-wide

| Setting | Required | Notes |
|---|---|---|
| **Google API Key** | ✅ | Your Maps Platform key, with Places API (New) enabled. One key covers every address field set. |
| **Import Google API** | | Checkbox. Emits the Google Maps bootstrap loader. Turn it **off** only if another module on the same page already loads the Maps API. The loader is emitted at most once per page however many sets are active. |
| **Privacy notice shown under the address box** | | Replaces the default notice wording. Shown under every set's search box. See [Privacy](#privacy). |
| **Hide the privacy notice** | | Checkbox, default off. Suppresses the notice. Only tick this if your consent form already covers the disclosure — read [Privacy](#privacy) first. |

### Per address field set

Use the **+** button beside **Address Field Set** to add another set.

**The fields you map.** Only **Autocomplete Field** is required; map as many or as few
destinations as your data dictionary needs.

| Setting | Required | Notes |
|---|---|---|
| **Autocomplete Field** | ✅ | The text field the widget attaches to. It is hidden and replaced visually by the Google widget, and receives the full formatted address on selection. |
| **Street Number Field** | | Street number. Also the destination for the recovered unit — see [Unit and apartment numbers](#unit-and-apartment-numbers). |
| **Street Field** | | Route / street name. |
| **City Field** | | Locality / suburb. |
| **County Field** | | `administrative_area_level_2`. The word "County" is stripped from the value. **Leave blank for Australian projects** — see the collision warning below. |
| **State Field** | | `administrative_area_level_1`, short form (e.g. `QLD`, `TX`). |
| **Zip Code Field** | | Postal code. |
| **Country Field** | | Country, long form (e.g. `Australia`). |
| **Latitude Field** / **Longitude Field** | | Coordinates of the selected place. |
| **Place Name Field** | | The place's display name, for named premises (e.g. `Brisbane Airport`, or a medical centre's name). |

**Scope and identification.** Worth setting even on a single-set project.

| Setting | Notes |
|---|---|
| **Instrument(s) this set applies to** | Repeating. Name the instrument this address lives on. Blank means "any form containing the Autocomplete Field". |
| **Name / description** | For your own reference. Also identifies the set in browser console messages, which is what makes two sets on one project debuggable. |
| **Disable this address field set** | Checkbox, default off. Keeps the mapping but stops the search box appearing. |

**Optional tuning.** All blank by default; each is compiled out of the emitted JavaScript when
left blank, so an unconfigured feature contributes no code and cannot misfire.

| Setting | Notes |
|---|---|
| **Recover unit/apartment number from typed text** | Checkbox. Parses the unit from what the participant typed when Google omits it. See [Unit and apartment numbers](#unit-and-apartment-numbers). |
| **Restrict predictions to region codes** | Comma-separated CLDR region codes, max 15, e.g. `au`. |
| **Restrict predictions to place types** | Comma-separated Google place types, max 5, e.g. `street_address,premise,subpremise`. Too narrow a value can suppress all predictions — verify in the browser console after setting it. |

Prediction filters are applied to the widget inside a `try`/`catch`: a value Google rejects
degrades to unfiltered predictions rather than breaking the field.

## Setting up your REDCap fields

**Field types.** Destination fields work as plain text, radio, dropdown/select, and REDCap's
autocomplete dropdowns. For coded fields the module tries, in order: an option whose *value*
matches exactly, the value with spaces replaced by underscores, an option literally valued
`Other`, and finally an option whose *label* matches. If none match, the participant sees an alert
and the field is left blank — so code your dropdowns to match Google's output, or use text fields.

**Street Number must be plain text when unit recovery is on.** The value becomes `3/27`, so any
integer or number validation on that field will reject it.

> ### ⚠️ Never map two settings to the same REDCap field
>
> Each destination element carries a single `googleSearch_*` id, so if two settings point at one
> field the later assignment wins and the other setting silently stops working. The common trap is
> **City + County**: for Australian addresses the council area overwrites the suburb. Leave
> **County** blank on AU projects.
>
> This applies **within** a set and **across** sets that appear on the same page. Two sets must
> each have their own destination fields — a shared field is logged as a warning but will still
> be overwritten. Sets on different instruments cannot collide.

**Multi-instrument projects are fine.** A set is not emitted at all on instruments it is not
scoped to, and even where it is emitted, the script exits early if its autocomplete field is not
on the form being viewed.

## Address field sets

One set is one address search box and the fields it fills. A project capturing a participant's
home address *and* their GP practice address configures two sets.

**Put each set on its own instrument.** Name that instrument under **Instrument(s) this set
applies to**, and nothing at all is emitted for the set on any other page. Two search boxes on
one page do work — they are fully isolated from each other — but they compete for the
participant's attention and each one asks the browser for location access separately.

Because each set carries its own prediction filters, you can tune them independently: a home
address set might use `street_address,subpremise`, while a medical centre set leaves the type
filter blank so named premises are predicted, and maps **Place Name Field** to capture the
practice name.

### Rules the module enforces

Sets are checked before anything is emitted. A misconfigured set is skipped and the reason is
written to the module log (**External Modules → View logs**); it never takes the rest of the
page down with it.

| Situation | What happens |
|---|---|
| Set has no **Autocomplete Field** | Skipped silently — an added-but-unfilled set is not an error. |
| Set is disabled | Skipped. |
| Set is scoped to other instruments | Not emitted on this page. |
| Two sets on one page share an **Autocomplete Field** | The later set is skipped and logged. Both would try to take over the same input. |
| Two sets on one page write to the same destination field | Both are emitted, and a warning is logged. One will overwrite the other — fix the mapping. |

## Unit and apartment numbers

Australian and UK unit addresses lose their unit number in the default Google response: typing
`3/27 Harris St` returns street number `27` with the unit dropped entirely. The module handles the
two independent causes separately.

**1. Google returned the unit, as a `subpremise` component.** Always handled. The raw component
list is scanned directly for `subpremise`, independently of the field mapping.

**2. Google omitted `subpremise` altogether.** This is a documented limitation of the Places API
for AU/UK addresses. When **Recover unit/apartment number from typed text** is enabled, the unit
is parsed out of what the participant actually typed, anchored to the street number Google *did*
return.

Either way, the result is written to the **Street Number Field** as `3/27`, and the formatted
address in the search field is patched to match. There is no separate unit field, so no data
dictionary change is needed.

### What the parser will and won't recover

It returns nothing rather than guessing.

| Typed into the search box | Street number from Google | Unit recovered |
|---|---|---|
| `3/27 Harris St, Bulimba QLD` | `27` | `3` |
| `Unit 3, 27 Harris St` | `27` | `3` |
| `Apt 3A/27 Harris St` | `27` | `3A` |
| `Flat 12 27 Harris St` | `27` | `12` |
| `Level 3, 27 Harris St` | `27` | `3` |
| `27 Harris St` | `27` | *(none)* — nothing precedes the street number |
| `Harris St 27` | `27` | *(none)* — the prefix is a street name, not a unit |
| `27-29 Harris St` | `27` | *(none)* — a street number range, not a unit |
| `The Old Rectory, 27 Harris St` | `27` | *(none)* — prefix does not end in a unit token |
| `The Grand Old Rectory Flat 3, 27 Harris St` | `27` | *(none)* — prefix too long to be a unit |
| `3/27 Harris St` | `7` | *(none)* — `7` is not matched inside `27` |

Each of those negative cases corresponds to a guard in `recoverUnitFromText()`: the street number
is matched on a word boundary, only the text *before* it is considered, that prefix is capped at
24 characters, a trailing hyphen marks a range, and the prefix must *end* with a unit token —
optionally introduced by `unit`, `apt`, `apartment`, `flat`, `suite`, `ste`, `shop`, `villa`,
`lot`, `level`, `lvl`, `room` or `rm`.

The typed text is consumed after each selection, so a later selection can never reuse a stale unit.

## How it behaves on the form

- **Destination fields are disabled on page load**, and each is re-enabled individually as a
  selection writes to it. This stops participants hand-editing the components and ensures REDCap
  only saves autocomplete-populated values. A field is enabled when it receives a value, and also
  when a blank has to overwrite something it already held — a disabled input is not submitted, so
  a field left disabled would keep whatever was saved against the record earlier. A field that was
  blank before the selection and is blank after it stays locked.
- **A selection replaces the whole address, not just the parts it supplies.** Every mapped field
  is cleared before the newly selected place is written, so components the new address does not
  have — a county, or the place name for an address that is not a named premises — are blanked
  rather than left behind from the previous selection. This is what stops a name or a district
  captured for one address staying attached to a different one.
- **The original address field is hidden, not removed.** It still submits, holding the full
  formatted address.
- **Clearing the search box clears everything.** Emptying the field wipes all destination fields,
  so a cleared search can never leave the previous address behind.
- **Branching logic re-runs** after any fill or clear, so fields conditional on the address
  appear and disappear correctly.
- **Predictions are biased toward the participant's location** if they grant the browser's
  geolocation prompt. Declining costs nothing but relevance.
- **A privacy notice appears under the address box** disclosing that typed text goes to Google.
  See [Privacy](#privacy).
- **If autocomplete fails while the participant is typing**, the box is replaced by a plain text
  input containing what they had typed, under a red banner. On a record being edited, the address
  component fields still hold the values saved last time — the module cannot know which of them the
  half-typed address should replace, so it unlocks them rather than guessing. Check them before
  saving.

## Securing your API key

**The key cannot be hidden, and you should not try.** The Maps JavaScript API runs in the
participant's browser, so the key has to reach the browser to work at all. Obfuscating it,
injecting it late, or fetching it over AJAX all fail the same way — it is still readable from the
network tab. Google's design assumes the key is public; the security boundary is the set of
**restrictions you place on it**, not the key's secrecy.

Work through all four of these in the [Google Cloud console](https://console.cloud.google.com/).
They are layers — none of them is sufficient alone.

**1. Restrict the key to your REDCap hostname.**
*Application restrictions → Websites.* Add your REDCap host, e.g. `https://redcap.example.edu/*`.
Include every hostname that serves surveys, and remember that survey links may use a different
public hostname from the one staff use.

> Understand what this does and does not do. Google enforces it on the `Referer` header, which is
> trivially forged with `curl`. It reliably stops someone lifting your key from the page source
> and using it on *their* website — the realistic threat. It does not stop a determined attacker
> who is willing to spoof headers, which is why the remaining layers matter.

**2. Restrict which APIs the key can call.**
*API restrictions → Restrict key.* Select only **Maps JavaScript API** and **Places API (New)**.
This bounds the blast radius: a lifted key cannot then be spent on Geocoding, Directions, Routes
or anything else on your billing account.

**3. Cap the spend.**
This is the layer people skip, and usually the one that matters most. The realistic harm from a
leaked Maps key is a surprise bill, not a data breach. Set a **budget with alerts** on the billing
account, and per-API **quota limits** (*APIs & Services → Quotas*) sized to your expected survey
volume. A hard cap turns an unbounded loss into a bounded one.

**4. Use a dedicated key for this module.**
Do not reuse a key that other applications depend on. If it is ever abused you can rotate this one
without coordinating an outage across unrelated systems.

### Two things specific to this module

- **If another module already loads the Maps API, this module need not emit the key at all.**
  Untick **Import Google API** and the widget uses whatever `google.maps.importLibrary` was
  already bootstrapped with. No key appears in the page source from this module.
- **The key is a project-level setting**, so anyone with module configuration rights on the
  project can read it in plain text in the configuration dialog. Treat "who can configure modules
  on this project" as "who can read the key", and use a separate key per project if that group is
  wider than you would like. One key serves every address field set on the project — the key is
  not configurable per set, because the Maps API can only be bootstrapped once per page.

## Privacy

**This module sends participant-entered text to Google.** Every keystroke in the address field
goes to the Google Maps Platform to generate the predictions, and the selected place is fetched
back from Google. That is a disclosure of personal information to a third party, and because
Google processes it overseas it is a **cross-border disclosure** — Australian Privacy Principle 8
for AU projects, GDPR Chapter V for UK/EU projects.

A survey participant sees what looks like an ordinary REDCap field, so the module discloses this
on the form itself. **A short notice appears under the address box by default:**

> Address suggestions come from Google. What you type in this box is sent to Google Maps to
> generate them.

You can replace that wording with your own under **Privacy notice shown under the address box**,
or suppress it with **Hide the privacy notice**. Only suppress it if your participant information
and consent form already discloses the transfer — that is your call to make, and your ethics
approval to hold.

The notice appears only when the widget actually loads. If autocomplete fails, nothing is sent to
Google and no notice is shown.

Two related points:

- **Geolocation** is requested through the browser's own permission prompt, so the participant
  gives or withholds that consent explicitly. Declining costs relevance, nothing else.
- **The API key is visible in the page source.** Inherent to the client-side Maps API — see
  [Securing your API key](#securing-your-api-key) for the layered mitigations.

Nothing is sent to Google when the form loads — only once the participant starts typing in the
address field.

## Troubleshooting

Browser console messages from the module are prefixed `[Address Autocomplete #n]`, where `n` is
the address field set's position (plus its name / description, if you gave it one) — that is how
you tell two sets apart on the same page.

**Nothing appears**

| Symptom | Cause and fix |
|---|---|
| Plain text box with a red "Address autocomplete could not load" banner | Either the Maps API never loaded (ad blocker — allow `googleapis.com`), or `PlaceAutocompleteElement` is missing, which means **Places API (New)** is not enabled for the key. Check the browser console for the specific message. |
| Nothing happens at all on one instrument | Expected — the module no-ops when the autocomplete field is not on the current form, or when no address field set is scoped to it. |
| One of several address field sets never appears | Check **External Modules → View logs**: a set with no Autocomplete Field, a disabled set, a set scoped to another instrument, or a set whose Autocomplete Field is already claimed by an earlier set is skipped deliberately. |
| Changes deployed but old behaviour persists | The JavaScript is inlined into the page. Hard-refresh with Ctrl+F5. |
| A new setting doesn't appear in the configuration dialog | REDCap caches `config.json`. Disable the module on the project and re-enable it. |

**Predictions don't work**

| Symptom | Cause and fix |
|---|---|
| Widget appears but no predictions ever show | Usually **Restrict predictions to place types** is too narrow. Clear it and retest. Also check the console for `gmp-error`, which indicates Google rejected the request — bad key, billing disabled, an API restriction that omits Places API (New), a quota cap already reached, or a referrer restriction that excludes your REDCap host. Up to two consecutive denials are tolerated and appear as console **warnings** with the widget left in place, since a momentary denial should not cost the participant autocomplete for the whole page; the third within ten seconds retires the widget and shows the red banner. See [Securing your API key](#securing-your-api-key). |
| Works for staff but not for survey participants | A referrer restriction that covers your internal REDCap hostname but not the public survey hostname. Add both. |

**Wrong or missing values**

| Symptom | Cause and fix |
|---|---|
| Suburb is being overwritten by the council area | City and County are mapped to the same REDCap field. Leave County blank. |
| Street number saved as `27` when `3/27` was typed | Enable **Recover unit/apartment number from typed text**, and confirm the Street Number Field has no integer/number validation. |
| Values look right on screen but don't save | Something wrote a value without re-enabling the field; disabled inputs are not submitted. Check the console for "Could not find the element with the following id". |
| `alert` saying a value is not valid for a field | Google returned a value your dropdown/radio has no matching option for. Add the option, or use a text field. |
| Two address boxes on one page fill each other's fields | Two sets are mapped to the same destination field. The module logs this; fix the mapping so each set has its own fields. |

## Development

There is no build system or package manager. Tests are a single PHP script with no dependencies.

[EXECUTION_FLOW.md](https://github.com/274188A/google_address_autocomplete/blob/main/EXECUTION_FLOW.md)
traces a page render end to end — which address field sets are emitted server-side, how the Google
widget is attached to the form, and what a selection writes where — with a diagram and its exit
paths for each of the three phases. The link is absolute because this README is also rendered by
REDCap's **View Documentation**, where a relative path would not resolve and the diagrams would not
render.

### Golden-output harness

The module's only observable behaviour is the markup it echoes, so correctness under refactoring
is checkable directly: for identical settings input, the output must be byte-identical.

```
php tests/golden.php verify     # exit 1 on any mismatch
php tests/golden.php capture    # re-record, only when a change to the markup is intended
```

Fifteen fixtures in `tests/fixtures.php` cover one and two sets, each optional field mapping,
the privacy-notice states, instrument scoping, and both misconfiguration warnings. Log messages
are captured alongside the markup, so a change that stops warning about a misconfigured set fails
too. It runs against a stub of `AbstractExternalModule` implementing the three framework methods
the module calls, so no REDCap installation is needed. See [tests/README.md](tests/README.md).

The `sparse-missing-keys` fixture is the one to keep working: it supplies only the required
`set-autocomplete` key, reproducing what REDCap returns when a setting was added to `config.json`
after a project was configured. The module's defensive reads exist for that case.

### Limits

The harness proves the emitted markup is unchanged. It does not execute the emitted JavaScript,
and it cannot tell you whether REDCap autoloads sibling classes in the module namespace — which
is why `AddressFieldSet` and `AddressComponent` are `require_once`d explicitly.

`php -l` is likewise a syntax check, not a compatibility check. The maintainer's PHP is 8.5 while
`config.json` sets a floor of 8.2, so 8.3+ syntax lints clean locally and would fatal on the
server. That ceiling has to be held by review.

Changes to the emitted JavaScript still warrant a browser check on a real form: the PHP
conditionals produce materially different JavaScript depending on which settings are mapped, and
an unsubstituted `<?php` tag or an empty assignment (`var x = ;`) kills the whole inline script.

## AI-assisted development

Parts of this module — code, review and documentation, including this README — were produced
with assistance from [Claude](https://claude.ai) (Anthropic), used via Claude Code. Everything
was reviewed by the maintainer before release, and behaviour is verified against a live REDCap
instance. Responsibility for the code rests with the maintainer, not the tool.

## Changelog

See [CHANGELOG.md](CHANGELOG.md). Note that REDCap reads the deployed version from the module
directory name (`_v<version>`), not from `config.json` — so a release means renaming the
deployment directory and tagging the commit to match.

## License

MIT — see [LICENSE](LICENSE). Copyright © 2026 John Barrett.
