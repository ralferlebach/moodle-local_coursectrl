# Course Control Hub

**Moodle plugin:** `local_coursectrl`  
**Version:** 1.0.0  
**Maturity:** Stable  
**License:** [GNU GPL v3 or later](https://www.gnu.org/licenses/gpl-3.0.html)  
**Author:** Ralf Erlebach  

---

Course Control Hub gives teachers and course coordinators a single interface to
inspect, analyse, and bulk-edit the structure, dates, and availability logic of a
Moodle course. It surfaces configuration problems that are otherwise invisible,
and provides safe, preview-first bulk-editing tools to correct them efficiently.

---

## Features

### Dashboard
- At-a-glance cockpit: section count, activity count, open problems, text date references
- Problem summary with direct links and one-click action buttons
- Upcoming dates, monthly calendar, collapsible activity inventory

### Timeline & Text Review
- Chronological list of all structured date fields across all activities
- Three-step bulk date shift: configure → preview → confirm
- Gantt view of overlapping date windows
- Free-text date detection and controlled rewriting (AJAX-based, no page reload)

### Dependency Graph
- Interactive completion-dependency graph with group-aware filter
- Grade-based dependency edges (activities gated on a passing grade)
- Simulation overlay: blocked activities (red) and next steps (green)

### Checks
Three tabs in one page:

- **Problems** — Live consistency checks: temporal conflicts, dangling prerequisites,
  impossible completion conditions, date/course-frame violations, adapter rule checks.
  Runs every time the tab is opened; results are not persisted.

- **Solutions** — Persistent risk assessment: structural dead ends, circular dependency
  cycles, long chains, hidden activities with completion tracking, and dynamic learner
  journey simulation (see below). Results stored per course; refresh with *Run now*.

- **Simulation** — Learner-perspective simulation. Set a date, group membership,
  completion states, and grade to see which activities are accessible, blocked, or
  recommended as next steps.

### Dynamic Journey Simulation
The risk scanner simulates all plausible learner journeys through the course:

- Iterates every group-membership combination (configurable limit)
- Two grade scenarios per combination: *best case* (all passed) and *worst case* (all failed)
- Detects activities that can never be reached in any scenario
- Escalates severity to **error** when a blocked activity is required for course completion
- Generates a direct **Replay in Simulation** deep-link for each finding

### History & Rollback
- Paginated audit log of all executed bulk actions
- Per-batch rollback via pre-operation snapshots
- Configurable retention limits (count and age)

---

## Requirements

| Component | Minimum version |
|---|---|
| Moodle | 4.5 |
| PHP | 8.2 |
| Database | MariaDB 10.6 · PostgreSQL 14 |

Tested on Moodle 4.5, 5.0, 5.1 and 5.2 with PHP 8.2, 8.3, and 8.4.

---

## Installation

1. Download and extract the archive into `<moodleroot>/local/coursectrl/`.
2. Log in as a site administrator and navigate to
   **Site administration → Notifications**.
3. Complete the installer prompts. Database tables are created automatically.
4. Assign capabilities (see the [User Guide](docs/user-guide.md)).
5. Optionally install the companion **Course Control Hub Block**
   (`block_coursectrl`) to add a visible entry point in courses.

---

## Upgrade

Replace the `local/coursectrl/` directory with the new version and visit
**Site administration → Notifications**. Database migrations are applied
automatically.

---

## Capabilities

| Capability | Suggested role | Description |
|---|---|---|
| `local/coursectrl:view` | editingteacher, manager | Open the plugin and all read-only pages |
| `local/coursectrl:bulkaction` | editingteacher | Execute bulk date shifts and text changes |
| `local/coursectrl:rollback` | manager | Roll back a previously executed bulk action |

---

## Configuration

All settings are in **Site administration → Plugins → Local plugins → Course Control Hub**.

Key settings include risk rule severities (R0–R7, each independently configurable),
dashboard display options, history retention limits, and the journey simulation
parameters (`risk_min_activity_minutes`, `risk_max_group_combinations`).

Full documentation: [docs/user-guide.md](docs/user-guide.md)

---

## Subplugins

Activity-type logic is encapsulated in `coursectrlmod_*` subplugins. Each
subplugin provides date field mappings, validation, preview, execution, rollback,
and rule-check logic for one Moodle activity type.

| Subplugin | Activity |
|---|---|
| `coursectrlmod_assign` | Assignment |
| `coursectrlmod_quiz` | Quiz |
| `coursectrlmod_feedback` | Feedback |
| `coursectrlmod_forum` | Forum |
| `coursectrlmod_lesson` | Lesson |
| `coursectrlmod_page` | Page |
| `coursectrlmod_h5pactivity` | H5P Activity |
| `coursectrlmod_workshop` | Workshop |

Subplugins for activity types not installed on the site are silently ignored.

---

## Scheduled Tasks

| Task | Default schedule | Description |
|---|---|---|
| `purge_old_batches` | Daily 03:00 | Removes batch records beyond retention limits |

---

## Privacy

This plugin stores bulk action history, pre-action snapshots, and user presets
in the Moodle database. It implements the Moodle Privacy API for GDPR compliance:
user data can be exported and deleted on request. Risk assessment results and
text hit records contain no personal data.

Details: [docs/user-guide.md § 3.5](docs/user-guide.md#35-privacy--data-retention)

---

## Documentation

| Document | Audience |
|---|---|
| [docs/user-guide.md](docs/user-guide.md) | Administrators, teachers |

---

## Support

Please report bugs and feature requests via the project's issue tracker.

---

## License

Course Control Hub is free software: you can redistribute it and/or modify it
under the terms of the GNU General Public License as published by the Free
Software Foundation, either version 3 of the License, or (at your option)
any later version.

This program is distributed in the hope that it will be useful, but WITHOUT
ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

Copyright © 2026 Ralf Erlebach
