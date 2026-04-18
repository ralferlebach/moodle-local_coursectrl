# Moodle Coding Standards — Claude System Prompt
# Course Control Hub Project (`local_coursectrl`)
# Place this file in: docs/prompt-templates/coding-standards.md
# Version: 2026-04-16 (Session 006 — all accumulated rules)

---

## PHP — Moodle CodeSniffer Standard (`moodle` ruleset, PHP 8.2+)

### File structure
- Every PHP file begins with the GPL-3 boilerplate comment block, then a `/** @package ... */` file-level docblock.
- `defined('MOODLE_INTERNAL') || die();` immediately after the docblock in non-entry-point files (lang files, version.php, lib.php, etc.).
- Entry-point files (`index.php`, `manage.php`, …) begin with `require_once(__DIR__ . '/../../config.php');`.
- One class per file. Filename must match class name exactly (e.g. `class rollback_manager` → `rollback_manager.php`).
- Namespace matches directory path: `local_coursectrl\local\analysis` → `classes/local/analysis/`.

### End-of-file newline — SILENT CI KILLER
**Rule (PSR-2): Every PHP file must end with exactly ONE `\n` (LF). No trailing blank lines.**

```php
// WRONG — two trailing newlines (PSR2.Files.EndFileNewline.TooMany):
class foo {
}
[blank line here]

// RIGHT — exactly one LF after the last closing brace:
class foo {
}
```

**Prevention:** When generating files with a script, always call `.rstrip('\n') + '\n'` on the output before writing. When using `str_replace`, ensure the replacement does NOT end with more than one `\n`.

### Class closing brace — COMMON CI FAILURE
**Rule (PSR-2): The closing `}` of a class must come immediately after the last method body — no blank line between them.**

```php
// WRONG (PSR2.Classes.ClassDeclaration.CloseBraceAfterBody):
class foo {
    public function bar(): void {
    }

}

// RIGHT:
class foo {
    public function bar(): void {
    }
}
```

**Most common cause:** Generated adapters with no date fields (page, h5pactivity) where the trait_hooks template section is empty.

### Docblocks
- Every file, class, method, and property has a docblock.
- `@param`, `@return`, `@throws` for every non-trivial method.
- `@covers \Full\Class\Name` on every PHPUnit test class.
- Subplugin packages: `@package coursectrlmod_<modname>` or `@package coursectrlcal_<n>`.
- **Every class constant needs its OWN docblock** — one shared block for multiple `const` is not accepted:

```php
// WRONG (moodle.Commenting.MissingDocblock.Constant):
/**
 * Navigation key constants.
 */
public const KEY_A = 'a';
public const KEY_B = 'b';

// RIGHT:
/** @var string Key for page A. */
public const KEY_A = 'a';
/** @var string Key for page B. */
public const KEY_B = 'b';
```

### Inline comments
- **Must start with a capital letter, a digit, or `...`** (three dots).
- WRONG: `// timeopen is the open date.` → RIGHT: `// Timeopen is the open date.`
- WRONG: `// mod_capquiz: single field.` → RIGHT: `// CAPQuiz has a single field.`
- No blank line immediately after an opening `{`.

### Arrays and short syntax
- Always use short array syntax: `[]` not `array()`.
- `list()` → `[$a, $b] = ...`.

### Multi-line function/constructor calls — MOST COMMON CI FAILURE
**Rule: In a multi-line call, each continuation line must contain exactly ONE argument.**

```php
// WRONG:
return new cm_item(
    $cmid, 1, 10, 'assign', $cmid, 'Activity',   // ← PHPCS ERROR
    true, null, 2
);

// RIGHT:
return new cm_item(
    $cmid,
    1,
    10,
    ...
);
```

### Multi-line control structures — SECOND MOST COMMON CI FAILURE
**Practical rule: Never embed a multi-line array literal directly inside `foreach()`, `if()`, or `while()`. Assign it to a variable first.**

```php
// WRONG:
foreach (['key1', 'key2', 'key3'] as $key) {  // breaks if array spans lines

// RIGHT:
$keys = ['key1', 'key2', 'key3'];
foreach ($keys as $key) {
```

### Member variables (class properties) — NO UNDERSCORES
**Rule (moodle.NamingConventions.ValidVariableName.MemberNameUnderscore):**

```php
// WRONG:
protected string $enabled_key = '';   // ← MemberNameUnderscore error
private int $course_id = 0;

// RIGHT:
protected string $enabledkey = '';
private int $courseid = 0;
```

