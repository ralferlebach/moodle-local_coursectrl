# Course Control Hub (`local_coursectrl`)

A Moodle local plugin for teachers and course administrators to analyse,
validate and bulk-edit course structure, dates and availability logic.

## Features

### Dashboard (Cockpit)
- Stat tiles: section count, activity count, editable text count, open problems
- Collapsible calendar linked to the timeline
- Problem summary: errors, warnings, notices with direct links and action buttons
- Upcoming dates: next N structured dates sorted chronologically
- Dates in texts: date references found in free-text fields

### Timeline & Text Review
- Chronological date list with per-slot and per-entry shift buttons
- 3-step shift workflow modal (configure → preview → confirm)
- Group and activity-type filter, Gantt tab
- Text Review tab: AJAX-based date reference review table

### Dependency Graph
- Interactive completion dependency graph with group-aware filter
- Simulation overlay (blocked = red, next step = green)

### Checks (Consistency + Risk Assessment + Simulation)
- **Consistency**: live transient checks (temporal conflicts, dangling/impossible prerequisites, adapter checks)
- **Risk Assessment**: persistent structural analysis (dead ends, circular deps, scoring)
- **Simulation**: learner-perspective simulation with date, group and completion state parameters

### History & Rollback
- Paginated audit log of all executed bulk actions
- Per-batch rollback via pre-operation snapshots

### Subplugins
Activity-type logic is encapsulated in `coursectrlmod_*` subplugins.
Supported: `assign`, `quiz`, `feedback`, `forum`, `lesson`, `page`, `h5pactivity`, `workshop`

## Requirements

| Component | Minimum |
|---|---|
| Moodle | 4.5 |
| PHP | 8.2 |

Tested on Moodle 4.5, 5.0, 5.1, 5.2 with PHP 8.2–8.4, MariaDB 10.11, PostgreSQL 16.

## Installation

1. Clone or unzip into `local/coursectrl/`
2. Run **Site administration → Notifications**
3. Assign `local/coursectrl:view` and `local/coursectrl:bulkaction` capabilities

## Capabilities

| Capability | Default | Description |
|---|---|---|
| `local/coursectrl:view` | editingteacher | Access the plugin |
| `local/coursectrl:bulkaction` | editingteacher | Execute bulk changes |
| `local/coursectrl:rollback` | manager | Roll back batches |

## Admin Settings

**Dashboard:** `dashboard_inventory` (hide/admin_only/show), `dashboard_upcoming_count` (7),
`dashboard_warning_cap` (0=auto), `dashboard_textfind_count` (0=auto)

**History:** `history_maxcount` (100), `history_maxdays` (365)

## Scheduled Tasks

| Task | Schedule | Description |
|---|---|---|
| `purge_old_batches` | Daily 03:00 | Remove batch history beyond retention limits |

## License

GNU General Public License v3 or later. Copyright 2026 Ralf Erlebach.
