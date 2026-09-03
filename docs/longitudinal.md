# Longitudinal projects

[← Back to the README](../README.md)

An address field set can be scoped to events as well as instruments, so the same instrument can
carry a different address at baseline and at follow-up.

## Scoping a set to events

Each address field set carries two independent scope settings, and **both must pass** before the
search box is emitted on a page:

- **Instrument(s) this set applies to** — blank means any form containing the Autocomplete Field.
- **Event(s) this set applies to** — blank means any event. Longitudinal projects only; the
  picker is hidden on projects without events.

| Instrument scope | Event scope | Where the search box appears |
|---|---|---|
| blank | blank | Every page whose form contains the Autocomplete Field |
| `contact_details` | blank | That instrument, at **every event it is designated to** |
| blank | `Follow-up` | Any form containing the field, but only at that event |
| `contact_details` | `Follow-up` | That instrument, only at that event |

**A blank event does not mean every event in the project.** With an instrument named and the event
left blank, the set applies at every event *that instrument is designated to*. Events where the
instrument is not designated never render the form at all, so there is nothing for the module to
attach to.

This is what makes the setting backwards-compatible: a set configured before events existed has no
event scope, reads as blank, and keeps behaving exactly as it did.

### When you need it

Name an event when one instrument is designated to several events and you want the address
captured at only some of them — for example a `contact_details` form collected at baseline, 6
months and 12 months, where the address should be searchable at baseline only.

You do **not** need it to make the module work at a later event. Nothing about the module is
tied to the first event: REDCap renders the same field names at every event, and the module
attaches to the fields on whatever page is open. If a set works at one event and not another,
the cause is the scoping above or a field that is not on that instrument — not the event itself.
Check the browser console for `[Address Autocomplete #n]`, which names the field it looked for.

### Two sets on one instrument

Because the filters are independent, two sets can share an instrument and even the same
destination fields, provided they name **different events**. The event filter is applied before
the module checks for sets competing over the same fields, so the set belonging to another event
is out of the way before that check runs.

### What event scoping is not

It decides **where the search box appears**. It does not write into another event's fields — the
module fills the fields on the page in front of the participant, and REDCap saves them when the
form is submitted. There is no cross-event write.

## Next

- [Settings](settings.md) — every setting, and what it does
- [Fields and address field sets](fields-and-sets.md) — the mapping rule, and the collision rules
- [Troubleshooting](troubleshooting.md)