This is the OPPOSITE of local variables which use `$snake_case`. The distinction:
- Local variables inside methods: `$snake_case` (e.g. `$course_id`)
- Class properties: all lowercase or camelCase, NO underscores (e.g. `$courseid`)

### Anonymous functions / closures — SPACE AFTER `function`
**Rule (Squiz.Functions.MultiLineFunctionDeclaration.SpaceAfterFunction):**

```php
// WRONG:
array_filter($items, function($x) { ... });

// RIGHT:
array_filter($items, function ($x) { ... });
```

Named methods are unaffected — only closures/anonymous functions need the space.

### Test file conventions
- Test class name **must exactly match** the filename.
- **One test class per file.**
- Every test class declares `@covers \Full\ClassName`.
- `$this->resetAfterTest();` at the start of every test method that touches the DB.

### Capabilities and security
- `require_capability('local/coursectrl:view', $context)` before any output.
- POST forms always check `require_sesskey()`.
- `optional_param()` / `required_param()` with explicit `PARAM_*` type.

---

## Template Rules (Mustache)

### Template filename = class name (EXACT)
`$OUTPUT->render($renderable)` derives the template name from the fully qualified class name:

```
Class:    local_coursectrl\output\my_widget
Template: templates/my_widget.mustache   ← snake_case, underscore preserved
```

Never use a "nicer" different filename — Moodle will not find it.

### Never use {{> core/select_menu}} without knowing the exact variable contract
`core/select_menu.mustache` uses different variable names across Moodle 4.x / 5.x versions. In Moodle 4.5, `{{name}}` is output as the button text (not `{{label}}`). If in doubt: write your own template with full control over all variable names.

### Never use `\core\output\select_menu_option` / `select_menu_optgroup`
These classes do not exist in Moodle 4.5. Build plain PHP arrays matching the template contract instead.

### Template variable names must match PHP export
Before writing any Mustache template, read `export_for_template()` to check actual field names. Mustache silently ignores undefined variables — empty output is hard to debug.

### Mandatory file header
```mustache
{{!
    This file is part of Moodle - https://moodle.org/
    [GPL boilerplate]
}}
{{!
    @template local_coursectrl/template_name
    [description, context vars, example json]
}}
```

---

## JavaScript (AMD) Rules

### var-functions do not hoist — order matters
In AMD modules (`define([], function() {...})`), `var f = function() {}` is sequential — no hoisting. Functions must be defined BEFORE their call site.

### Python str_replace finds the FIRST match
When inserting before a pattern like `return {`, the first occurrence is replaced — not necessarily the semantically correct one. Always use the most specific pattern or target the last occurrence.

```python
# WRONG — inserts before nodeCenter's internal 'return {':
c = c.replace("    return {\n", my_code + "    return {\n", 1)

# RIGHT — find the last 'return {' (module-level):
matches = list(re.finditer(r'^ {4}return \{', c, re.MULTILINE))
pos = matches[-1].start()
c = c[:pos] + my_code + c[pos:]
```

### No unused vars (no-unused-vars)
All `var` declarations must be used. Declared-but-unused variables cause ESLint errors.

### Space after `function` in closures
```javascript
// WRONG:
var f = function(x) {...};
// RIGHT:
var f = function (x) {...};
```

### All comments must start with capital letter (capitalized-comments)
```javascript
// Wrong: starts lowercase
// Right: starts uppercase
```

---

## Navigation Architecture Rules

### `$PAGE->secondarynav` is for course-level tabs only
Do NOT add plugin-internal navigation to `$PAGE->secondarynav->add()` — items appear in the course tab bar (Berichte / Mehr) instead of the page content area.

For plugin-internal navigation: render a `navigation_bar` renderable AFTER `$OUTPUT->header()` in the page content area.

### navigation_builder.php must be in every patch that contains entry points
If a patch delivers entry points that call `navigation_builder::make()`, `navigation_builder.php` must be in the same patch — even if the file content hasn't changed.

---

## Subplugin Conventions

### Type 1: Activity adapter (`coursectrlmod_*`, directory `mod/`)
```
mod/<modname>/
  version.php
  classes/adapter.php, field_map.php (if dates), privacy/provider.php
  lang/de/coursectrlmod_<modname>.php
  lang/en/coursectrlmod_<modname>.php
```
- No-date adapters: NO blank line before class `}`, no `field_map.php`

### Type 2: Calendar provider (`coursectrlcal_*`, directory `cal/`)
```
cal/<n>/
  version.php
  classes/provider.php (extends abstract_calendar_provider), privacy/provider.php
  lang/de/coursectrlcal_<n>.php
  lang/en/coursectrlcal_<n>.php
```

