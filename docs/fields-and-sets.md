# Fields and address field sets

[← Back to the README](../README.md)

## Setting up your REDCap fields

**Field types.** Destination fields work as plain text, radio, dropdown/select, and REDCap's
autocomplete dropdowns. For coded fields the module tries, in order: an option whose *value*
matches exactly, the value with spaces replaced by underscores, an option literally valued
`Other`, and finally an option whose *label* matches. If none match, the participant sees an alert
and the field is left blank — so code your dropdowns to match Google's output, or use text fields.

**Street Number must be plain text when unit recovery is on.** The value becomes `3/27`, so any
integer or number validation on that field will reject it. See
[Unit and apartment numbers](form-behaviour.md#unit-and-apartment-numbers).

**Multi-instrument projects are fine.** A set is not emitted at all on instruments it is not
scoped to, and even where it is emitted, the script exits early if its autocomplete field is not
on the form being viewed.

### Never map two settings to the same REDCap field

> ⚠️ **This one fails silently.** Nothing errors, nothing is logged within a set — a mapped
> field just stops receiving its value.

Each destination element carries a single `googleSearch_*` id, so if two settings point at one
field the later assignment wins and the other setting silently stops working. The common trap is
**City + County**: for Australian addresses the council area overwrites the suburb. Leave
**County** blank on AU projects.

This applies **within** a set and **across** sets that appear on the same page. Two sets must
each have their own destination fields — a shared field is logged as a warning but will still
be overwritten. Sets on different instruments cannot collide.

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

## Next

- [How it behaves on the form](form-behaviour.md) — what the participant sees, and unit numbers
- [Troubleshooting](troubleshooting.md)
- [Execution flow](execution-flow.md) — which sets are emitted, and what a selection writes where
