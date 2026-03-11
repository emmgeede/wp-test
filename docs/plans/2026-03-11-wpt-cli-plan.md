# WPT CLI Tool Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a Go CLI tool (`wpt`) with Bubbletea TUI that replaces the Makefile for managing the wp-test Docker environment.

**Architecture:** Cobra handles CLI parsing and subcommands. When run without args, Bubbletea renders an interactive menu. All Docker/WP-CLI operations run as shell commands via `os/exec`, with a Bubbletea spinner model showing progress. Blueprint → Docker file copying and compose orchestration live in `internal/docker/`.

**Tech Stack:** Go 1.25, Cobra, Bubbletea, Bubbles (spinner/list), Lipgloss

**Design doc:** `docs/plans/2026-03-11-wpt-cli-design.md`

---

### Task 1: Go module + Cobra root command

**Files:**
- Create: `cmd/wpt/main.go`
- Create: `go.mod`

**Step 1: Initialize Go module**

```bash
cd ~/Projects/wp-test
go mod init github.com/emmgeede/wp-test
```

**Step 2: Install Cobra**

```bash
go get github.com/spf13/cobra@latest
```

**Step 3: Create `cmd/wpt/main.go`**

```go
package main

import (
	"fmt"
	"os"

	"github.com/spf13/cobra"
)

var rootCmd = &cobra.Command{
	Use:   "wpt",
	Short: "WP Test Environment Manager",
	Long:  "Manage the WordPress test environment for WPfaker plugin testing.",
	Run: func(cmd *cobra.Command, args []string) {
		// TUI menu will go here in Task 5
		fmt.Println("WPT — run 'wpt --help' for available commands")
	},
}

func main() {
	if err := rootCmd.Execute(); err != nil {
		os.Exit(1)
	}
}
```

**Step 4: Build and verify**

Run: `go build -o wpt ./cmd/wpt && ./wpt --help`
Expected: Help output showing "WP Test Environment Manager"

**Step 5: Commit**

```bash
git add go.mod go.sum cmd/
git commit -m "feat(wpt): init Go module with Cobra root command"
```

---

### Task 2: Config package — paths and constants

**Files:**
- Create: `internal/config/paths.go`

**Step 1: Create `internal/config/paths.go`**

```go
package config

import (
	"os"
	"path/filepath"
)

// ProjectRoot returns the wp-test project root.
// It walks up from the executable or cwd looking for Blueprint/.
func ProjectRoot() (string, error) {
	dir, err := os.Getwd()
	if err != nil {
		return "", err
	}
	for {
		if _, err := os.Stat(filepath.Join(dir, "Blueprint")); err == nil {
			return dir, nil
		}
		parent := filepath.Dir(dir)
		if parent == dir {
			return "", fmt.Errorf("could not find wp-test project root (no Blueprint/ directory found)")
		}
		dir = parent
	}
}

type Paths struct {
	Root      string // wp-test project root
	Blueprint string // Blueprint/ source-of-truth
	Docker    string // Docker/ runtime directory
	Snapshots string // snapshots/ directory
	Golden    string // snapshots/golden.sql.gz
	WPfaker   string // ~/Projects/wpfaker (local dev)
}

func NewPaths() (*Paths, error) {
	root, err := ProjectRoot()
	if err != nil {
		return nil, err
	}
	home, err := os.UserHomeDir()
	if err != nil {
		return nil, err
	}
	return &Paths{
		Root:      root,
		Blueprint: filepath.Join(root, "Blueprint"),
		Docker:    filepath.Join(root, "Docker"),
		Snapshots: filepath.Join(root, "snapshots"),
		Golden:    filepath.Join(root, "snapshots", "golden.sql.gz"),
		WPfaker:   filepath.Join(home, "Projects", "wpfaker"),
	}, nil
}

const (
	ContainerWP    = "wpt-wordpress"
	ContainerMySQL = "wpt-mysql"
	ContainerCaddy = "wpt-caddy"
	SiteURL        = "http://wpfaker-test.dv:8089"
)
```

