---
audience: manager
pagetypes:
  - course-view-designer
  - course-edit
  - course-management
---

# Designer for course managers

You do not have to configure Designer to use it. A new Designer course looks like
a standard Moodle course and works like one. Everything below is optional.

This guide is for people who create and hand over courses: what to decide once,
what to leave alone, and what happens when a course is copied.

## Choosing Designer for a course

Set **Course format** to *Designer format* in the course settings. The course keeps
its sections, its activities and its completion settings — only the presentation
changes, and at first it does not change much, because the shipped section layout
is **Default (custom sections)**.

That is deliberate. Designer earns its keep gradually: turn on a course header,
or a progress display, or a card layout for one section, and leave the rest alone.

## The three decisions worth making up front

**How the course is laid out.** *Course type* offers Normal, Collapsible sections,
Kanban board and Flow. Normal is the standard vertical course. The others change
navigation enough that it is worth deciding before teachers start building, not
after.

**Whether sections show all at once.** *Course layout* — show all sections on one
page, or one section per page. Per-section widths only do anything in the
one-section-per-page mode, because on a single page a section always spans the
full width.

**What the course header shows.** With Designer Pro you can add a header band with
the course summary, staff, progress and custom fields. Without Pro you still get a
lighter header. Decide once per course; teachers rarely need to change it.

Everything else — section layouts, activity presentation — is better left to the
teacher building the course.

## Templates and handover

A course you have set up is a perfectly good template. Duplicating, copying,
backing up and restoring all carry every Designer setting with them: course
options, per-section layouts and widths, per-activity presentation, and the
images behind them.

So the practical pattern is: build one course the way you want it, then copy it.
The copy is identical.

One exception worth knowing: **import does not bring design settings**. Moodle's
*Import* brings activities into a course that already exists, and deliberately
leaves that course's own settings alone — including Designer's. If you want the
design as well, copy or restore the course rather than importing into a new one.

## If a setting you expect is not there

Your administrator can switch Designer's major features off site-wide, and a
feature that is off takes its settings out of the forms with it. Nothing is lost:
if the feature is switched back on, every value you had set is still there.

If something you need is missing, ask them to check
*Site administration ▸ Plugins ▸ Course formats ▸ Designer ▸ Manage features*.

## What your teachers will ask about

**"Why does my section look different from the others?"** Section layout is set per
section. The course-wide setting is only the starting value; a section that has
been given its own layout keeps it.

**"Where did the section width setting go?"** It only appears when the course shows
one section per page. On a single-page course it would do nothing.

**"The course looks plain."** That is the default. Point them at the teacher guide —
section layouts are one menu away.
