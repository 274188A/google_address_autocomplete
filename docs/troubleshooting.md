# Troubleshooting

[← Back to the README](../README.md)

Browser console messages from the module are prefixed `[Address Autocomplete #n]`, where `n` is
the address field set's position (plus its name / description, if you gave it one) — that is how
you tell two sets apart on the same page.

## Nothing appears

| Symptom | Cause and fix |
|---|---|
| Plain text box with a red "Address autocomplete could not load" banner | Either the Maps API never loaded (ad blocker — allow `googleapis.com`), or `PlaceAutocompleteElement` is missing, which means **Places API (New)** is not enabled for the key. Check the browser console for the specific message. |
| Nothing happens at all on one instrument | Expected — the module no-ops when the autocomplete field is not on the current form, or when no address field set is scoped to it. |
| One of several address field sets never appears | Check **External Modules → View logs**: a set with no Autocomplete Field, a disabled set, a set scoped to another instrument, or a set whose Autocomplete Field is already claimed by an earlier set is skipped deliberately. See [Rules the module enforces](fields-and-sets.md#rules-the-module-enforces). |
| Changes deployed but old behaviour persists | The JavaScript is inlined into the page. Hard-refresh with Ctrl+F5 — see [After deploying a change](installation.md#after-deploying-a-change). |
| A new setting doesn't appear in the configuration dialog | REDCap caches `config.json`. Disable the module on the project and re-enable it — see [After deploying a change](installation.md#after-deploying-a-change). |

## Predictions don't work

| Symptom | Cause and fix |
|---|---|
| Widget appears but no predictions ever show | Usually **Restrict predictions to place types** is too narrow. Clear it and retest. Also check the console for `gmp-error`, which indicates Google rejected the request — bad key, billing disabled, an API restriction that omits Places API (New), a quota cap already reached, or a referrer restriction that excludes your REDCap host. Up to two consecutive denials are tolerated and appear as console **warnings** with the widget left in place, since a momentary denial should not cost the participant autocomplete for the whole page; the third within ten seconds retires the widget and shows the red banner. See [Securing your API key](security-and-privacy.md#securing-your-api-key). |
| Works for staff but not for survey participants | A referrer restriction that covers your internal REDCap hostname but not the public survey hostname. Add both. |

## Wrong or missing values

| Symptom | Cause and fix |
|---|---|
| Suburb is being overwritten by the council area | City and County are mapped to the same REDCap field. Leave County blank — see [Never map two settings to the same REDCap field](fields-and-sets.md#never-map-two-settings-to-the-same-redcap-field). |
| Street number saved as `27` when `3/27` was typed | Enable **Recover unit/apartment number from typed text**, and confirm the Street Number Field has no integer/number validation. See [Unit and apartment numbers](form-behaviour.md#unit-and-apartment-numbers). |
| Values look right on screen but don't save | Something wrote a value without re-enabling the field; disabled inputs are not submitted. Check the console for "Could not find the element with the following id". |
| `alert` saying a value is not valid for a field | Google returned a value your dropdown/radio has no matching option for. Add the option, or use a text field. See [field types](fields-and-sets.md#setting-up-your-redcap-fields). |
| Two address boxes on one page fill each other's fields | Two sets are mapped to the same destination field. The module logs this; fix the mapping so each set has its own fields. |

## Still stuck

[Execution flow](execution-flow.md) traces a page render end to end, with every exit and degrade
path marked — useful for working out *which* step a failure stopped at.
