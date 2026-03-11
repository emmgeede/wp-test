# WPT CLI Tool — Design

## Goal

Replace the Makefile with a Go CLI tool (`wpt`) that uses Bubbletea for interactive menus and Cobra for direct command execution. Manages the wp-test Docker environment for testing WPfaker against 6 field-management plugins.

## Architecture

Two execution modes:

```
wpt                          → Bubbletea TUI with main menu
wpt <command> [flags]        → Direct execution (no TUI)
```

The tool replaces the Makefile entirely. It calls Docker Compose and WP-CLI directly — no `make` dependency.

## Commands

| Command | Flags | Description |
|---------|-------|-------------|
| `wpt provision` | `--wpfaker=local\|zip\|none` | Full setup: containers + plugins + schemas + snapshot |
| `wpt up` | `--wpfaker=local` | Start containers only |
| `wpt down` | | Stop containers (keep volumes) |
| `wpt reset` | | Restore DB from golden snapshot |
| `wpt snapshot` | | Save current DB as golden snapshot |
| `wpt destroy` | | Remove containers + volumes |
| `wpt status` | | Show container status + active plugins |
| `wpt logs` | | Tail container logs |

## TUI Main Menu

When running `wpt` without arguments:

```
WP Test Environment

  > Provision (full setup)
    Up (start containers)
    Reset (restore snapshot)
    Status
    Down (stop)
    Destroy (remove all)
    Logs
```

Selecting "Provision" shows a sub-menu:

```
WPfaker Mode:
  > None (test plugins only)
    Local (mount ~/Projects/wpfaker)
    Zip (install from dist/)
```

## UX: Spinner + Summary

Long-running operations show a Bubbletea spinner with step descriptions:

```
⣾ Waiting for WordPress...
⣾ Activating plugins...
⣾ Importing schemas...
⣾ Creating snapshot...
```

On completion, a summary replaces the spinner:

```
✓ Provisioning complete
  Plugins: ACF Pro (active), CPTUI, Meta Box, JetEngine, ACPT (inactive)
  WPfaker: local mount (active)
  Snapshot: 208K
  URL: http://wpfaker-test.dv:8089
```

On error, the spinner turns red and shows the error inline. Execution stops.

## Project Structure

```
cmd/wpt/
  main.go              # Entry point, Cobra root command
internal/
  tui/
    menu.go            # Bubbletea main menu
    provision.go       # WPfaker mode selection
    spinner.go         # Spinner + status display
  docker/
    compose.go         # Docker Compose command execution
    provision.go       # Provision logic (plugins, schemas, snapshot)
  config/
    paths.go           # Paths (Blueprint, Docker, Snapshots, WPfaker)
go.mod
go.sum
```

## Dependencies

- `github.com/charmbracelet/bubbletea` — TUI framework
- `github.com/charmbracelet/lipgloss` — Styling
- `github.com/charmbracelet/bubbles` — Spinner, list components
- `github.com/spf13/cobra` — CLI command parsing

## WPfaker Modes

- **none** (default): Only test plugins, no WPfaker
- **local**: Mounts `~/Projects/wpfaker/` into both WordPress and Caddy containers via docker-compose override file, activates plugin
- **zip**: Copies latest zip from `~/Projects/wpfaker/dist/` into container, installs via `wp plugin install`, activates

## Docker Compose Integration

The tool manages two compose files:
- `docker-compose.yml` — base configuration
- `docker-compose.wpfaker.yml` — override for local WPfaker mount

When `--wpfaker=local`, both files are passed: `docker compose -f docker-compose.yml -f docker-compose.wpfaker.yml up -d`

Blueprint files are copied to `Docker/` before each `up` operation (same pattern as current Makefile).

## Provision Steps

1. Copy Blueprint files to Docker/
2. `docker compose up -d` (with override if local)
3. Wait for WordPress (`wp core is-installed`)
4. Fix uploads directory permissions
5. Activate all 6 test plugins
6. Import ACF Pro schemas (field groups, post types, taxonomies)
7. Import CPTUI schemas
8. Import Meta Box schemas
9. Import JetEngine schemas
10. Import ACPT schemas
11. Set default state (deactivate non-default plugins, keep ACF Pro active)
12. Install/activate WPfaker (based on mode)
13. Flush rewrite rules
14. Create golden snapshot

## Error Handling

- Docker not running → clear error message, exit
- Container not found → suggest running `wpt provision` first
- Snapshot missing on reset → suggest running `wpt provision` first
- WPfaker zip not found → suggest running `npm run build` in wpfaker
- Plugin activation failure → show WP-CLI error, continue with next plugin
