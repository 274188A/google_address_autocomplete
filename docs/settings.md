# Settings

[← Back to the README](../README.md)

All settings are per-project, under **External Modules → Configure**. They come in two parts: a
handful that apply to the whole project, and one repeating **Address Field Set** block that you
add once per address the project captures.

> If a setting you expect is missing from the dialog, REDCap is serving a cached `config.json` —
> see [After deploying a change](installation.md#after-deploying-a-change).

## Project-wide

| Setting | Required | Notes |
|---|---|---|
| **Google API Key** | ✅ | Your Maps Platform key, with Places API (New) enabled. One key covers every address field set. |
| **Import Google API** | | Checkbox. Emits the Google Maps bootstrap loader. Turn it **off** only if another module on the same page already loads the Maps API. The loader is emitted at most once per page however many sets are active. |
| **Privacy notice shown under the address box** | | Replaces the default notice wording. Shown under every set's search box. See [Privacy](security-and-privacy.md#privacy). |
| **Hide the privacy notice** | | Checkbox, default off. Suppresses the notice. Only tick this if your consent form already covers the disclosure — read [Privacy](security-and-privacy.md#privacy) first. |

## Per address field set

Use the **+** button beside **Address Field Set** to add another set. See
[Address field sets](fields-and-sets.md#address-field-sets) for how sets interact.

### The fields you map

Only **Autocomplete Field** is required; map as many or as few destinations as your data
dictionary needs.

| Setting | Required | Notes |
|---|---|---|
| **Autocomplete Field** | ✅ | The text field the widget attaches to. It is hidden and replaced visually by the Google widget, and receives the full formatted address on selection. |
| **Street Number Field** | | Street number. Also the destination for the recovered unit — see [Unit and apartment numbers](form-behaviour.md#unit-and-apartment-numbers). |
| **Street Field** | | Route / street name. |
| **City Field** | | Locality / suburb. |
| **County Field** | | `administrative_area_level_2`. The word "County" is stripped from the value. **Leave blank for Australian projects** — the council area would overwrite the suburb. See [Never map two settings to the same REDCap field](fields-and-sets.md#never-map-two-settings-to-the-same-redcap-field). |
| **State Field** | | `administrative_area_level_1`, short form (e.g. `QLD`, `TX`). |
| **Zip Code Field** | | Postal code. |
| **Country Field** | | Country, long form (e.g. `Australia`). |
| **Latitude Field** / **Longitude Field** | | Coordinates of the selected place. |
| **Place Name Field** | | The place's display name, for named premises (e.g. `Brisbane Airport`, or a medical centre's name). |

### Scope and identification

Worth setting even on a single-set project.

| Setting | Notes |
|---|---|
| **Instrument(s) this set applies to** | Repeating. Name the instrument this address lives on. Blank means "any form containing the Autocomplete Field". |
| **Name / description** | For your own reference. Also identifies the set in browser console messages, which is what makes two sets on one project debuggable. |
| **Disable this address field set** | Checkbox, default off. Keeps the mapping but stops the search box appearing. |

### Optional tuning

All blank by default; each is compiled out of the emitted JavaScript when left blank, so an
unconfigured feature contributes no code and cannot misfire.

| Setting | Notes |
|---|---|
| **Recover unit/apartment number from typed text** | Checkbox. Parses the unit from what the participant typed when Google omits it. See [Unit and apartment numbers](form-behaviour.md#unit-and-apartment-numbers). |
| **Restrict predictions to region codes** | Comma-separated CLDR region codes, max 15, e.g. `au`. |
| **Restrict predictions to place types** | Comma-separated Google place types, max 5, e.g. `street_address,premise,subpremise`. Too narrow a value can suppress all predictions — verify in the browser console after setting it. |

Prediction filters are applied to the widget inside a `try`/`catch`: a value Google rejects
degrades to unfiltered predictions rather than breaking the field.

## Next

- [Setting up your REDCap fields](fields-and-sets.md) — field types, and the mapping rule
- [How it behaves on the form](form-behaviour.md) — what the participant sees
- [Troubleshooting](troubleshooting.md)
