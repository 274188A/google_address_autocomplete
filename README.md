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

## Requirements

| | |
|---|---|
| **REDCap 14.6.4+** | The module declares External Modules framework version 16; REDCap will not enable it on an older release. LTS 15.0.9+ carries framework 16. |
| **HTTPS** | Google's geolocation and Places APIs will not run over plain HTTP. |
| **A Google Maps API key** | With **Places API (New)** enabled, plus a billing account. Google no longer enables the *legacy* Places API for newly issued keys, and this module has no legacy fallback. |

## Quick start

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

Not working? Go straight to
[Troubleshooting](https://github.com/274188A/google_address_autocomplete/blob/main/docs/troubleshooting.md).

> **Before going live**, work through
> [Security and privacy](https://github.com/274188A/google_address_autocomplete/blob/main/docs/security-and-privacy.md).
> The API key is visible in the page source — inherent to the client-side Maps API — and the
> module sends participant-typed text to Google, which is a disclosure you may need to declare.

## Documentation

| Page | Read it when |
|---|---|
| [Installation](https://github.com/274188A/google_address_autocomplete/blob/main/docs/installation.md) | Deploying the module, or a change you deployed hasn't taken effect |
| [Settings](https://github.com/274188A/google_address_autocomplete/blob/main/docs/settings.md) | Configuring a project — every setting, and what it does |
| [Fields and address field sets](https://github.com/274188A/google_address_autocomplete/blob/main/docs/fields-and-sets.md) | Choosing which REDCap fields to map, or capturing more than one address |
| [How it behaves on the form](https://github.com/274188A/google_address_autocomplete/blob/main/docs/form-behaviour.md) | You want to know what the participant sees — and how unit/apartment numbers are recovered |
| [Security and privacy](https://github.com/274188A/google_address_autocomplete/blob/main/docs/security-and-privacy.md) | **Before going live.** Locking down the API key, and the disclosure to participants |
| [Troubleshooting](https://github.com/274188A/google_address_autocomplete/blob/main/docs/troubleshooting.md) | Something isn't working |
| [Execution flow](https://github.com/274188A/google_address_autocomplete/blob/main/docs/execution-flow.md) | Tracing a page render end to end, with a diagram per phase |
| [Development](https://github.com/274188A/google_address_autocomplete/blob/main/docs/development.md) | Changing the code — test harnesses, limits, docs layout |

## About this project

Parts of this module — code, review and documentation — were produced with assistance from
[Claude](https://claude.ai) (Anthropic), used via Claude Code. Everything was reviewed by the
maintainer before release, and behaviour is verified against a live REDCap instance.
Responsibility for the code rests with the maintainer, not the tool.

**Changelog:**
[CHANGELOG.md](https://github.com/274188A/google_address_autocomplete/blob/main/CHANGELOG.md).
Note that REDCap reads the deployed version from the module directory name (`_v<version>`), not
from `config.json` — so a release means renaming the deployment directory and tagging the commit
to match.

**License:** MIT — see
[LICENSE](https://github.com/274188A/google_address_autocomplete/blob/main/LICENSE).
Copyright © 2026 John Barrett.
