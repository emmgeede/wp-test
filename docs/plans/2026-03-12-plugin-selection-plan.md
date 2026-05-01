# Plugin Selection During Provisioning — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add a multi-select checkbox TUI screen to choose which test plugins to activate during provisioning.

**Architecture:** New bubbletea `ChecklistModel` in `internal/tui/checklist.go`, wired into `main.go` after WPfaker mode selection. Selected plugins are passed as `ACTIVATE_PLUGINS` env var to `provision.sh`, which replaces the hardcoded activate-all/deactivate approach.

**Tech Stack:** Go 1.25, Charm bubbletea, Cobra CLI, bash

---

### Task 1: Create ChecklistModel

**Files:**
- Create: `internal/tui/checklist.go`

**Step 1: Create the checklist bubbletea model**

```go
package tui

import (
	"fmt"
	"strings"

	tea "github.com/charmbracelet/bubbletea"
)

// ChecklistItem represents a selectable item.
type ChecklistItem struct {
	Label   string
	Key     string
	Checked bool
}

// ChecklistModel is a multi-select checklist.
type ChecklistModel struct {
	title    string
	items    []ChecklistItem
	cursor   int
	quitting bool
	allIdx   int // index of the "All" toggle, -1 if none
}

// NewChecklistModel creates a checklist with an "All" toggle at position 0.
func NewChecklistModel(title string, items []ChecklistItem) ChecklistModel {
	// Prepend "All" item
	all := ChecklistItem{Label: "All", Key: "_all"}
	withAll := append([]ChecklistItem{all}, items...)
	return ChecklistModel{
		title:  title,
		items:  withAll,
		allIdx: 0,
	}
}

func (m ChecklistModel) Init() tea.Cmd {
	return nil
}

func (m ChecklistModel) Update(msg tea.Msg) (tea.Model, tea.Cmd) {
	switch msg := msg.(type) {
	case tea.KeyMsg:
		switch msg.String() {
		case "ctrl+c", "q":
			m.quitting = true
			return m, tea.Quit
		case "up", "k":
			if m.cursor > 0 {
				m.cursor--
			}
		case "down", "j":
			if m.cursor < len(m.items)-1 {
				m.cursor++
			}
		case " ":
			m.toggle(m.cursor)
		case "enter":
			return m, tea.Quit
		}
	}
	return m, nil
}

func (m *ChecklistModel) toggle(idx int) {
	if idx == m.allIdx {
		// Toggle all
		newState := !m.items[m.allIdx].Checked
		for i := range m.items {
			m.items[i].Checked = newState
		}
	} else {
		m.items[idx].Checked = !m.items[idx].Checked
		// Sync "All" checkbox
		if m.allIdx >= 0 {
			allChecked := true
			for i, item := range m.items {
				if i == m.allIdx {
					continue
				}
				if !item.Checked {
					allChecked = false
					break
				}
			}
			m.items[m.allIdx].Checked = allChecked
		}
	}
}

func (m ChecklistModel) View() string {
	s := styleTitle.Render(m.title) + "\n"
	for i, item := range m.items {
		cursor := "  "
		if i == m.cursor {
			cursor = styleCursor.Render("▸ ")
		}

		check := "[ ]"
		if item.Checked {
			check = styleSelected.Render("[✓]")
		}

		label := item.Label
		if i == m.cursor {
			label = styleSelected.Render(label)
		}

		s += fmt.Sprintf("%s%s %s\n", cursor, check, label)

		// Separator after "All"
		if i == m.allIdx {
			s += styleDim.Render("  ─────────────────") + "\n"
		}
	}
	s += styleDim.Render("\n  Space: toggle · Enter: confirm · q: cancel") + "\n"
	return s
}

// Selected returns the keys of all checked items (excluding "_all").
func (m ChecklistModel) Selected() []string {
	if m.quitting {
		return nil
	}
	var sel []string
	for _, item := range m.items {
		if item.Checked && item.Key != "_all" {
			sel = append(sel, item.Key)
		}
	}
	return sel
}

// Cancelled returns true if the user quit without confirming.
func (m ChecklistModel) Cancelled() bool {
	return m.quitting
}
```

