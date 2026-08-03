# How it behaves on the form

[← Back to the README](../README.md)

## What the participant sees

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
  See [Privacy](security-and-privacy.md#privacy).
- **If autocomplete fails while the participant is typing**, the box is replaced by a plain text
  input containing what they had typed, under a red banner. On a record being edited, the address
  component fields still hold the values saved last time — the module cannot know which of them the
  half-typed address should replace, so it unlocks them rather than guessing. Check them before
  saving.

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
dictionary change is needed — but that field
[must not carry integer or number validation](fields-and-sets.md#setting-up-your-redcap-fields).

The typed text is consumed after each selection, so a later selection can never reuse a stale unit.

<details>
<summary><strong>What the parser will and won't recover</strong> — it returns nothing rather than guessing (11 worked examples)</summary>

| Typed into the search box | Street number from Google | Unit recovered |
|---|---|---|
| `3/27 Harris St, Bulimba QLD` | `27` | `3` |
| `Unit 3, 27 Harris St` | `27` | `3` |
| `Apt 3A/27 Harris St` | `27` | `3A` |
| `Flat 12 27 Harris St` | `27` | `12` |
| `Level 3, 27 Harris St` | `27` | `3` |
| `12/2 Smith St` | `2` | `12` — the `2` inside `12` is not mistaken for the street number |
| `27 Harris St` | `27` | *(none)* — nothing precedes the street number |
| `Harris St 27` | `27` | *(none)* — the prefix is a street name, not a unit |
| `27-29 Harris St` | `27` | *(none)* — a street number range, not a unit |
| `The Old Rectory, 27 Harris St` | `27` | *(none)* — prefix does not end in a unit token |
| `The Grand Old Rectory Flat 3, 27 Harris St` | `27` | *(none)* — prefix too long to be a unit |

The street number is located on a word boundary, so it is never matched inside a longer number —
that is what keeps the unit correct in the `12/2` case above. Each of the negative cases
corresponds to a further guard in `recoverUnitFromText()`: only the text *before* the street
number is considered, that prefix is capped at 24 characters, a trailing hyphen marks a range,
and the prefix must *end* with a unit token — optionally introduced by `unit`, `apt`,
`apartment`, `flat`, `suite`, `ste`, `shop`, `villa`, `lot`, `level`, `lvl`, `room` or `rm`.

</details>

## Next

- [Troubleshooting](troubleshooting.md)
- [Execution flow](execution-flow.md) — the same behaviour traced step by step, with diagrams
