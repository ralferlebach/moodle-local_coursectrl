# Course Control Hub (`local_coursectrl`)

A Moodle local plugin for teachers and course administrators to analyse,
validate and bulk-edit course structure, dates and availability logic.

---

## Features

### Dashboard (Cockpit)
A course-level overview showing:
- **Stat tiles** — section count, activity count, editable text count, open problems
- **Collapsible calendar** — visual month grid linked to the timeline
- **Problem summary** — errors, warnings and notices grouped by severity with direct links to affected activities and action buttons to the detail and deep-analysis views
- **Upcoming dates** — next N structured dates (configurable) sorted chronologically
- **Dates in texts** — date references found in free-text fields from the last text scan

### Timeline & Text Review
- Chronological list of all course dates grouped by day and time slot
- Per-slot and per-entry shift buttons opening a 3-step workflow modal (configure → preview → confirm)
- Group and activity-type filter
- Gantt overview tab
- **Text Review tab** — table of date references found in activity descriptions, labels and section texts with confidence classification (safe / ambiguous / informational), bulk selection and AJAX-based apply

### Dependency Graph
- Interactive graph of completion dependencies between activities
- Group-aware filter, hide-independent-activities toggle
- Simulation overlay (blocked = red, next step = green)

### Checks (Consistency + Risk Assessment + Simulation)
- **Consistency tab** — transient live checks on every page load:
  temporal conflicts, dangling prerequisites, unreachable dependencies,
  adapter-specific R3/R7 checks; direct fix buttons per finding
- **Risk Assessment tab** — persistent structural analysis triggered on demand:
  dead ends, circular dependencies, long chains, priority scoring; fix buttons
  route to the appropriate tool (timeline, edit page, dependency graph)
- **Simulation tab** — learner-perspective simulation with configurable date,
  group membership and assumed completion states

### History & Rollback
- Paginated log of all executed bulk actions
- Per-batch rollback using pre-operation snapshots (where available)

### Subplugin Architecture
Activity-type-specific logic is encapsulated in `coursectrlmod_*` subplugins,
allowing new activity types to be supported without modifying the core plugin.

Supported activity types (included subplugins):
`assign`, `quiz`, `feedback`, `forum`, `lesson`, `page`, `h5pactivity`, `workshop`

Optional third-party activity support (installed separately, Moodle ≥ 5.0):
`questionnaire`, `choicegroup`, `studentquiz`, `capquiz`

---

## Requirements

| Component | Minimum version |
|---|---|
| Moodle | 4.5 |
| PHP | 8.2 |

Tested against Moodle 4.5, 5.0 and 5.1 with PHP 8.2, 8.3 and 8.4
on MariaDB 10.11 and PostgreSQL 16.

---

## Installation

1. Clone or unzip into `local/coursectrl/` inside your Moodle root.
2. Visit **Site administration → Notifications** to run the installer.
3. Assign the `local/coursectrl:view` and `local/coursectrl:bulkaction`
   capabilities to the roles that should use the plugin
   (editing teachers have these by default after install).
4. Add the **Course Control Hub** block to any course,
   or navigate directly via the course navigation.

---

## Capabilities

| Capability | Default roles | Description |
|---|---|---|
| `local/coursectrl:view` | editingteacher, manager | Access the plugin in a course |
| `local/coursectrl:bulkaction` | editingteacher, manager | Execute bulk date/visibility changes |
| `local/coursectrl:rollback` | manager | Roll back a previously executed batch |

---

## Admin Settings

Settings are found under **Site administration → Plugins → Local plugins → Course Control Hub**.

### Dashboard

| Setting | Default | Description |
|---|---|---|
| `dashboard_inventory` | `admin_only` | Who sees the full course inventory list: `hide`, `admin_only`, `show` |
| `dashboard_upcoming_count` | `7` | Number of upcoming dates shown in the dashboard summary |
| `dashboard_warning_cap` | `0` | Max warnings shown per severity level; `0` = same as upcoming count |
| `dashboard_textfind_count` | `0` | Max text hits shown; `0` = same as upcoming count |

### History / Audit

| Setting | Default | Description |
|---|---|---|
| `history_maxcount` | `100` | Maximum batch records shown per course on the history page |
| `history_maxdays` | `365` | Batch records older than this many days are removed by the scheduled cleanup task |

### Risk Assessment

Per-adapter severity levels for R7 soft-deadline checks are configurable
in the **Installed activity adapters** section.

---

## Scheduled Tasks

| Task | Default schedule | Description |
|---|---|---|
| `purge_old_batches` | Daily at 03:00 | Removes batch history records beyond the configured retention limits |

---

## Subplugin Development

To add support for a new activity type, create a `coursectrlmod_<modname>`
subplugin in `local/coursectrl/mod/<modname>/` implementing the
`local_coursectrl\local\contract\activity_adapter` interface.

See `mod/assign/classes/adapter.php` as a reference implementation.

---

## Privacy

This plugin stores the following personal data:

- **Batch records** (`local_coursectrl_batch`) — userid, courseid, action, timestamps
- **Presets** (`local_coursectrl_preset`) — userid, courseid, preset configuration
- **Reports** (`local_coursectrl_report`) — userid, courseid, report results

All data is exportable and deletable via the Moodle Privacy API.
User preferences (`local_coursectrl_showcalendar`, `local_coursectrl_immediateapply`)
are also exported and deleted on request.

---

## License

GNU General Public License v3 or later — see [LICENSE](LICENSE).

Copyright 2026 Ralf Erlebach
