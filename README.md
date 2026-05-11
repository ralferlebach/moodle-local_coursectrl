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

* **Dashboard**: at-a-glance view of upcoming activity dates, open issues, and
  recent bulk actions for the current course.
* **Timeline**: shifting single dates or as batches by calendar, including information tokens in free texts, as well as a visual overview of all activity dates and availability windows.
* **Dependency**: interactive graph of completion and availability dependencies.
* **Manage**: shifting dates for one or multiple activities.
* **Checks**: rule-based surface scan and simulation-based deep scanning for problems in course design and dependencies, solutions and simulation for specific course progress trajectories.
* **Checks — Solutions**: prioritised fix suggestions linked directly to each detected
* **History & Rollback**: full audit log of all executed bulk actions.
* supporting all standard activities of MOODLE as well as multiple foreign activity types via subplugins.
* support for open calendar APIs for holidays and term breaks.

## Installation

1. Copy the `coursectrl` directory into `<moodleroot>/local/`.
   The directory **must** be named exactly `coursectrl`.

2. Log in as administrator and navigate to
   **Site administration → Notifications**.
   Moodle detects the new plugin and runs the database installer automatically.

3. Navigate to **Site administration → Users → Permissions → Define roles** and assign
   the plugin capabilities to the appropriate roles (see *Settings* below).

4. Optionally install the companion **Course Control Hub Block** (`block_coursectrldates`)
   to give teachers a one-click entry point directly inside each course page. (new with vers. 1.1)


### Requirements

| Component | Minimum | Tested up to |
|---|---|---|
| Moodle | 4.5 | 5.2 |
| PHP | 8.2 | 8.4 |
| Database | MariaDB 10.6 / PostgreSQL 14 | MariaDB 10.11 / PostgreSQL 16 |

### Included subplugin adapters (1.1.0)

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
| `local/coursectrl:simulate` | `editingteacher`, `manager` | Run the learner journey simulation |

### Key admin settings

| Setting | Default | Purpose |
|---|---|---|
| `history_maxcount` | 100 | Maximum batch records retained per course |
| `history_maxdays` | 365 | Maximum age of batch records in days |
| Risk rules R0–R7 | `warning` | Severity level for each consistency-check rule |
| `risk_max_group_combinations` | 32 | Maximum group combinations simulated per run |

Full documentation of all settings and pages is in the [User Guide](docs/user-guide.md).

## Contributors

* **Ralf Erlebach** — author and developer

## License

This plugin is free software: you can redistribute it and/or modify it under the terms of
the **GNU General Public License** as published by the Free Software Foundation, either
version 3 of the License, or (at your option) any later version.

See <https://www.gnu.org/licenses/> for details.