Add missing `"fmt"` import. The file should compile cleanly.

**Step 2: Build to verify**

Run: `go build ./internal/config/`
Expected: No errors

**Step 3: Commit**

```bash
git add internal/
git commit -m "feat(wpt): add config package with paths and constants"
```

---

### Task 3: Docker package — compose operations

**Files:**
- Create: `internal/docker/compose.go`

This package wraps `docker compose` commands. It does NOT use the Docker SDK — just `os/exec`.

**Step 1: Create `internal/docker/compose.go`**

```go
package docker

import (
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"strings"

	"github.com/emmgeede/wp-test/internal/config"
)

// WPfakerMode controls how WPfaker is installed.
type WPfakerMode string

const (
	WPfakerNone  WPfakerMode = "none"
	WPfakerLocal WPfakerMode = "local"
	WPfakerZip   WPfakerMode = "zip"
)

// Compose runs docker compose commands in the Docker/ directory.
type Compose struct {
	paths  *config.Paths
	mode   WPfakerMode
}

func NewCompose(paths *config.Paths, mode WPfakerMode) *Compose {
	return &Compose{paths: paths, mode: mode}
}

// composeArgs returns the -f flags for docker compose.
func (c *Compose) composeArgs() []string {
	args := []string{"-f", "docker-compose.yml"}
	if c.mode == WPfakerLocal {
		args = append(args, "-f", "docker-compose.wpfaker.yml")
	}
	return args
}

// Run executes a docker compose command in the Docker/ directory.
// Output is captured and returned, not streamed.
func (c *Compose) Run(args ...string) (string, error) {
	composeArgs := append([]string{"compose"}, c.composeArgs()...)
	composeArgs = append(composeArgs, args...)

	cmd := exec.Command("docker", composeArgs...)
	cmd.Dir = c.paths.Docker
	out, err := cmd.CombinedOutput()
	return strings.TrimSpace(string(out)), err
}

// CopyBlueprint copies Blueprint/ files to Docker/.
func (c *Compose) CopyBlueprint() error {
	if err := os.MkdirAll(c.paths.Docker, 0o755); err != nil {
		return err
	}
	files := []string{
		"docker-compose.yml",
		"docker-compose.wpfaker.yml",
		"Caddyfile",
		"wp-setup.sh",
		"php-uploads.ini",
		"acpt-import.php",
	}
	for _, f := range files {
		src := filepath.Join(c.paths.Blueprint, f)
		dst := filepath.Join(c.paths.Docker, f)
		data, err := os.ReadFile(src)
		if err != nil {
			return fmt.Errorf("read %s: %w", f, err)
		}
		if err := os.WriteFile(dst, data, 0o644); err != nil {
			return fmt.Errorf("write %s: %w", f, err)
		}
	}
	return nil
}

// Up starts containers.
func (c *Compose) Up() (string, error) {
	return c.Run("up", "-d")
}

// Down stops containers (keeps volumes).
func (c *Compose) Down() (string, error) {
	return c.Run("down")
}

// Destroy stops containers and removes volumes.
func (c *Compose) Destroy() (string, error) {
	return c.Run("down", "-v")
}

// Ps returns container status.
func (c *Compose) Ps() (string, error) {
	return c.Run("ps")
}

// Logs tails container logs (streams to stdout).
func (c *Compose) Logs() error {
	composeArgs := append([]string{"compose"}, c.composeArgs()...)
	composeArgs = append(composeArgs, "logs", "-f")
	cmd := exec.Command("docker", composeArgs...)
	cmd.Dir = c.paths.Docker
	cmd.Stdout = os.Stdout
	cmd.Stderr = os.Stderr
	return cmd.Run()
}
```

**Step 2: Build to verify**

Run: `go build ./internal/docker/`
Expected: No errors

