# Golden-output harness

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

## Scope

This runs **outside** REDCap against a stub. It proves the emitted markup is unchanged. It does
not prove the module works on a real server — in particular it cannot tell you whether REDCap
autoloads sibling classes in the module namespace, which is why the module `require_once`s them
explicitly. Nor does it execute the emitted JavaScript.

`php -l` is likewise a syntax check, not a compatibility check: the local PHP is 8.5, but
`config.json` sets a floor of 8.2, so 8.3+ syntax will lint clean here and fatal on the server.
That ceiling has to be held by review.