### db/subplugins.json — both keys required (MDL-83705)
```json
{
    "subplugintypes": {
        "coursectrlmod": "mod",
        "coursectrlcal": "cal"
    },
    "plugintypes": {
        "coursectrlmod": "local/coursectrl/mod",
        "coursectrlcal": "local/coursectrl/cal"
    }
}
```

### Never use bash brace expansion with path separators in mkdir
```bash
# WRONG — creates literal '{cal/nager,...}' directory artifacts:
mkdir -p path/{cal/nager,cal/openholidays}/classes

# RIGHT — use Python os.makedirs() or explicit separate mkdir calls:
```python
for p in ['path/cal/nager/classes', 'path/cal/openholidays/classes']:
    os.makedirs(p, exist_ok=True)
```
```

---

## Recurring CI Failure Patterns — Quick Reference

| Failure | Root cause | Fix |
|---|---|---|
| `MultipleArguments` | Multi-line `new Foo(\n  $a, $b,` | One arg per line, or all on one line |
| `ControlStructureSpacing` | `foreach ([...multi` | Extract array to variable first |
| `InlineComment.NotCapital` | `// lowercase start` | Start with capital letter, digit, or `...` |
| `MultipleClasses` | Two classes in one file | Split into separate files |
| `TestCaseNames.NoMatch` | Class name ≠ filename | Rename one to match the other |
| `CloseBraceAfterBody` | Blank line before class `}` | Remove the blank line |
| `EndFileNewline.TooMany` | 2+ `\n` at end of file | Strip then add exactly one `\n` |
| `MemberNameUnderscore` | `protected string $my_prop` | Remove underscore: `$myprop` |
| `SpaceAfterFunction` | `function(` in closure | `function (` with space |
| `MissingDocblock.Constant` | Shared docblock for multiple consts | One docblock per `const` |
| `no-unused-vars` (JS) | Variable declared but unused | Remove or use the variable |
| `capitalized-comments` (JS) | `// lowercase` comment | `// Capital letter` |
| Template `filenotfound` | Template name ≠ class name | Rename template to match class name |
| Class not found | Using Moodle-version-specific output classes | Build plain PHP arrays instead |

---

## Pre-Generation Checklist

**PHP files:**
- [ ] One class per file, filename = class name
- [ ] All multi-line constructor/function calls: one arg per line
- [ ] No `foreach`/`if` with multi-line array literals — use variable
- [ ] All inline comments start with capital letter, digit, or `...`
- [ ] No blank line after opening `{`
- [ ] No blank line before class closing `}` (CloseBraceAfterBody)
- [ ] File ends with exactly ONE `\n` (EndFileNewline)
- [ ] Class properties: no underscores (`$courseid` not `$course_id`)
- [ ] Closures: `function (` with space
- [ ] Every `const` has its own docblock
- [ ] `resetAfterTest()` in all DB tests
- [ ] Docblock on every method with `@param`/`@return`
- [ ] Template name = class name (no creative renaming)
- [ ] navigation_builder.php included in patch if entry points included

**JS (AMD) files:**
- [ ] `define([], function() { ... });` wrapper
- [ ] JSDoc on every function
- [ ] `var` not `let`/`const`
- [ ] `function (` with space (closures)
- [ ] All comments capitalised
- [ ] No `console.log()`
- [ ] All vars used
- [ ] Lines ≤ 132 chars
- [ ] Functions defined BEFORE their call sites (no hoisting)
- [ ] Python insertions use last/specific match, not first match

**Mustache files:**
- [ ] GPL boilerplate + `@template` doc comment at top
- [ ] `{{variable}}` for escaped, `{{{variable}}}` for raw HTML only
- [ ] `hasX` boolean alongside every array `X`
- [ ] JS in `{{#js}}...{{/js}}` block, not inline `<script>`
- [ ] Root element has `class="local_coursectrl_templatename"` + `data-region`
- [ ] Variable names verified against actual `export_for_template()` output
- [ ] Not using `{{> core/select_menu}}` without knowing exact variable contract

**Subplugins:**
- [ ] `version.php` bumped in lockstep with `local_coursectrl`
- [ ] Privacy null provider present
- [ ] Lang strings alphabetical, no section comments
- [ ] Activity adapters: `field_map.php` only if date fields exist
- [ ] Calendar providers: `abstract_calendar_provider` extended
- [ ] `db/subplugins.json`: both `subplugintypes` AND `plugintypes` keys
- [ ] Directory creation: Python `os.makedirs()`, never bash brace expansion with `/`