**Step 3: Commit**

```bash
git add internal/docker/
git commit -m "feat(wpt): add docker compose wrapper package"
```

---

### Task 4: Docker package — WP-CLI and provision operations

**Files:**
- Create: `internal/docker/wp.go`

**Step 1: Create `internal/docker/wp.go`**

```go
package docker

import (
	"fmt"
	"os/exec"
	"strings"
	"time"

	"github.com/emmgeede/wp-test/internal/config"
)

// WP runs WP-CLI commands inside the WordPress container.
func WP(args ...string) (string, error) {
	wpArgs := append([]string{"exec", config.ContainerWP, "wp", "--allow-root", "--path=/var/www/html"}, args...)
	cmd := exec.Command("docker", wpArgs...)
	out, err := cmd.CombinedOutput()
	return strings.TrimSpace(string(out)), err
}

// WaitForWP polls until WordPress is installed (wp core is-installed).
func WaitForWP(timeout time.Duration) error {
	deadline := time.Now().Add(timeout)
	for time.Now().Before(deadline) {
		_, err := WP("core", "is-installed")
		if err == nil {
			return nil
		}
		time.Sleep(2 * time.Second)
	}
	return fmt.Errorf("wordpress not ready after %s", timeout)
}

// FixUploadsPermissions ensures wp-content/uploads is owned by www-data.
func FixUploadsPermissions() error {
	cmd := exec.Command("docker", "exec", config.ContainerWP, "bash", "-c",
		"mkdir -p /var/www/html/wp-content/uploads && chown -R www-data:www-data /var/www/html/wp-content/uploads")
	return cmd.Run()
}

// Snapshot exports the DB to golden.sql.gz.
func Snapshot(goldenPath string) error {
	cmd := exec.Command("bash", "-c",
		fmt.Sprintf("docker exec %s mysqldump --skip-ssl -h db -u wordpress -pwordpress --no-tablespaces wordpress | gzip > %s",
			config.ContainerWP, goldenPath))
	return cmd.Run()
}

// Reset imports the golden snapshot.
func Reset(goldenPath string) error {
	cmd := exec.Command("bash", "-c",
		fmt.Sprintf("gunzip -c %s | docker exec -i %s mysql --skip-ssl -h db -u wordpress -pwordpress wordpress",
			goldenPath, config.ContainerWP))
	if err := cmd.Run(); err != nil {
		return err
	}
	WP("cache", "flush") // ignore error
	return nil
}

// PluginList returns active plugin names.
func PluginList() (string, error) {
	return WP("plugin", "list", "--status=active", "--format=table")
}

// InstallWPfakerFromZip copies the latest zip into the container and installs it.
func InstallWPfakerFromZip(wpfakerDir string) (string, error) {
	// Find latest zip
	cmd := exec.Command("bash", "-c",
		fmt.Sprintf("ls -t %s/dist/wpfaker-*.zip 2>/dev/null | head -1", wpfakerDir))
	out, err := cmd.Output()
	zipPath := strings.TrimSpace(string(out))
	if err != nil || zipPath == "" {
		return "", fmt.Errorf("no zip found in %s/dist/ — run 'npm run build' in wpfaker first", wpfakerDir)
	}

	// Copy into container
	copyCmd := exec.Command("docker", "cp", zipPath, config.ContainerWP+":/tmp/wpfaker.zip")
	if err := copyCmd.Run(); err != nil {
		return "", fmt.Errorf("docker cp failed: %w", err)
	}

	// Install and activate
	return WP("plugin", "install", "/tmp/wpfaker.zip", "--activate", "--force")
}
```

**Step 2: Build to verify**

Run: `go build ./internal/docker/`
Expected: No errors

**Step 3: Commit**

```bash
git add internal/docker/wp.go
git commit -m "feat(wpt): add WP-CLI wrapper and provision operations"
```

---

### Task 5: Docker package — provision orchestrator

