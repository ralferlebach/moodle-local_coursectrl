# Moodle Plugin: Course Control Hub (`local_coursectrl`)

## Description

Course Control Hub is a Moodle local plugin that gives teachers and course managers a
single, integrated workspace for inspecting, correcting, and future-proofing their course
configurations. It detects problems that are otherwise invisible — date inversions,
circular completion dependencies, unreachable activities, ambiguous date references in
free-text fields — and provides safe, preview-first bulk-editing tools to fix them.

Every action requires an explicit preview and confirmation step. No data is changed
automatically. All bulk operations are fully logged and can be rolled back.

## Features

* **Dashboard / Cockpit** — at-a-glance view of upcoming activity dates, open issues, and
  recent bulk actions for the current course.
* **Timeline & Gantt** — visual overview of all activity dates and availability windows;
  supports bulk date shifting directly from the timeline with a 3-step modal workflow.
* **Dependency Graph** — interactive graph of completion and availability dependencies;
  highlights circular locks, unreachable nodes, and simulation state overlays.
* **Checks — Problems** — rule-based scan (rules R0–R7) covering date inversions,
  hidden-but-tracked activities, missing completion settings, stale group conditions,
  and structural dead ends.
* **Checks — Solutions** — prioritised fix suggestions linked directly to each detected
  problem, with one-click navigation to the affected activity.
* **Checks — Simulation** — learner journey simulator evaluating visibility and
  accessibility for a defined learner state (point in time, group membership, assumed
  completion and grade results), detecting dead ends before learners encounter them.
* **Bulk Date Shift** — shift structured date fields across all selected activities by
  days, hours, and minutes; preview shows every changed field with old/new values,
  resolved locale-aware field labels, and human-readable dates.
* **Text Review** — scans free-text fields (descriptions, labels, section texts) for
  embedded date references, classifies them as safe / ambiguous / informational, and
  offers controlled in-place updates in a dedicated review step.
* **History & Rollback** — full audit log of all executed bulk actions; individual
  batches can be selected and rolled back with a single click.
* **Extensible via subplugins** — `coursectrlmod_*` adapters encapsulate
  activity-type-specific logic; new activity types can be added without modifying the
  core plugin code.

## Installation

1. Copy the `coursectrl` directory into `<moodleroot>/local/`.
   The directory **must** be named exactly `coursectrl`.

2. Log in as administrator and navigate to
   **Site administration → Notifications**.
   Moodle detects the new plugin and runs the database installer automatically.

3. Navigate to **Site administration → Users → Permissions → Define roles** and assign
   the plugin capabilities to the appropriate roles (see *Settings* below).

4. Optionally install the companion **Course Control Hub Block** (`block_coursectrl`)
   to give teachers a one-click entry point directly inside each course page.

**Upgrade:** Replace the contents of `local/coursectrl/` with the new version and visit
**Site administration → Notifications**. Database migrations run automatically; no manual
SQL is required.

### Requirements

| Component | Minimum | Tested up to |
|---|---|---|
| Moodle | 4.5 | 5.2 |
| PHP | 8.2 | 8.4 |
| Database | MariaDB 10.6 / PostgreSQL 14 | MariaDB 10.11 / PostgreSQL 16 |

### Included subplugin adapters (1.0.0)

| Subplugin | Activity type |
|---|---|
| `coursectrlmod_assign` | Assignment |
| `coursectrlmod_capquiz` | CAPQuiz |
| `coursectrlmod_choice` | Choice |
| `coursectrlmod_choicegroup` | Group Choice |
| `coursectrlmod_feedback` | Feedback |
| `coursectrlmod_forum` | Forum |
| `coursectrlmod_glossary` | Glossary |
| `coursectrlmod_h5pactivity` | H5P Activity |
| `coursectrlmod_lesson` | Lesson |
| `coursectrlmod_page` | Page |
| `coursectrlmod_questionnaire` | Questionnaire |
| `coursectrlmod_quiz` | Quiz |
| `coursectrlmod_scorm` | SCORM Package |
| `coursectrlmod_studentquiz` | StudentQuiz |
| `coursectrlmod_workshop` | Workshop |

## Settings

Navigate to **Site administration → Plugins → Local plugins → Course Control Hub**.

### Capabilities

| Capability | Suggested role | Purpose |
|---|---|---|
| `local/coursectrl:view` | `editingteacher`, `manager` | Access all read-only analysis pages |
| `local/coursectrl:bulkaction` | `editingteacher` | Execute bulk date shifts and text changes |
| `local/coursectrl:rollback` | `manager` | Roll back a previously executed bulk action |

### Key admin settings

| Setting | Default | Purpose |
|---|---|---|
| `history_maxcount` | 100 | Maximum batch records retained per course |
| `history_maxdays` | 365 | Maximum age of batch records in days |
| Risk rules R0–R7 | `warning` | Severity level for each consistency-check rule |
| `risk_max_group_combinations` | 32 | Maximum group combinations simulated per run |

Full documentation of all settings and pages is in the [User Guide](docs/user-guide.md).

## Contributors

* **Ralf Erlebach** — author and lead developer

## License

This plugin is free software: you can redistribute it and/or modify it under the terms of
the **GNU General Public License** as published by the Free Software Foundation, either
version 3 of the License, or (at your option) any later version.

See <https://www.gnu.org/licenses/> for details.
