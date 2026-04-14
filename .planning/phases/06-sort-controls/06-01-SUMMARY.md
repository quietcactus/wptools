---
phase: 06-sort-controls
plan: "01"
subsystem: ui
tags: [php, wordpress, filter-panel, sort, select, html]

# Dependency graph
requires:
  - phase: 05-filter-panel-search-and-filters
    provides: Filter panel HTML structure with .wptools-imageconv-filter-row and four filter groups
provides:
  - Sort filter group HTML: label + select#wptools-imageconv-sort with 6 combined orderby-order options
affects: [06-02-sort-controls-js]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Combined orderby-order option value format: '{orderby}-{order}' (e.g. date-DESC) — JS splits on '-' to extract both params"

key-files:
  created: []
  modified:
    - tools/image-converter/image-converter.php

key-decisions:
  - "Combined select encodes both orderby and order in a single value (e.g. date-DESC) — keeps UI compact and JS simple"
  - "First option (date-DESC) is browser default — no selected attribute needed"
  - "filesize-DESC splits cleanly to ['filesize','DESC'] on '-' — no ambiguity in value format"

patterns-established:
  - "Sort option values use '{orderby}-{order}' format for easy JS parsing via split('-')"

requirements-completed: [SORT-01, SORT-02, SORT-03]

# Metrics
duration: 10min
completed: 2026-04-14
---

# Phase 06 Plan 01: Sort Controls — HTML Summary

**Sort filter group appended to filter panel as fifth .wptools-imageconv-filter-group, with select#wptools-imageconv-sort offering 6 combined orderby-order options (date/filesize/title x ASC/DESC)**

## Performance

- **Duration:** ~10 min
- **Started:** 2026-04-14T18:50:00Z
- **Completed:** 2026-04-14T18:59:00Z
- **Tasks:** 1
- **Files modified:** 1

## Accomplishments
- Added `select#wptools-imageconv-sort` with 6 options inside a new `.wptools-imageconv-filter-group` div
- Sort group appended inside `.wptools-imageconv-filter-row` after the month group — now 5 filter groups total
- Option values use `{orderby}-{order}` format enabling Plan 06-02 JS to extract both params with `split('-')`
- No new CSS required — existing `.wptools-imageconv-filter-group` and `.wptools-imageconv-filter-select` rules cover the new element

## Task Commits

Each task was committed atomically:

1. **Task 1: Add sort filter group HTML to filter panel** - `419bc26` (feat)

**Plan metadata:** (docs commit follows)

## Files Created/Modified
- `tools/image-converter/image-converter.php` - Fifth filter group with sort select added to `wptools_imageconv_render_page()`

## Decisions Made
- Combined select encodes both `orderby` and `order` in a single value (e.g. `date-DESC`) — keeps UI compact and JS parsing simple via `split('-')`
- First option (`date-DESC`, Newest first) is browser default — no `selected` attribute needed
- Literal UTF-8 en-dash (–) used in Name: A–Z / Z–A labels per plan spec, wrapped in `esc_html__()`

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
- `php -l` lint check unavailable (no PHP binary in this environment). Structural verification confirmed via grep: all 6 option values present, 2 matches for `wptools-imageconv-sort` (label for + select id), 5 filter groups total.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- DOM prerequisite for Plan 06-02 JS satisfied: `select#wptools-imageconv-sort` exists inside `.wptools-imageconv-filter-row` with all required option values
- Plan 06-02 JS can wire a `change` handler on `#wptools-imageconv-sort`, split the value on `'-'` to extract `orderby` and `order`, and include both in the AJAX fetch params

---
*Phase: 06-sort-controls*
*Completed: 2026-04-14*
