# Security and privacy

[← Back to the README](../README.md)

Work through both halves of this page before going live.

## Securing your API key

**The key cannot be hidden, and you should not try.** The Maps JavaScript API runs in the
participant's browser, so the key has to reach the browser to work at all. Obfuscating it,
injecting it late, or fetching it over AJAX all fail the same way — it is still readable from the
network tab. Google's design assumes the key is public; the security boundary is the set of
**restrictions you place on it**, not the key's secrecy.

Work through all four of these in the [Google Cloud console](https://console.cloud.google.com/).
They are layers — none of them is sufficient alone.

### 1. Restrict the key to your REDCap hostname

*Application restrictions → Websites.* Add your REDCap host, e.g. `https://redcap.example.edu/*`.
Include every hostname that serves surveys, and remember that survey links may use a different
public hostname from the one staff use.

<details>
<summary><strong>What this does and does not do</strong> — it stops the realistic threat, not a determined one</summary>

Google enforces it on the `Referer` header, which is trivially forged with `curl`. It reliably
stops someone lifting your key from the page source and using it on *their* website — the
realistic threat. It does not stop a determined attacker who is willing to spoof headers, which
is why the remaining layers matter.

</details>

### 2. Restrict which APIs the key can call

*API restrictions → Restrict key.* Select only **Maps JavaScript API** and **Places API (New)**.
This bounds the blast radius: a lifted key cannot then be spent on Geocoding, Directions, Routes
or anything else on your billing account.

### 3. Cap the spend

This is the layer people skip, and usually the one that matters most. The realistic harm from a
leaked Maps key is a surprise bill, not a data breach. Set a **budget with alerts** on the billing
account, and per-API **quota limits** (*APIs & Services → Quotas*) sized to your expected survey
volume. A hard cap turns an unbounded loss into a bounded one.

### 4. Use a dedicated key for this module

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
- **The API key is visible in the page source.** That is inherent to the client-side Maps API;
  the layered mitigations are in [Securing your API key](#securing-your-api-key) above.

Nothing is sent to Google when the form loads — only once the participant starts typing in the
address field.

## Next

- [Settings](settings.md) — the privacy-notice settings themselves
- [Troubleshooting](troubleshooting.md)
