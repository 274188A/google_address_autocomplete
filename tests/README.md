# Tests

Two harnesses, neither needing a REDCap server:

| Harness | Runs | Proves |
|---|---|---|
| Golden output (`golden.php`) | PHP | The emitted markup is byte-identical for identical settings |
| Unit (`unit.test.mjs`) | Node | The pure JavaScript helpers still behave as they do today |

```
C:\tools\php85\php.exe tests/golden.php verify
node --test "tests/*.test.mjs"
```

Both exit non-zero on failure, so either works as a pre-commit gate. **Run both** — they cover
different layers and neither catches the other's regressions.

## Golden-output harness

The module's entire job is to `echo` an inline `<script>` into a REDCap page. That makes
refactoring checkable without a REDCap server: **for identical settings input, the emitted
output must be byte-identical before and after.**

```
php tests/golden.php capture    # write tests/golden/<fixture>.html
php tests/golden.php verify     # re-render and diff against them (exit 1 on any mismatch)
```

PHP is not on `PATH` on the maintainer's machine — invoke it by full path:

```
C:\tools\php85\php.exe tests/golden.php verify
```

## Layout

| Path | Purpose |
|---|---|
| `stub/AbstractExternalModule.php` | ~40-line stand-in for the REDCap framework base class. Implements only `getProjectSetting()`, `getSubSettings()` and `log()` — the three methods the module calls. |
| `extract.mjs` | Lifts a named JS function out of the PHP source for the unit tests. |
| `unit.test.mjs` | The unit tests. |
| `fixtures.php` | The settings scenarios. `gaa_full_set()` is a fully populated address set; the rest vary one thing from it. |
| `golden.php` | Renders each fixture and captures or verifies. |
| `golden/*.html` | Committed expected output. Regenerate with `capture` **only** when a change to the emitted markup is intended. |

`golden/*.html.actual` files are written on mismatch so you can diff with a real tool. They are
scratch — delete them, don't commit them.

## What the fixtures cover

One set / two sets; unit recovery on/off; lat-lng, place-name and prediction filters
mapped/unmapped; privacy notice default/custom/suppressed; bootstrap loader on/off; a disabled
set; instrument scoping (matched, unmatched, and blank-means-any); missing API key; and both
misconfiguration warnings (two sets sharing a source field, two sets sharing a destination field).

`sparse-missing-keys` is the important one. It supplies **only** the required `set-autocomplete`
key, reproducing what REDCap returns when a key was added to `config.json` after a project was
configured. The module's defensive `?? ''` reads exist for exactly this case, so any refactor
that makes a constructor parameter required will fail here rather than in production.

Log messages are captured into the golden files too, as an HTML comment — a refactor that
silently stops warning about a duplicate destination field is a regression the markup alone
would not reveal.

## Unit tests

```
node --test "tests/*.test.mjs"
```

Node 18+, no dependencies and no `package.json` — `node:test` and `node:assert` are built in.
Quote the glob: bare `node --test tests/` tries to execute the PHP files in this directory.

These cover `recoverUnitFromText()` and the `escapeRegExp()` it depends on: the unit-recovery
parser, which is pure, regex-heavy, and the most intricate logic in the module. Everything the
golden harness cannot reach, because the golden files only prove those regexes were *emitted*,
never that they *work*.

**The functions are extracted from `Google_Address_Autocomplete.php` at run time, not copied.**
`extract.mjs` finds `function <name>(`, brace-matches to the end, and evaluates the result in a
bare `vm` context. A copy pasted into the test file would drift from the source silently — the
tests would keep passing while exercising code the module no longer ships. Extraction means
editing the shipped function is what the tests respond to. Renaming it fails with a message
naming the function, rather than silently testing nothing.

The bare context provides no DOM, no jQuery and no globals, so only genuinely pure helpers can be
tested this way; anything else throws on its first `document` reference. A function containing a
`<?php ?>` block is rejected outright rather than tested in a mangled form.

They are **characterisation** tests: they pin what the code does today so a refactor that changes
behaviour fails here. They are not a specification. Two pinned behaviours are deliberate and worth
knowing about before you "fix" them:

- `recoverUnitFromText('7/27 Harris St', '7')` returns `''`. The street number must match on a word
  boundary, so `7` does not match inside `27`, and no unit is recovered even though the text plainly
  contains one. This is the guard that stops `27` being read as a unit working as intended.
- A unit prefix longer than 24 characters is rejected as a building name. The fixture asserts its
  own length so that a miscount fails loudly instead of testing the wrong boundary.

## Scope

This runs **outside** REDCap against a stub. The golden harness proves the emitted markup is
unchanged; the unit tests prove the extracted pure helpers still behave the same. Neither proves
the module works on a real server — in particular they cannot tell you whether REDCap autoloads
sibling classes in the module namespace, which is why the module `require_once`s them explicitly.

The emitted JavaScript is still largely unexecuted: everything touching the DOM, jQuery or the
Google Places API — `fillInAddress()`, `updateValue()`, the widget lifecycle and the degrade path —
is covered by neither harness and has to be held by review and by testing on a real form.

`php -l` is likewise a syntax check, not a compatibility check: the local PHP is 8.5, but
`config.json` sets a floor of 8.2, so 8.3+ syntax will lint clean here and fatal on the server.
That ceiling has to be held by review.
