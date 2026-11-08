# TaisarTownHub — Live Interactive States & Modals (Pillar 11)

**Version:** 2.0.0 · **Last updated:** 2026-11-08

## 1. Dynamic UI Patterns

### Instant search & filtering
- Hero search → `onSearch(criteria)` → `setCurrentView('properties')` with `activeFilterCriteria`.
- Properties listing reads `initialFilter` and applies client-side filter on phase/sector/budget/size/type.
- Phase filter tabs on Media Gallery (All/Phase 1/2/3).
- Inquiry status filter (All/Pending/Contacted/In Progress/Resolved).

### Form steps
- Property add/edit modal: single form with validation; submit triggers API; modal stays open on error.
- Deal execution modal: multi-field form with live commission calculation.
- Video publish modal: form with dealer selector, phase/sector, duration.

### Confirmation modals
- `ConfirmDialogHost` — global accessible dialog replacing `window.confirm`.
- Delete confirmations on property/dealer/transaction/file.
- Soft-delete → Recycle Bin (not permanent) unless explicitly purged.

## 2. Modal Behavior Standards

- Open via state flag (`isModalOpen`).
- Close via X button, Escape key, or backdrop click (where safe).
- On submit: disable button, show spinner, then success/error toast.
- On failure: keep modal open with entered values (no data loss).
- Focus trapped within modal; restored on close.

## 3. Live States

| State | Trigger | Behavior |
|---|---|---|
| Like toggle | click heart | optimistic local update + backend increment (only on like, not un-like); localStorage tracks visitor state |
| Comment post | submit | append to list, clear input, toast |
| Video switcher | click dot | cross-fade, update activeSlideIndex |
| Slide autoplay | 4.5s interval | disabled under reduced motion |
| Parallax | scroll | background translate3d, content fade |
| Stats counter | in-view | animated count-up |
| Toast | mutation result | auto-dismiss, stackable |

## 4. Optimistic Updates with Rollback

```ts
// likeAgencyVideo
setAgencyVideos(prev => prev.map(v => v.id === id ? { ...v, likes: v.likes + 1 } : v));
if (backendConnected) api.likeVideo(id).catch(() => { /* revert optional */ });
```

- On failure, UI reverts or shows error; never claims success.

## 5. Loading & Skeleton States

- `Suspense` fallbacks for lazy chunks.
- `Loading content...` / `Loading dashboard...` placeholders.
- Skeleton rows in tables during fetch.

## 6. Empty States

- "No Open Inquiries" with icon + hint.
- "No properties match your filters" with reset action.
- Honest counts (no fake "0 available" success banners).
