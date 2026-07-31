# What travels when a Designer course is copied

Every Designer setting is part of the course. Duplicating, copying, backing up and
restoring all carry them, so a copied course looks like the course it came from.

This page says exactly what "everything" means, because there are two edges worth
knowing about.

## What is carried

| | Stored as | Carried |
| --- | --- | --- |
| Course options — course type, header, progress, index, accordion | `course_format_options` (course level) | yes |
| Section options — layout, widths, estimated time, design, background | `course_format_options` (section level) | yes |
| Activity options — hero, elements, background, purpose | `format_designer_options` | yes |
| Images — section and activity backgrounds, header and course backgrounds, editor attachments | Moodle file areas | yes |

That holds for every route: **Duplicate** in the course list, **Copy course**,
**Backup** and **Restore** in the browser, `admin/cli/restore_backup.php`, and the
`core_course_duplicate_course` web service.

It also holds when a feature is switched off. Feature toggles hide fields; they
never touch stored values, and backup works from the stored values. A course
copied while a feature was off still has that feature's settings, and switching
the feature back on finds them intact.

## Edge one: import brings activities, not design

Moodle's **Import** copies activities into a course that already exists, and
deliberately leaves that course's own settings alone. Course and section format
options are part of "its own settings", so Designer's are not imported.

This is core behaviour and applies to every course format, not just Designer. If
you want the design as well, use **Copy course** or restore a backup into a new
course.

## Edge two: mask images do not survive a move to another site

Section and activity **mask images** are chosen from a list your administrator
uploads at site level. The course stores the identifier of the chosen file, not
the file itself, and that identifier only means anything on the site it came from.

Restore onto the *same* site and masks come back correctly. Restore onto a
*different* site and a mask will point at whichever file happens to hold that
identifier there, or at nothing.

Everything else — background images, header images, editor attachments — is a
proper course file and moves with the backup.

If you are moving courses between sites and use masks, check them after the
restore, or set them again on the destination.

## A note for administrators upgrading

The section layout shipped as the default changed to **Default (custom sections)**.
The upgrade only adopts that on a site that never stored a value of its own, so no
existing course changes appearance. Per-course and per-section layouts are never
rewritten by an upgrade.
