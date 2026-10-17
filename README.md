# Course Control Hub · `local_coursectrl`

[![Moodle Plugin CI](https://github.com/ralferlebach/moodle-local_coursectrl/actions/workflows/moodle-ci.yml/badge.svg?branch=development)](https://github.com/ralferlebach/moodle-local_coursectrl/actions/workflows/moodle-ci.yml?query=branch%3Adevelopment) [![Moodle 4.5–5.3](https://img.shields.io/badge/Moodle-4.5%E2%80%935.3-informational)](version.php)

[![CourseCtrl Hub](https://img.shields.io/badge/Plugin%20family-CourseCtrl%20Hub-3867d6)](https://github.com/ralferlebach/moodle-local_coursectrl) [![CourseCtrl Dates](https://img.shields.io/badge/Companion-CourseCtrl%20Dates-16a085)](https://github.com/ralferlebach/moodle-block_coursectrldates) [![Learn Tip](https://img.shields.io/badge/Companion-Learn%20Tip-8e44ad)](https://github.com/ralferlebach/moodle-block_coursectrllearntip)

Course Control Hub is a Moodle local plugin for teachers and course managers to inspect course dates, availability and completion dependencies, analyse learner pathways, preview and apply date changes, and review/rollback changes.

## Requirements

- **Moodle:** 4.5–5.3 (declared in `version.php`; verify CI before production release).
- **PHP / database:** use the version-compatible requirements of the selected Moodle release; the project CI covers multiple PHP/database combinations.
- **Install path:** `local/coursectrl`.
- **Current development version:** `1.2.1` (`2026101701`) on `development`. The published `main` branch can lag.
- Optional activity adapters live under [`mod/`](mod/README.md); optional calendar providers under `cal/`.

## Motivation

Course configurations can contain conflicting dates, impossible completion chains and conditional access requirements that are difficult to review consistently. Course Control Hub provides a course-oriented review and an auditable change workflow.

## Installation

1. Install the plugin as `<moodle-root>/local/coursectrl`.
2. Install any required third-party activity plugins before enabling their adapters.
3. Visit **Site administration → Notifications**.
4. Assign the `local/coursectrl:*` capabilities according to your course-administration policy.

See [Moodle plugin installation](https://docs.moodle.org/en/Installing_plugins).

## Usage & settings

The plugin provides a **Dashboard**, **Timeline**, **Manage** date-change workflow, **Checks** (consistency, risk analysis and learner-state simulation), a dependency graph, and **History / rollback**.

Changes are previewed before execution. Do not assume every batch can be safely rolled back: ongoing rollback/preview correctness improvements are tracked in [GitHub Issues](https://github.com/ralferlebach/moodle-local_coursectrl/issues).

Settings are under **Site administration → Plugins → Local plugins → Course Control Hub**. See the [User Guide](docs/user-guide.md) for the current configuration options.

## Capabilities

Core capability definitions are in [`db/access.php`](db/access.php). In particular:
- `local/coursectrl:view` and `local/coursectrl:viewreports`: inspect reports.
- `local/coursectrl:simulate`: run hypothetical learner-state simulations.
- `local/coursectrl:bulkaction`: apply bulk changes.
- `local/coursectrl:rollback`: restore saved batch snapshots where supported.
- `local/coursectrl:managepresets`: manage shared presets.

These are designed for authorised teachers/managers; do not expose simulated user grades as real learner data.

## Scheduled tasks

See [`db/tasks.php`](db/tasks.php) if present in the deployed version and Moodle's **Scheduled tasks** administration for installed tasks. No task schedule is promised by this README.

## How this plugin works / pitfalls

- Moodle Core provides authoritative visibility, completion and grade state; the plugin's *hypothetical* simulations are not personal learner records.
- Activity and calendar adapters support additional modules/providers. All analysis and simulation paths must share consistent group/grouping and access semantics; see [#63–#66](https://github.com/ralferlebach/moodle-local_coursectrl/issues/63).
- A shared evidence/availability graph for both deep analysis and the future Learn Tip block is planned in [#70](https://github.com/ralferlebach/moodle-local_coursectrl/issues/70) and [#71](https://github.com/ralferlebach/moodle-local_coursectrl/issues/71); this is **not yet an implemented API**.
- Simulation input/replay problems remain tracked in [#67](https://github.com/ralferlebach/moodle-local_coursectrl/issues/67)–[#69](https://github.com/ralferlebach/moodle-local_coursectrl/issues/69).
- The plugin's external/homepage website has **not** been set up; GitHub is the authoritative project location.

## Theme support

Developed with Moodle's Boost/Classic ecosystem in mind. Browser, accessibility and Bootstrap 4/5 compatibility require version-specific checks; see [#49](https://github.com/ralferlebach/moodle-local_coursectrl/issues/49).

## Plugin repositories / related projects

- **Source:** https://github.com/ralferlebach/moodle-local_coursectrl
- **Dates block:** https://github.com/ralferlebach/moodle-block_coursectrldates
- **Planned learner-facing block:** https://github.com/ralferlebach/moodle-block_coursectrllearntip
- **Development template:** https://github.com/ralferlebach/moodle-plugintemplate

## Bug reports, support & feature proposals

Submit defects or feature proposals at [GitHub Issues](https://github.com/ralferlebach/moodle-local_coursectrl/issues); contributions via [Pull requests](https://github.com/ralferlebach/moodle-local_coursectrl/pulls). Include Moodle version, PHP/DB combination, course/activity types, reproducible steps and sanitised logs.

## Moodle release support

`version.php` declares support for Moodle 4.5–5.3. Compatibility claims are conditional on successful integration testing; CI and release gates are tracked in [#40](https://github.com/ralferlebach/moodle-local_coursectrl/issues/40) and [#62](https://github.com/ralferlebach/moodle-local_coursectrl/issues/62).

## Translating this plugin

English and German strings are maintained in `lang/en/` and `lang/de/`. For further translations, use Moodle's [AMOS](https://lang.moodle.org/).

## Right-to-left support

RTL languages have not been comprehensively validated; contributions and test reports are welcome.

## Maintainer, copyright & licence

Maintainer: **Ralf Erlebach**. Copyright © 2026 contributors as credited in source files. Licensed under the **GNU GPL v3 or later**; see https://www.gnu.org/licenses/gpl-3.0.html.