**Files:**
- Create: `internal/docker/provision.go`

This is the main provisioning logic that calls `Blueprint/provision.sh` and handles WPfaker mode.

**Step 1: Create `internal/docker/provision.go`**

```go
package docker

import (
	"fmt"
	"os"
	"os/exec"

	"github.com/emmgeede/wp-test/internal/config"
)

// ProvisionStep represents one step in the provisioning process.
type ProvisionStep struct {
	Name string
	Fn   func() error
}

// ProvisionSteps returns the ordered list of provisioning steps.
func ProvisionSteps(paths *config.Paths, mode WPfakerMode) []ProvisionStep {
	compose := NewCompose(paths, mode)
	steps := []ProvisionStep{
		{
			Name: "Copying Blueprint files",
			Fn:   compose.CopyBlueprint,
		},
		{
			Name: "Starting containers",
			Fn: func() error {
				_, err := compose.Up()
				return err
			},
		},
		{
			Name: "Waiting for WordPress",
			Fn: func() error {
				return WaitForWP(120 * time.Second)
			},
		},
		{
			Name: "Fixing upload permissions",
			Fn:   FixUploadsPermissions,
		},
		{
			Name: "Running provision script",
			Fn: func() error {
				cmd := exec.Command("bash", filepath.Join(paths.Blueprint, "provision.sh"))
				cmd.Env = append(os.Environ(), fmt.Sprintf("WPFAKER=%s", mode))
				out, err := cmd.CombinedOutput()
				if err != nil {
					return fmt.Errorf("%s: %w", string(out), err)
				}
				return nil
			},
		},
		{
			Name: "Creating golden snapshot",
			Fn: func() error {
				os.MkdirAll(paths.Snapshots, 0o755)
				return Snapshot(paths.Golden)
			},
		},
	}
	return steps
}
```

Add missing imports (`"time"`, `"path/filepath"`).

**Step 2: Build to verify**

Run: `go build ./internal/docker/`
Expected: No errors

**Step 3: Commit**

```bash
git add internal/docker/provision.go
git commit -m "feat(wpt): add provision orchestrator with step definitions"
```

---

### Task 6: TUI — spinner model

**Files:**
- Create: `internal/tui/spinner.go`

**Step 1: Install Bubbletea dependencies**

```bash
go get github.com/charmbracelet/bubbletea@latest
go get github.com/charmbracelet/bubbles@latest
go get github.com/charmbracelet/lipgloss@latest
```

**Step 2: Create `internal/tui/spinner.go`**

This model runs a list of steps sequentially, showing a spinner for the current step and checkmarks for completed steps.

