# Installation

[← Back to the README](../README.md)

## Requirements

| | |
|---|---|
| **REDCap 14.6.4+** | The module declares External Modules framework version 16; REDCap will not enable it on an older release. LTS 15.0.9+ carries framework 16. |
| **HTTPS** | Google's geolocation and Places APIs will not run over plain HTTP. |
| **A Google Maps API key** | With **Places API (New)** enabled, plus a billing account. |

> **The "(New)" matters.** Google no longer enables the legacy Places API for newly issued keys,
> and this module has no legacy fallback. If your key only has the legacy Places API, the address
> box shows an error banner rather than autocompleting. Enable **Places API (New)** in the Google
> Cloud console for the key you use here.

<details>
<summary><strong>Why framework 16 is declared when framework 13 is the real floor</strong></summary>

Framework 13 is the meaningful floor for this module — that is where `getSubSettings()` began
including settings marked `hidden`, which the [address field sets](fields-and-sets.md) rely on.
Framework 16 is declared to keep the module installable on current LTS instances.

</details>

## Steps

1. Copy `Google_Address_Autocomplete.php` and `config.json` into your REDCap modules directory:

   ```
   redcap/modules/<module_name>_v<version>/
   ```

   **Do not rename that directory.** REDCap derives the module's prefix from the folder name and
   reads the version from the `_v<version>` suffix. Renaming it creates what REDCap sees as a
   different module, and every project's saved settings are orphaned.

2. Enable the module in **Control Center → External Modules**, then enable it on your project.

3. Open **External Modules → Configure** on the project and fill in the
   [settings](settings.md).

4. **Hard-refresh** the form (Ctrl+F5). The JavaScript is inlined into the page, so a cached page
   will keep running the old version.

## After deploying a change

Two caching behaviours catch people out, and both have the same symptom — "I changed it and
nothing happened".

| You changed | What to do |
|---|---|
| `Google_Address_Autocomplete.php` (any emitted JavaScript or markup) | **Hard-refresh the form with Ctrl+F5.** The JavaScript is inlined into the page rather than served as a separate file, so the browser's cached copy of the page is a cached copy of the script. |
| `config.json` (a new or renamed setting) | **Disable the module on the project and re-enable it.** REDCap caches `config.json`, so the setting will otherwise not appear in the configuration dialog. |

## Next

- [Settings](settings.md) — what to configure, and what each setting does
- [Setting up your REDCap fields](fields-and-sets.md) — field types that work, and the one
  mapping mistake to avoid
- [Securing your API key](security-and-privacy.md) — work through this before going live
