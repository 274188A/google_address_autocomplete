# Development

[← Back to the README](../README.md)

There is no build system and no package manager. Deployment is copying two files —
see [Installation](installation.md).

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
C:\tools\php85\php.exe tests/golden.php verify
node --test tests/unit.test.mjs
```

Both exit non-zero on failure, so either works as a pre-commit gate. PHP is not on `PATH` on the
maintainer's machine, hence the full path above.

**[tests/README.md](../tests/README.md) is the authoritative reference** for both harnesses —
layout, what each fixture covers, how the unit tests extract functions from the PHP source, and
which current behaviours are pinned deliberately. What follows is the short version.

### Golden output

The module's only observable behaviour is the markup it echoes, so correctness under refactoring
is checkable directly: for identical settings input, the output must be byte-identical.

```
C:\tools\php85\php.exe tests/golden.php verify    # exit 1 on any mismatch
C:\tools\php85\php.exe tests/golden.php capture   # re-record, only when a markup change is intended
```

Fifteen fixtures in `tests/fixtures.php` cover one and two sets, each optional field mapping, the
privacy-notice states, instrument scoping, and both misconfiguration warnings. Log messages are
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

`php -l` is likewise a syntax check, not a compatibility check. The maintainer's PHP is 8.5 while
`config.json` sets a floor of 8.2, so 8.3+ syntax lints clean locally and would fatal on the
server. That ceiling has to be held by review.

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
