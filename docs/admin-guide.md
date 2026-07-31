---
audience: admin
pagetypes:
  - admin-setting-format_designer_features
  - admin-setting-format_designer_general
  - admin-setting-format_designer_section
  - admin-setting-format_designer_activity
  - course-view-designer
---

# Manage features — administrator guide

Designer ships a lot of features. Most sites use a handful of them, and every one
they do not use still shows up in the course settings form, in the section edit
form and on the activity form — which makes Designer feel much heavier than the
course a teacher is actually trying to build.

**Manage features** lets you turn the big features off. A feature you switch off
disappears from every form it owns, and the course renders as if it had never
existed.

Nothing is deleted. Switching a feature back on brings every setting back exactly
as it was.

## Where it is

*Site administration ▸ Plugins ▸ Course formats ▸ Designer ▸ Manage features*

The page is grouped the way the features are used:

| Group | Covers |
| --- | --- |
| Course-wide features | Course type, accordion sections, course index control |
| Section features | Section activity layouts, course section layout |
| Activity features | Hero activity, activity elements, secondary navigation, popup activities |
| Course header features | Course header, activity progress, time management |

Every toggle is named **Enable …**, so it reads as the switch it is. That also keeps
each toggle's name distinct from the setting it governs: a toggle called "Course
types" would sit uncomfortably close to the "Course type" setting on the same
page, and anything matching controls by name — your own scripts, an accessibility
tool, an automated test — could pick the wrong one.

Designer Pro adds its own features to the same page — course background, section
background, activity background, prerequisites, purposes. They appear only when
Designer Pro is installed.

## Everything is on until you say otherwise

A feature with no stored setting counts as enabled. That is deliberate: when you
upgrade to this release, nothing changes. Every feature you had is still there,
every course looks the same, and you do not have to visit this page at all.

You only ever need it when you want *less*.

## What switching a feature off does

Three things, and only these three:

- its fields disappear from the course, section and activity forms;
- its settings disappear from the Designer settings pages;
- the course renders without it.

What it does **not** do:

- it does not delete anything from the database;
- it does not change any course;
- it does not affect backup or restore.

That last one is worth stating plainly, because it is the one people worry about.
A course backed up while a feature is switched off still contains that feature's
settings, and restoring it on a site where the feature is on brings them all back.
Backup works from the stored values, and the stored values are never touched.

### A key two features share

A few settings belong to more than one feature. *Initial state*, for example, is
used by both **Course type** and **Accordion**. A setting like that only disappears
when **every** feature that uses it is switched off. Switch off Course type alone
and Initial state stays, because Accordion still needs it.

## The section layout called "Default"

Designer's section layouts decide how the activities inside a section are drawn.
This release adds one more, and makes it the layout new sites start with:

| Layout | What a section looks like |
| --- | --- |
| **Default (custom sections)** | Exactly like a standard Moodle course |
| Text links | Activity names as plain links |
| List | Designer's list layout |
| Cards | Activity cards |
| Circles, Horizontal circles | Designer Pro |

**Default (custom sections)** renders the activity list through Moodle's own
markup — the same activity items, icons, availability information and edit menus a
teacher sees in the Custom sections (Topics) format. Choose it and a Designer
course is visually a standard course, which is the right starting point if you
want Designer for its course header or progress features rather than for its
section styling.

It is also what every section falls back to when you switch **Section activity
layout** off, whatever layout the section has stored.

### What this means when you upgrade

New sites get **Default (custom sections)** as the site-wide section layout.

Existing sites keep the layout they already had. The upgrade only adopts the new
default on a site that never had a stored value, so no existing course changes
appearance. Per-course and per-section layouts are never touched by the upgrade.

If you *want* the new default on an existing site, set it yourself at
*Site administration ▸ Plugins ▸ Course formats ▸ Designer ▸ Section ▸ Section
layout*. Sections that have their own stored layout keep it — the site-wide
setting is only the starting value.

## Features that need another plugin

Two features only appear when the plugin they need is installed:

| Feature | Needs |
| --- | --- |
| Popup activities | `format_popups` |
| Time management (due dates) | `tool_timetable` |

If the plugin is missing the feature is off and cannot be switched on, and the
toggle explains why. Enrolment and completion dates under Time management keep
working without `tool_timetable`; only the due-date parts need it.

Pro features behave the same way: without Designer Pro they are neither shown nor
enabled.

## If something looks wrong

**A setting vanished from the course form.** Its feature is off. Find the feature
that owns it in the table above and switch it back on; the stored value is still
there and reappears with it.

**A section looks different after an upgrade.** Check
*Designer ▸ Section ▸ Section layout*. If it now reads "Default (custom sections)"
on a site that used to use another layout, set it back — and please report it, as
the upgrade is meant to leave that setting alone.

**A pro feature is missing from this page.** Designer Pro is not installed, or it
is older than this release. The pro features are contributed by Designer Pro
itself, so an older pro plugin simply contributes none.