```go
package tui

import (
	"fmt"

	"github.com/charmbracelet/bubbles/spinner"
	tea "github.com/charmbracelet/bubbletea"
	"github.com/charmbracelet/lipgloss"
)

var (
	styleSuccess = lipgloss.NewStyle().Foreground(lipgloss.Color("#44CEFF"))
	styleError   = lipgloss.NewStyle().Foreground(lipgloss.Color("#FF4444"))
	styleDim     = lipgloss.NewStyle().Faint(true)
)

// Step is a named operation to execute.
type Step struct {
	Name string
	Fn   func() error
}

// stepDoneMsg signals a step completed (with optional error).
type stepDoneMsg struct {
	err error
}

// SpinnerModel runs steps sequentially with a spinner.
type SpinnerModel struct {
	steps   []Step
	current int
	done    bool
	err     error
	spinner spinner.Model
}

func NewSpinnerModel(steps []Step) SpinnerModel {
	s := spinner.New()
	s.Spinner = spinner.Dot
	s.Style = lipgloss.NewStyle().Foreground(lipgloss.Color("#44CEFF"))
	return SpinnerModel{
		steps:   steps,
		spinner: s,
	}
}

func (m SpinnerModel) Init() tea.Cmd {
	return tea.Batch(m.spinner.Tick, m.runCurrentStep())
}

func (m SpinnerModel) Update(msg tea.Msg) (tea.Model, tea.Cmd) {
	switch msg := msg.(type) {
	case tea.KeyMsg:
		if msg.String() == "ctrl+c" {
			return m, tea.Quit
		}
	case spinner.TickMsg:
		var cmd tea.Cmd
		m.spinner, cmd = m.spinner.Update(msg)
		return m, cmd
	case stepDoneMsg:
		if msg.err != nil {
			m.err = msg.err
			m.done = true
			return m, tea.Quit
		}
		m.current++
		if m.current >= len(m.steps) {
			m.done = true
			return m, tea.Quit
		}
		return m, m.runCurrentStep()
	}
	return m, nil
}

func (m SpinnerModel) View() string {
	var s string
	for i, step := range m.steps {
		if i < m.current {
			s += styleSuccess.Render("  ✓ "+step.Name) + "\n"
		} else if i == m.current && !m.done {
			s += fmt.Sprintf("  %s %s\n", m.spinner.View(), step.Name)
		} else if i == m.current && m.err != nil {
			s += styleError.Render("  ✗ "+step.Name) + "\n"
			s += styleError.Render("    "+m.err.Error()) + "\n"
		} else {
			s += styleDim.Render("  · "+step.Name) + "\n"
		}
	}
	if m.done && m.err == nil {
		s += "\n" + styleSuccess.Render("  Done!") + "\n"
	}
	return s
}

func (m SpinnerModel) runCurrentStep() tea.Cmd {
	step := m.steps[m.current]
	return func() tea.Msg {
		return stepDoneMsg{err: step.Fn()}
	}
}

// Err returns the error if the spinner failed.
func (m SpinnerModel) Err() error {
	return m.err
}
```

**Step 3: Build to verify**

Run: `go build ./internal/tui/`
Expected: No errors

**Step 4: Commit**

```bash
git add internal/tui/
git commit -m "feat(wpt): add Bubbletea spinner model for step execution"
```

---

### Task 7: TUI — main menu model

**Files:**
- Create: `internal/tui/menu.go`

**Step 1: Create `internal/tui/menu.go`**

```go
package tui

import (
	"fmt"

	tea "github.com/charmbracelet/bubbletea"
	"github.com/charmbracelet/lipgloss"
)

var (
	styleTitle    = lipgloss.NewStyle().Bold(true).Foreground(lipgloss.Color("#44CEFF")).MarginBottom(1)
	styleCursor   = lipgloss.NewStyle().Foreground(lipgloss.Color("#F200FF"))
	styleSelected = lipgloss.NewStyle().Foreground(lipgloss.Color("#44CEFF"))
)

// MenuItem represents a menu option.
type MenuItem struct {
	Label string
	Key   string // internal identifier
}

// MenuModel is an interactive list menu.
type MenuModel struct {
	title    string
	items    []MenuItem
	cursor   int
	chosen   string
	quitting bool
}

func NewMenuModel(title string, items []MenuItem) MenuModel {
	return MenuModel{title: title, items: items}
}

func (m MenuModel) Init() tea.Cmd {
	return nil
}

func (m MenuModel) Update(msg tea.Msg) (tea.Model, tea.Cmd) {
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
		case "enter":
			m.chosen = m.items[m.cursor].Key
			return m, tea.Quit
		}
	}
	return m, nil
}

func (m MenuModel) View() string {
	s := styleTitle.Render(m.title) + "\n"
	for i, item := range m.items {
		cursor := "  "
		label := item.Label
		if i == m.cursor {
			cursor = styleCursor.Render("▸ ")
			label = styleSelected.Render(label)
		}
		s += fmt.Sprintf("%s%s\n", cursor, label)
	}
	s += styleDim.Render("\n  ↑/↓ navigate · enter select · q quit") + "\n"
	return s
}

// Chosen returns the selected item key, or "" if quit.
func (m MenuModel) Chosen() string {
	if m.quitting {
		return ""
	}
	return m.chosen
}
```