---

## Rule: JavaScript Line Length (max-len = 132)

Moodle's ESLint config enforces a hard limit of **132 characters per line** in AMD JS files.

### Common violations and fixes

**Long string concatenation in ternary:**
```js
// WRONG (too long):
var x = cond
    ? '<button class="btn btn-outline-primary" id="btn-a">Alle sicheren wählen</button>'
    : '';

// CORRECT — extract string into named variable first:
var btnSafe = '<button class="btn btn-outline-primary" id="btn-a">' +
    'Alle sicheren w\u00e4hlen</button>';
var x = cond ? btnSafe : '';
```

**Long innerHTML assignment:**
```js
// WRONG (too long):
el.innerHTML = '<div class="spinner-border spinner-border-sm text-primary" role="status"></div>';

// CORRECT — split across lines, each ≤ 132 chars:
el.innerHTML = '<div class="spinner-border spinner-border-sm' +
    ' text-primary" role="status"></div>';
```

**Rule of thumb:** When building HTML strings in JS, break class lists at logical points. Prefer extracting long sub-strings into named variables before combining.

---

## Session 007 Additions (2026-04-18)

### @package tag in privacy/provider.php — no namespace suffix
Moodle's PHPCS sniff checks the `@package` tag against the *plugin name only*.

```php
// WRONG (common mistake — namespace appears in @package):
/**
 * @package    coursectrlmod_forum\privacy
 */

// RIGHT:
/**
 * @package    coursectrlmod_forum
 */
namespace coursectrlmod_forum\privacy;
```

The namespace declaration in PHP code is `coursectrlmod_forum\privacy`. The `@package` docblock must only contain the plugin component name.

### Multi-line `if` — PSR12 spacing (FirstExpressionLine / CloseParenthesisLine)
```php
// WRONG — first expression on same line as opening paren:
if (!empty($requiredgroups) &&
    empty(array_intersect($groupids, $requiredgroups))) {

// RIGHT — expression on next line, closing paren on own line:
if (
    !empty($requiredgroups) &&
    empty(array_intersect($groupids, $requiredgroups))
) {
```

### Multi-line function calls — one argument per line
When a function call is broken across lines, each argument gets its own line.

```php
// WRONG:
$result = $builder->build($cms, $depindex, $forwardmap, $warnings, $blocked, $next);

// RIGHT:
$result = $builder->build(
    $cms,
    $depindex,
    $forwardmap,
    $warnings,
    $blocked,
    $next
);
```

### Variable naming — no camelCase
All PHP local variables must be `snake_case`. Moodle's `ValidVariableName.VariableNameLowerCase` sniff rejects camelCase.
```php
// WRONG: $withDatesCount, $blockCount, $currentUser
// RIGHT:  $withdatescount, $blockcount, $currentuser
```

### querySelector with attribute selector in HTML onchange — HTML parser bug
Attribute selectors with double-quoted values inside an HTML attribute cause the HTML parser to truncate the value at the first inner `"`.

```mustache
{{! WRONG — inner " closes the onchange attribute early: }}
<input onchange="var b=document.querySelector('[data-action=\"foo\"]');">

{{! RIGHT — use getElementById to avoid quoting entirely: }}
<input id="my-toggle-btn" ...>
<input onchange="var b=document.getElementById('my-toggle-btn');...">
```

Always give interactive elements a stable `id` when they need to be referenced from inline event handlers.

### Verify str_replace actually matched
Python's `str_replace` / `str.replace()` is silent when no match is found — it returns the original string and no error is raised. Always verify after replacement:

```python
data = data.replace(old, new)
assert new_marker in data, f"str_replace failed — '{new_marker}' not found"
```

### Inline comments inside const arrays
Comments inside `const`-defined arrays follow the same capitalisation rule as all other inline comments.
```php
// WRONG: // mod_assign: instructions (lowercase after //)
// RIGHT: // Mod_assign: instructions
```

### Checklist additions (append to pre-generation checklist)

**PHP:**
- [ ] Privacy `provider.php`: `@package` tag has NO `\privacy` suffix
- [ ] Multi-line `if`: opening paren alone on line, expressions indented, `)` on own line
- [ ] All local variables: lowercase only, no camelCase (`$withdatescount` not `$withDatesCount`)
- [ ] Comments inside `const` arrays: start with capital letter

**JS:**
- [ ] All declared `var`s are actually used (ESLint no-unused-vars)
- [ ] Attribute selectors in inline event handlers: use `getElementById`, not `querySelector` with `"`
