# CourseCtrl activity adapters · `coursectrlmod_*`

[![Moodle Plugin CI](https://github.com/ralferlebach/moodle-local_coursectrl/actions/workflows/moodle-ci.yml/badge.svg?branch=development)](https://github.com/ralferlebach/moodle-local_coursectrl/actions/workflows/moodle-ci.yml?query=branch%3Adevelopment)

[![CourseCtrl Hub](https://img.shields.io/badge/Plugin%20family-CourseCtrl%20Hub-3867d6)](https://github.com/ralferlebach/moodle-local_coursectrl) [![CourseCtrl Dates](https://img.shields.io/badge/Companion-CourseCtrl%20Dates-16a085)](https://github.com/ralferlebach/moodle-block_coursectrldates) [![Learn Tip](https://img.shields.io/badge/Companion-Learn%20Tip-8e44ad)](https://github.com/ralferlebach/moodle-block_coursectrllearntip)

This directory contains the **activity-type subplugins** bundled with [Course Control Hub](../README.md). It is **not** a separate installable Moodle plugin and has no independent homepage or CI repository.

## Requirements

- **Parent:** `local_coursectrl` (Moodle 4.5–5.3 as declared for the current development version).
- **Location:** `local/coursectrl/mod/{modname}/`, registered as subplugin type **`coursectrlmod`** in [`db/subplugins.json`](../db/subplugins.json).
- The matching Moodle activity module (including third-party modules where relevant) must be installed to exercise an adapter.

## Motivation

Activity plugins model deadlines, grades and attempts differently. Adapters provide CourseCtrl-compatible field maps and actions rather than duplicating module-specific SQL and UI logic across the CourseCtrl family.

## Included adapters

`assign`, `capquiz`, `choice`, `choicegroup`, `feedback`, `forum`, `glossary`, `h5pactivity`, `lesson`, `page`, `questionnaire`, `quiz`, `scorm`, `studentquiz`, `workshop`.

The actual adapter set can vary by revision; inspect this directory and each `version.php` before relying on a component. These are adapters for modules, **not copies** of the Moodle activity modules.

## Installation

Install the **parent** at `local/coursectrl` and install required third-party Moodle activity modules via their standard plugin paths. Bundled adapter subplugins are discovered through the parent's registered subplugin type; do not move them into `moodle/mod/`.

## Usage, settings & capabilities

CourseCtrl discovers eligible adapters for teacher-authorised inventory, preview, execution and analysis operations. Permissions are enforced through the **parent** capabilities `local/coursectrl:*` and Moodle Core activity/course contexts. Any adapter-specific action must be independently validated and must not bypass the parent checks.

## Scheduled tasks

No separate schedule is promised for the adapter directory; use the parent plugin's Moodle tasks and the installed activity module's own tasks.

## How adapters work / pitfalls

- Existing date-field adapters expose their fields and safe mutation/restore contracts.
- A **proposed** shared access/recovery/evidence interface should enrich the same activity-state logic used by teacher deep analysis and learner-facing Learn Tip; see [shared graph #70](https://github.com/ralferlebach/moodle-local_coursectrl/issues/70).
- Gradebook, completion, retake/reopen and pass/fail policies cannot be inferred reliably from a generic `gradepass` flag: prefer Moodle Core and the activity's supported API, with `unknown` where unavailable.
- Adapter registration, PHPUnit suite discovery and release-CI are tracked in [#45](https://github.com/ralferlebach/moodle-local_coursectrl/issues/45); multiple current defects remain tracked at [GitHub Issues](https://github.com/ralferlebach/moodle-local_coursectrl/issues).

## Theme support

This directory contains primarily backend extensions; any adapter-rendered UI follows the parent's Boost/Classic and accessibility guidelines.

## Plugin repositories

- **Parent:** https://github.com/ralferlebach/moodle-local_coursectrl
- **Dates block:** https://github.com/ralferlebach/moodle-block_coursectrldates
- **Learn Tip (planned):** https://github.com/ralferlebach/moodle-block_coursectrllearntip
- **Template:** https://github.com/ralferlebach/moodle-plugintemplate

## Bugs, support & feature proposals

Report problems in [CourseCtrl Issues](https://github.com/ralferlebach/moodle-local_coursectrl/issues) and submit changes through [Pull requests](https://github.com/ralferlebach/moodle-local_coursectrl/pulls). Include the affected adapter name and installed activity-plugin version.

## Moodle release support

The parent declares Moodle 4.5–5.3 compatibility, subject to real CI/adapter checks. New module-specific APIs may have narrower supported version ranges.

## Translations and RTL

Use parent/subplugin language files for English and German strings; additional translations via [AMOS](https://lang.moodle.org/). RTL and theme-specific behaviour require testing where a UI is present.

## Maintainer, copyright & licence

Maintainer: **Ralf Erlebach**. © 2026 contributors as credited in the source. **GNU GPL v3 or later**.