**Step 2: Verify it compiles**

Run: `cd /home/emmgee/Projects/wp-test && go build ./internal/tui/...`
Expected: No errors

**Step 3: Commit**

```bash
git add internal/tui/checklist.go
git commit -m "feat: add ChecklistModel for multi-select TUI"
```

---

### Task 2: Wire checklist into main.go

**Files:**
- Modify: `cmd/wpt/main.go`

**Step 1: Add pluginsFlag variable and plugin items**

After the `wpfakerFlag` variable (line 17), add:

```go
var pluginsFlag string
```

In `init()`, add to `provisionCmd`:

```go
provisionCmd.Flags().StringVar(&pluginsFlag, "plugins", "", "Comma-separated plugins to activate (e.g. acf-pro,metabox)")
```

**Step 2: Add plugin checklist to interactive flow**

In `runInteractive()`, after the WPfaker mode selection block (after line 82), add the plugin selection checklist:

```go
	// Plugin selection for provision
	if chosen == "provision" {
		pluginItems := []tui.ChecklistItem{
			{Label: "ACF Pro", Key: "advanced-custom-fields-pro"},
			{Label: "ACPT", Key: "advanced-custom-post-type"},
			{Label: "CPT UI", Key: "custom-post-type-ui"},
			{Label: "JetEngine", Key: "jet-engine"},
			{Label: "Meta Box", Key: "meta-box"},
			{Label: "Meta Box AIO", Key: "meta-box-aio"},
		}
		cl := tui.NewChecklistModel("Which test plugins should be activated?", pluginItems)
		p3 := tea.NewProgram(cl)
		result3, err := p3.Run()
		if err != nil {
			return err
		}
		clModel := result3.(tui.ChecklistModel)
		if clModel.Cancelled() {
			return nil
		}
		pluginsFlag = strings.Join(clModel.Selected(), ",")
	}
```

Add `"strings"` to the imports.

**Step 3: Pass pluginsFlag to provisionCmd**

In `provisionCmd.RunE`, change the call to `docker.ProvisionSteps` to also pass the plugins:

```go
mode := docker.WPfakerMode(wpfakerFlag)
steps := docker.ProvisionSteps(paths, mode, pluginsFlag)
```

**Step 4: Verify it compiles (will fail — provision.go not updated yet)**

Run: `cd /home/emmgee/Projects/wp-test && go build ./...`
Expected: Error about wrong number of arguments to `ProvisionSteps` (expected, fixed in Task 3)

**Step 5: Commit**

```bash
git add cmd/wpt/main.go
git commit -m "feat: wire plugin checklist into interactive provisioning"
```

---

### Task 3: Update provision.go to pass ACTIVATE_PLUGINS

**Files:**
- Modify: `internal/docker/provision.go`

**Step 1: Add plugins parameter to ProvisionSteps**

Change the function signature from:
```go
func ProvisionSteps(paths *config.Paths, mode WPfakerMode) []ProvisionStep {
```
to:
```go
func ProvisionSteps(paths *config.Paths, mode WPfakerMode, plugins string) []ProvisionStep {
```

In the "Running provision script" step, add `ACTIVATE_PLUGINS` to the env:

```go
{
	Name: "Running provision script",
	Fn: func() error {
		cmd := exec.Command("bash", filepath.Join(paths.Blueprint, "provision.sh"))
		cmd.Env = append(os.Environ(),
			fmt.Sprintf("WPFAKER=%s", mode),
			fmt.Sprintf("ACTIVATE_PLUGINS=%s", plugins),
		)
		out, err := cmd.CombinedOutput()
		if err != nil {
			return fmt.Errorf("%s: %w", string(out), err)
		}
		return nil
	},
},
```