**Step 2: Build to verify**

Run: `go build ./internal/tui/`
Expected: No errors

**Step 3: Commit**

```bash
git add internal/tui/menu.go
git commit -m "feat(wpt): add Bubbletea menu model for interactive selection"
```

---

### Task 8: Cobra subcommands — all 8 commands

**Files:**
- Modify: `cmd/wpt/main.go`

Replace the entire `cmd/wpt/main.go` with all Cobra subcommands wired up to the docker and tui packages.

**Step 1: Rewrite `cmd/wpt/main.go`**

```go
package main

import (
	"fmt"
	"os"

	tea "github.com/charmbracelet/bubbletea"
	"github.com/spf13/cobra"

	"github.com/emmgeede/wp-test/internal/config"
	"github.com/emmgeede/wp-test/internal/docker"
	"github.com/emmgeede/wp-test/internal/tui"
)

var wpfakerFlag string

func main() {
	if err := rootCmd.Execute(); err != nil {
		os.Exit(1)
	}
}

var rootCmd = &cobra.Command{
	Use:   "wpt",
	Short: "WP Test Environment Manager",
	RunE: func(cmd *cobra.Command, args []string) error {
		return runInteractive()
	},
}

func init() {
	provisionCmd.Flags().StringVar(&wpfakerFlag, "wpfaker", "none", "WPfaker mode: none, local, zip")
	upCmd.Flags().StringVar(&wpfakerFlag, "wpfaker", "none", "WPfaker mode: none, local")

	rootCmd.AddCommand(provisionCmd, upCmd, downCmd, resetCmd, snapshotCmd, destroyCmd, statusCmd, logsCmd)
}

// --- provision ---
var provisionCmd = &cobra.Command{
	Use:   "provision",
	Short: "Full setup: containers + plugins + schemas + snapshot",
	RunE: func(cmd *cobra.Command, args []string) error {
		paths, err := config.NewPaths()
		if err != nil {
			return err
		}
		mode := docker.WPfakerMode(wpfakerFlag)
		steps := docker.ProvisionSteps(paths, mode)
		tuiSteps := make([]tui.Step, len(steps))
		for i, s := range steps {
			tuiSteps[i] = tui.Step{Name: s.Name, Fn: s.Fn}
		}
		m := tui.NewSpinnerModel(tuiSteps)
		p := tea.NewProgram(m)
		result, err := p.Run()
		if err != nil {
			return err
		}
		if sm, ok := result.(tui.SpinnerModel); ok && sm.Err() != nil {
			return sm.Err()
		}
		fmt.Printf("\n  WordPress ready at %s\n", config.SiteURL)
		return nil
	},
}

// --- up ---
var upCmd = &cobra.Command{
	Use:   "up",
	Short: "Start containers",
	RunE: func(cmd *cobra.Command, args []string) error {
		paths, err := config.NewPaths()
		if err != nil {
			return err
		}
		mode := docker.WPfakerMode(wpfakerFlag)
		compose := docker.NewCompose(paths, mode)

		steps := []tui.Step{
			{Name: "Copying Blueprint files", Fn: compose.CopyBlueprint},
			{Name: "Starting containers", Fn: func() error { _, err := compose.Up(); return err }},
			{Name: "Waiting for WordPress", Fn: func() error { return docker.WaitForWP(120 * time.Second) }},
		}
		m := tui.NewSpinnerModel(steps)
		p := tea.NewProgram(m)
		result, err := p.Run()
		if err != nil {
			return err
		}
		if sm, ok := result.(tui.SpinnerModel); ok && sm.Err() != nil {
			return sm.Err()
		}
		fmt.Printf("\n  WordPress ready at %s\n", config.SiteURL)
		return nil
	},
}

// --- down ---
var downCmd = &cobra.Command{
	Use:   "down",
	Short: "Stop containers (keep volumes)",
	RunE: func(cmd *cobra.Command, args []string) error {
		paths, err := config.NewPaths()
		if err != nil {
			return err
		}
		compose := docker.NewCompose(paths, docker.WPfakerNone)
		_, err = compose.Down()
		if err != nil {
			return err
		}
		fmt.Println("  Containers stopped.")
		return nil
	},
}

// --- reset ---
var resetCmd = &cobra.Command{
	Use:   "reset",
	Short: "Restore DB from golden snapshot",
	RunE: func(cmd *cobra.Command, args []string) error {
		paths, err := config.NewPaths()
		if err != nil {
			return err
		}
		if _, err := os.Stat(paths.Golden); os.IsNotExist(err) {
			return fmt.Errorf("no snapshot found — run 'wpt provision' first")
		}
		steps := []tui.Step{
			{Name: "Restoring database", Fn: func() error { return docker.Reset(paths.Golden) }},
		}
		m := tui.NewSpinnerModel(steps)
		p := tea.NewProgram(m)
		result, err := p.Run()
		if err != nil {
			return err
		}
		if sm, ok := result.(tui.SpinnerModel); ok && sm.Err() != nil {
			return sm.Err()
		}
		return nil
	},
}

// --- snapshot ---
var snapshotCmd = &cobra.Command{
	Use:   "snapshot",
	Short: "Save current DB as golden snapshot",
	RunE: func(cmd *cobra.Command, args []string) error {
		paths, err := config.NewPaths()
		if err != nil {
			return err
		}
		os.MkdirAll(paths.Snapshots, 0o755)
		steps := []tui.Step{
			{Name: "Exporting database", Fn: func() error { return docker.Snapshot(paths.Golden) }},
		}
		m := tui.NewSpinnerModel(steps)
		p := tea.NewProgram(m)
		result, err := p.Run()
		if err != nil {
			return err
		}
		if sm, ok := result.(tui.SpinnerModel); ok && sm.Err() != nil {
			return sm.Err()
		}
		return nil
	},
}

// --- destroy ---
var destroyCmd = &cobra.Command{
	Use:   "destroy",
	Short: "Remove containers and volumes",
	RunE: func(cmd *cobra.Command, args []string) error {
		paths, err := config.NewPaths()
		if err != nil {
			return err
		}
		compose := docker.NewCompose(paths, docker.WPfakerNone)
		_, err = compose.Destroy()
		if err != nil {
			return err
		}
		fmt.Println("  All containers and volumes destroyed.")
		return nil
	},
}

// --- status ---
var statusCmd = &cobra.Command{
	Use:   "status",
	Short: "Show container status and active plugins",
	RunE: func(cmd *cobra.Command, args []string) error {
		paths, err := config.NewPaths()
		if err != nil {
			return err
		}
		compose := docker.NewCompose(paths, docker.WPfakerNone)
		ps, err := compose.Ps()
		if err != nil {
			return err
		}
		fmt.Println(ps)
		fmt.Println()
		plugins, err := docker.PluginList()
		if err != nil {
			fmt.Println("  (WordPress not running)")
		} else {
			fmt.Println(plugins)
		}
		return nil
	},
}

// --- logs ---
var logsCmd = &cobra.Command{
	Use:   "logs",
	Short: "Tail container logs",
	RunE: func(cmd *cobra.Command, args []string) error {
		paths, err := config.NewPaths()
		if err != nil {
			return err
		}
		compose := docker.NewCompose(paths, docker.WPfakerNone)
		return compose.Logs()
	},
}
```

