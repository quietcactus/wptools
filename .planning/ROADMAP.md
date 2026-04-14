# WPTools — Roadmap

## Milestones

- ✅ **v1.0 Image Converter/Compressor** — Phases 1–3 (shipped 2026-04-13)
- 🚧 **v1.1 Image Converter Search and Filters** — Phases 4–7 (in progress)

## Phases

<details>
<summary>✅ v1.0 Image Converter/Compressor (Phases 1–3) — SHIPPED 2026-04-13</summary>

- [x] Phase 1: Tool Scaffold + API Layer (1/1 plans) — completed 2026-04-13
- [x] Phase 2: Media Library Selector (2/2 plans) — completed 2026-04-13
- [x] Phase 3: Confirmation, Processing & Results (2/2 plans) — completed 2026-04-13

Full details: [milestones/v1.0-ROADMAP.md](milestones/v1.0-ROADMAP.md)

</details>

### 🚧 v1.1 Image Converter Search and Filters (In Progress)

**Milestone Goal:** Add a collapsible search/filter toolbar with server-side filtering, sort controls, pagination, and thumbnail previews to the image converter's Media Library image list.

- [ ] **Phase 4: Backend Extension + Thumbnails** - Extend AJAX handler for filter/sort/page params; add thumbnail to each row
- [ ] **Phase 5: Filter Panel, Search, and Filters** - Collapsible filter panel with search input and type/date filter controls wired to the backend
- [ ] **Phase 6: Sort Controls** - Sort dropdowns within the filter panel for date, file size, and filename
- [ ] **Phase 7: Pagination UI** - Page navigation controls and result count display

## Phase Details

### Phase 4: Backend Extension + Thumbnails
**Goal**: The AJAX handler accepts search, filter, sort, and page params; each image row shows a thumbnail
**Depends on**: Phase 3 (v1.0 complete)
**Requirements**: THUMB-01, PAGE-01
**Success Criteria** (what must be TRUE):
  1. Each image row displays a small WordPress-generated thumbnail image
  2. The AJAX handler returns only images matching passed filter/sort/page params when called with those params
  3. The handler returns at most 50 images per page when a page param is provided
  4. The handler returns a total attachment count alongside the result set
**Plans**: 2 plans
Plans:
- [x] 04-01-PLAN.md — Extend PHP AJAX handler with WP_Query, params, pagination, and thumbnail_html
- [x] 04-02-PLAN.md — Update JS response consumption and add thumbnail column CSS
**UI hint**: yes

### Phase 5: Filter Panel, Search, and Filters
**Goal**: Users can open a filter panel, search images by name, and filter by type and date
**Depends on**: Phase 4
**Requirements**: PANEL-01, PANEL-02, SEARCH-01, SEARCH-02, FILTER-01, FILTER-02
**Success Criteria** (what must be TRUE):
  1. The filter panel appears above the image list, collapsed by default on page load
  2. User can click a toggle button to expand and collapse the filter panel
  3. User can type in a search field and see the image list replace with server results matching the filename or title
  4. User can select an image type (JPG / PNG / WebP / All) and see the list update to show only that type
  5. User can select an upload year and/or month from dropdowns and see the list update accordingly
**Plans**: 2 plans
Plans:
- [ ] 05-01-PLAN.md — Filter panel HTML markup and CSS (collapsed by default, toggle button, all filter controls)
- [ ] 05-02-PLAN.md — JS wiring: toggle handler, debounced search, select change handlers, updated fetch function
**UI hint**: yes

### Phase 6: Sort Controls
**Goal**: Users can reorder the image list by date, file size, or filename
**Depends on**: Phase 5
**Requirements**: SORT-01, SORT-02, SORT-03
**Success Criteria** (what must be TRUE):
  1. User can select "newest" or "oldest" and see the image list reorder by upload date
  2. User can select "largest" or "smallest" and see the image list reorder by file size
  3. User can select "A–Z" or "Z–A" and see the image list reorder by filename
  4. Sort selection persists alongside active search and filter values when the list refreshes
**Plans**: 2 plans
Plans:
- [x] 06-01-PLAN.md — Sort filter group HTML in PHP render function (select with 6 combined orderby-order options)
- [x] 06-02-PLAN.md — JS wiring: extend imageconv_get_filter_params with orderby+order, add sort change handler
**UI hint**: yes

### Phase 7: Pagination UI
**Goal**: Users can navigate through large result sets one page at a time
**Depends on**: Phase 6
**Requirements**: PAGE-02, PAGE-03
**Success Criteria** (what must be TRUE):
  1. User can click next/previous (or page number) controls to navigate between pages
  2. Active search, filter, and sort state is preserved when navigating between pages
  3. The current page number and total result count are visible on screen at all times
**Plans**: 2 plans
Plans:
- [ ] 07-01-PLAN.md — Pagination HTML shells in PHP render function + pagination CSS
- [ ] 07-02-PLAN.md — JS wiring: imageconv_render_pagination(), result count update, page button click handler
**UI hint**: yes

## Progress

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 1. Tool Scaffold + API Layer | v1.0 | 1/1 | Complete | 2026-04-13 |
| 2. Media Library Selector | v1.0 | 2/2 | Complete | 2026-04-13 |
| 3. Confirmation, Processing & Results | v1.0 | 2/2 | Complete | 2026-04-13 |
| 4. Backend Extension + Thumbnails | v1.1 | 2/2 | Complete | 2026-04-14 |
| 5. Filter Panel, Search, and Filters | v1.1 | 2/2 | Complete | 2026-04-14 |
| 6. Sort Controls | v1.1 | 2/2 | Complete | 2026-04-14 |
| 7. Pagination UI | v1.1 | 0/2 | Not started | - |
