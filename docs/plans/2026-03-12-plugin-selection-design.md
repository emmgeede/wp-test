# Plugin Selection During Provisioning — Design

## Problem

Currently, `provision.sh` activates all 6 test plugins, imports all schemas, then deactivates 5 — keeping only ACF Pro. There's no way to choose which plugins should be active after provisioning.

## Solution

Add a multi-select checkbox screen to the TUI after the WPfaker mode selection. Users pick which test plugins to activate. A toggle-all "All" option is included.

## TUI Screen

```
Which test plugins should be activated?

  [ ] All
  ───────────────
  [ ] ACF Pro
  [ ] ACPT
  [ ] CPT UI
  [ ] JetEngine
  [ ] Meta Box
  [ ] Meta Box AIO

  Space: toggle · Enter: confirm · q: cancel
```

**Behavior:**
- Appears after WPfaker mode selection, before provision starts
- Default: all unchecked
- "All" toggles all 6 plugins on/off
- Deselecting any individual plugin unchecks "All"
- Selecting all 6 individually auto-checks "All"
- Enter with 0 selections = no plugins activated (valid)

## Data Flow

```
TUI (plugins.go) → main.go → provision.go → provision.sh
                                              ↓
                                   ACTIVATE_PLUGINS env var
                                   (comma-separated slugs)
```

1. New `ChecklistModel` in `internal/tui/plugins.go` — bubbletea multi-select
2. `main.go` shows checklist after WPfaker menu, stores selected plugin slugs
3. `provision.go` passes `ACTIVATE_PLUGINS` env var to `provision.sh`
4. `provision.sh` replaces Task 3 + Task 9 with single activation loop reading `ACTIVATE_PLUGINS`

## Plugin Slugs

| Display Name | Slug (directory name) |
|---|---|
| ACF Pro | `advanced-custom-fields-pro` |
| ACPT | `advanced-custom-post-type` |
| CPT UI | `custom-post-type-ui` |
| JetEngine | `jet-engine` |
| Meta Box | `meta-box` |
| Meta Box AIO | `meta-box-aio` |

## provision.sh Changes

- All schemas are still imported regardless of selection (idempotent, no harm)
- Task 3 (activate all) and Task 9 (deactivate) are replaced by a single block:
  - Deactivate all 6 plugins
  - Activate only plugins listed in `ACTIVATE_PLUGINS`
- If `ACTIVATE_PLUGINS` is empty, all 6 remain deactivated

## CLI Flag

`wpt provision --plugins acf-pro,metabox` as shorthand (optional, skips TUI checklist). Plugin keys map to slugs. If `--plugins` is not provided in CLI mode, defaults to none (same as before). Interactive mode always shows the checklist.