Add missing `"time"` import.

**Step 2: Build to verify**

Run: `go build -o wpt ./cmd/wpt && ./wpt --help`
Expected: Shows all 8 subcommands in help output

**Step 3: Commit**

```bash
git add cmd/wpt/main.go
git commit -m "feat(wpt): wire all 8 subcommands to docker and tui packages"
```

---

### Task 9: TUI — interactive mode (root command)

**Files:**
- Modify: `cmd/wpt/main.go` (add `runInteractive` function)

**Step 1: Add `runInteractive()` to `cmd/wpt/main.go`**

Add this function (referenced by `rootCmd.RunE`):

```go
func runInteractive() error {
	// Main menu
	mainItems := []tui.MenuItem{
		{Label: "Provision (full setup)", Key: "provision"},
		{Label: "Up (start containers)", Key: "up"},
		{Label: "Reset (restore snapshot)", Key: "reset"},
		{Label: "Snapshot (save DB)", Key: "snapshot"},
		{Label: "Status", Key: "status"},
		{Label: "Down (stop)", Key: "down"},
		{Label: "Destroy (remove all)", Key: "destroy"},
		{Label: "Logs", Key: "logs"},
	}

	menu := tui.NewMenuModel("WP Test Environment", mainItems)
	p := tea.NewProgram(menu)
	result, err := p.Run()
	if err != nil {
		return err
	}
	chosen := result.(tui.MenuModel).Chosen()
	if chosen == "" {
		return nil
	}

	// WPfaker mode selection for provision/up
	if chosen == "provision" || chosen == "up" {
		wpfakerItems := []tui.MenuItem{
			{Label: "None (test plugins only)", Key: "none"},
			{Label: "Local (mount ~/Projects/wpfaker)", Key: "local"},
			{Label: "Zip (install from dist/)", Key: "zip"},
		}
		wpMenu := tui.NewMenuModel("WPfaker Mode", wpfakerItems)
		p2 := tea.NewProgram(wpMenu)
		result2, err := p2.Run()
		if err != nil {
			return err
		}
		wpChosen := result2.(tui.MenuModel).Chosen()
		if wpChosen == "" {
			return nil
		}
		wpfakerFlag = wpChosen
	}

	// Dispatch to the correct subcommand
	switch chosen {
	case "provision":
		return provisionCmd.RunE(provisionCmd, nil)
	case "up":
		return upCmd.RunE(upCmd, nil)
	case "down":
		return downCmd.RunE(downCmd, nil)
	case "reset":
		return resetCmd.RunE(resetCmd, nil)
	case "snapshot":
		return snapshotCmd.RunE(snapshotCmd, nil)
	case "destroy":
		return destroyCmd.RunE(destroyCmd, nil)
	case "status":
		return statusCmd.RunE(statusCmd, nil)
	case "logs":
		return logsCmd.RunE(logsCmd, nil)
	}
	return nil
}
```

