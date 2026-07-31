# Extending Designer

Two extension points, and the rules that keep them from surprising you.

## The feature registry

`\format_designer\features::get_features()` is the single list of the major
features an administrator can switch off. Everything else reads from it: the
Manage features admin page, the option stripping, and the gating in the renderer.

An entry looks like this:

```php
'accordion' => [
    'name' => 'feature_accordion',          // lang string, must read "Enable ..."
    'description' => 'feature_accordion_desc',
    'group' => self::GROUP_COURSE,          // which block on the admin page
    'pro' => false,                         // true = needs Designer Pro
    'depends' => null,                      // or a component like 'format_popups'
    'courseoptions' => ['accordion', 'initialstate'],
    'activityoptions' => [],
    'configkeys' => [],
],
```

The three option lists say which keys the feature *owns*. When it is switched off,
those keys are dropped from the edit forms — and only those. A key claimed by more
than one feature survives until every owner is off, which is why `initialstate`
appears under both `coursetype` and `accordion`.

Ask about a feature with `\format_designer\helper::feature_enabled('accordion')`.
Never read the config key directly: the helper also handles pro-only features on a
site without Pro, and features whose dependency is not installed.

### Three rules

**An unset toggle means enabled.** Upgrading sites must keep every feature they
had. A missing config value is on, not off.

**An unknown key is enabled.** A pro plugin newer than the free one may ask about a
feature this registry has never heard of. Returning `true` keeps the course
settings form working instead of fatal.

**A toggle name must not contain an option label.** Moodle matches form fields by a
label substring, and the Manage features page renders first, so a toggle called
"Course types" shadows the "Course type" setting — anything aiming at one hits the
other. Naming every toggle "Enable …" keeps them apart, and
`features_test::test_feature_toggle_labels_do_not_shadow_option_labels` fails if a
new one ever breaks that.

### Contributing features from another plugin

Designer Pro adds its own entries by implementing
`\local_designer\features::get_features()`, which the free registry merges in when
Pro is installed. Any plugin can do the same; the free side guards the call with
`class_exists()`, so an older Pro simply contributes nothing.

## Section layouts

A layout decides how the activities inside a section are drawn. The free plugin
ships `plain`, `default`, `list` and `cards`; `circles` and `horizontal_circles`
come from Pro as `layouts_*` subplugins.

A free layout needs two templates:

```
templates/layout/section_layout_<key>.mustache    the section wrapper
templates/cm/module_layout_<key>.mustache         one activity item
```

and an entry in `\format_designer\helper::get_all_layouts()`.

A subplugin layout lives in `local/designer/layouts/<key>/` and ships the same two
templates under its own component. `cmlist.php` resolves the subplugin form first
and falls back to the free one.

`plain` is worth reading before writing a new layout: it renders through core's
own `cmitem` partial, so the section is indistinguishable from the Custom sections
format. It is also the fallback when the section activity layout feature is off,
so it must never depend on anything Designer-specific.

## Two traps in the option definitions

**`course_format_options_list()` is cached in a static, and the edit-form branch
decorates and prunes that array in place.** The persisted definitions are
snapshotted before that happens and handed back untouched — do not "simplify" this
by returning the working array, or `update_format_options()` starts seeing
edit-form metadata and a pruned key list.

**A hidden editor does not submit its textarea.** An editor hidden by a `hideif`
posts `format` and `itemid` but no `text`. Post-processing that array dies on the
missing key, and passing it on reaches `clean_param()`, which refuses arrays. Drop
the key instead: a field that was not on screen has nothing to save, and
`update_format_options()` ignores keys that are not in the data.

## Tests

`tests/features_test.php` covers the registry shape, the enabled-by-default rule,
label shadowing and the static cache. `tests/upgrade_test.php` pins what an
upgrade may and may not change on an existing site. Add to them rather than
starting a new file — the invariants above are easy to break by accident and cheap
to assert.