**Step 2: Verify it compiles**

Run: `cd /home/emmgee/Projects/wp-test && go build ./...`
Expected: No errors

**Step 3: Commit**

```bash
git add internal/docker/provision.go cmd/wpt/main.go
git commit -m "feat: pass ACTIVATE_PLUGINS env var to provision script"
```

---

### Task 4: Update provision.sh to use ACTIVATE_PLUGINS

**Files:**
- Modify: `Blueprint/provision.sh`

**Step 1: Replace Task 3 (activate all) and Task 9 (set default state)**

Replace the current Task 3 block (lines 40-59) with:

```bash
# ---------------------------------------------------------------------------
# Task 3: Activate selected test plugins
# ---------------------------------------------------------------------------
section "Task 3: Activate selected test plugins"

ALL_PLUGINS=(
    advanced-custom-fields-pro
    advanced-custom-post-type
    custom-post-type-ui
    jet-engine
    meta-box
    meta-box-aio
)

# Temporarily activate all plugins so schema imports work
echo "Activating all plugins for schema import..."
for plugin in "${ALL_PLUGINS[@]}"; do
    if ! $WP plugin is-active "$plugin" 2>/dev/null; then
        $WP plugin activate "$plugin" 2>/dev/null && echo "  → Activated $plugin" || echo "  ✗ Failed to activate $plugin"
    fi
done
```

Replace the current Task 9 block (lines 229-253) with:

```bash
# ---------------------------------------------------------------------------
# Task 9: Set final plugin state
# ---------------------------------------------------------------------------
section "Task 9: Set final plugin state"

# Deactivate all test plugins first
echo "Deactivating all test plugins..."
for plugin in "${ALL_PLUGINS[@]}"; do
    if $WP plugin is-active "$plugin" 2>/dev/null; then
        $WP plugin deactivate "$plugin" 2>/dev/null
    fi
done

# Activate only selected plugins
if [ -n "${ACTIVATE_PLUGINS:-}" ]; then
    echo "Activating selected plugins..."
    IFS=',' read -ra SELECTED <<< "$ACTIVATE_PLUGINS"
    for plugin in "${SELECTED[@]}"; do
        plugin=$(echo "$plugin" | xargs)  # trim whitespace
        if $WP plugin activate "$plugin" 2>/dev/null; then
            echo "  → Activated $plugin"
        else
            echo "  ✗ Failed to activate $plugin"
        fi
    done
else
    echo "No plugins selected for activation."
fi
```

**Step 2: Test manually**

Run: `cd /home/emmgee/Projects/wp-test && wpt provision`
- Select WPfaker mode → None
- Check ACF Pro and Meta Box in the plugin checklist
- Verify only those two are active after provisioning

**Step 3: Commit**

```bash
git add Blueprint/provision.sh
git commit -m "feat: activate only selected plugins during provisioning"
```

---

### Task 5: Verify end-to-end and clean up

**Step 1: Test interactive flow**

Run: `cd /home/emmgee/Projects/wp-test && wpt`
1. Select "Provision"
2. Select WPfaker mode
3. Plugin checklist appears — toggle some plugins
4. Provisioning runs, only selected plugins are active

**Step 2: Test CLI flag flow**

Run: `cd /home/emmgee/Projects/wp-test && wpt provision --wpfaker none --plugins advanced-custom-fields-pro,jet-engine`
Verify: Only ACF Pro and JetEngine active after provisioning.

**Step 3: Test "All" toggle**

Run interactive, select "All" → verify all 6 checked. Deselect one → "All" unchecked. Re-check that one → "All" re-checked.

**Step 4: Test empty selection**

Run interactive, don't select any plugins, hit Enter → verify all 6 deactivated.

**Step 5: Final commit if any adjustments needed**

```bash
git add -A
git commit -m "fix: adjustments from end-to-end testing"
```