**Step 2: Build and test interactively**

Run: `go build -o wpt ./cmd/wpt && ./wpt`
Expected: Interactive menu appears with arrow-key navigation

**Step 3: Commit**

```bash
git add cmd/wpt/main.go
git commit -m "feat(wpt): add interactive TUI menu for root command"
```

---

### Task 10: End-to-end test

**Files:** none (manual verification)

**Step 1: Build the binary**

```bash
cd ~/Projects/wp-test
go build -o wpt ./cmd/wpt
```

**Step 2: Test interactive mode**

```bash
./wpt
```
Expected: Menu renders, can navigate, can select Provision → WPfaker mode selection

**Step 3: Test direct provisioning**

```bash
./wpt destroy
./wpt provision --wpfaker=local
```
Expected: Spinner shows each step, completes with success summary

**Step 4: Test reset**

```bash
./wpt reset
```
Expected: Spinner shows "Restoring database", completes

**Step 5: Test status**

```bash
./wpt status
```
Expected: Shows container status and active plugins

**Step 6: Commit final state**

```bash
git add -A
git commit -m "feat(wpt): complete Go CLI tool with Bubbletea TUI"
git push
```

---

**Summary:** 10 tasks, building from foundation (module, config, docker wrappers) through TUI components (spinner, menu) to wiring (commands, interactive mode) and final verification.
