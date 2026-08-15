package main

import (
	"fmt"
	"os"
	"os/exec"
	"strings"
	"time"

	tea "github.com/charmbracelet/bubbletea"
	"github.com/spf13/cobra"

	"github.com/emmgeede/wp-test/internal/config"
	"github.com/emmgeede/wp-test/internal/docker"
	"github.com/emmgeede/wp-test/internal/tui"
)

var fakerStudioFlag string
var fakerStudioDirFlag string
var pluginsFlag string

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
	provisionCmd.Flags().StringVar(&fakerStudioFlag, "faker-studio", "none", "FakerStudio mode: none, local, zip, free-local, free-zip")
	provisionCmd.Flags().StringVar(&fakerStudioDirFlag, "faker-studio-dir", "", "FakerStudio source directory (worktree path)")
	provisionCmd.Flags().StringVar(&pluginsFlag, "plugins", "", "Comma-separated plugins to activate")
	upCmd.Flags().StringVar(&fakerStudioFlag, "faker-studio", "none", "FakerStudio mode: none, local, zip, free-local, free-zip")
	upCmd.Flags().StringVar(&fakerStudioDirFlag, "faker-studio-dir", "", "FakerStudio source directory (worktree path)")

	rootCmd.AddCommand(provisionCmd, upCmd, downCmd, resetCmd, snapshotCmd, destroyCmd, statusCmd, logsCmd)
}

// selectFakerStudioMode runs the interactive FakerStudio edition + mode + worktree selection.
// Sets fakerStudioFlag and fakerStudioDirFlag. Returns false if user cancelled.
func selectFakerStudioMode() (bool, error) {
	// Step 1: Edition selection
	editionItems := []tui.MenuItem{
		{Label: "Premium (~/Projects/fakerStudio)", Key: "premium"},
		{Label: "Free (~/Projects/fakerStudio-free)", Key: "free"},
		{Label: "None (test plugins only)", Key: "none"},
	}
	edMenu := tui.NewMenuModel("FakerStudio Edition", editionItems)
	p := tea.NewProgram(edMenu)
	result, err := p.Run()
	if err != nil {
		return false, err
	}
	edition := result.(tui.MenuModel).Chosen()
	if edition == "" {
		return false, nil
	}
	if edition == "none" {
		fakerStudioFlag = "none"
		return true, nil
	}

	// Step 2: Install mode
	modeItems := []tui.MenuItem{
		{Label: "Local (mount source directory)", Key: "local"},
		{Label: "Zip (install from dist/)", Key: "zip"},
	}
	modeMenu := tui.NewMenuModel("Install Mode", modeItems)
	p2 := tea.NewProgram(modeMenu)
	result2, err := p2.Run()
	if err != nil {
		return false, err
	}
	modeChosen := result2.(tui.MenuModel).Chosen()
	if modeChosen == "" {
		return false, nil
	}

	// Combine edition + mode into FakerStudioMode
	if edition == "premium" {
		fakerStudioFlag = modeChosen // "local" or "zip"
	} else {
		fakerStudioFlag = "free-" + modeChosen // "free-local" or "free-zip"
	}

	// Step 3: Worktree selection (only for local mode)
	if modeChosen == "local" {
		paths, err := config.NewPaths()
		if err != nil {
			return false, err
		}
		repoPath := paths.FakerStudio
		if edition == "free" {
			repoPath = paths.FakerStudioFree
		}
		worktrees := paths.DetectWorktreesFor(repoPath)
		if len(worktrees) > 1 {
			var wtItems []tui.MenuItem
			for _, wt := range worktrees {
				label := wt.Branch
				if wt.Path == repoPath {
					label += "  (main repo)"
				} else {
					label += fmt.Sprintf("  (%s)", wt.Path)
				}
				wtItems = append(wtItems, tui.MenuItem{Label: label, Key: wt.Path})
			}
			wtMenu := tui.NewMenuModel("Select branch", wtItems)
			p3 := tea.NewProgram(wtMenu)
			result3, err := p3.Run()
			if err != nil {
				return false, err
			}
			wtChosen := result3.(tui.MenuModel).Chosen()
			if wtChosen == "" {
				return false, nil
			}
			fakerStudioDirFlag = wtChosen
		}
	}

	return true, nil
}

func runInteractive() error {
	// Main menu
	mainItems := []tui.MenuItem{
		{Label: "Destroy (remove all)", Key: "destroy"},
		{Label: "Provision (full setup)", Key: "provision"},
		{Label: "Up (start containers)", Key: "up"},
		{Label: "Reset (restore snapshot)", Key: "reset"},
		{Label: "Snapshot (save DB)", Key: "snapshot"},
		{Label: "Status", Key: "status"},
		{Label: "Down (stop)", Key: "down"},
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

	// FakerStudio mode selection for up only (provision uses startProvisionFlow)
	if chosen == "up" {
		ok, err := selectFakerStudioMode()
		if err != nil {
			return err
		}
		if !ok {
			return nil
		}
	}

	// Dispatch to the correct subcommand
	switch chosen {
	case "provision":
		return startProvisionFlow()
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

// startProvisionFlow runs the interactive provision prompts (FakerStudio edition,
// mode, worktree selection, plugin selection) and then executes the provision command.
func startProvisionFlow() error {
	// FakerStudio edition + mode + worktree selection
	ok, err := selectFakerStudioMode()
	if err != nil {
		return err
	}
	if !ok {
		return nil
	}

	// Plugin selection (Meta Box variant is part of the checklist)
	pluginItems := []tui.ChecklistItem{
		{Label: "ACF Pro", Key: "advanced-custom-fields-pro"},
		{Label: "ACPT", Key: "advanced-custom-post-type"},
		{Label: "CPT UI", Key: "custom-post-type-ui"},
		{Label: "JetEngine", Key: "jet-engine"},
		{Label: "Meta Box AIO (all-in-one)", Key: "meta-box-aio"},
		{Label: "Meta Box (individual plugins)", Key: "meta-box-standalone"},
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
	selected := clModel.Selected()

	// Expand Meta Box variant keys into actual plugin names
	var expanded []string
	for _, s := range selected {
		switch s {
		case "meta-box-aio":
			expanded = append(expanded, "meta-box", "meta-box-aio")
		case "meta-box-standalone":
			expanded = append(expanded, "meta-box", "mb-custom-post-type", "meta-box-builder", "mb-relationships")
		default:
			expanded = append(expanded, s)
		}
	}

	pluginsFlag = strings.Join(expanded, ",")

	return provisionCmd.RunE(provisionCmd, nil)
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
		mode := docker.FakerStudioMode(fakerStudioFlag)
		steps := docker.ProvisionSteps(paths, mode, pluginsFlag, fakerStudioDirFlag)
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
		exec.Command("xdg-open", config.SiteURL+"/wp-admin").Start()
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
		mode := docker.FakerStudioMode(fakerStudioFlag)
		compose := docker.NewCompose(paths, mode)
		if fakerStudioDirFlag != "" {
			compose.SetFakerStudioDir(fakerStudioDirFlag)
		}

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
		compose := docker.NewCompose(paths, docker.FakerStudioNone)
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
		compose := docker.NewCompose(paths, docker.FakerStudioNone)
		_, err = compose.Destroy()
		if err != nil {
			return err
		}
		fmt.Println("  All containers and volumes destroyed.")

		// Ask if user wants to provision immediately (default: yes)
		confirmItems := []tui.MenuItem{
			{Label: "Yes", Key: "yes"},
			{Label: "No", Key: "no"},
		}
		confirmMenu := tui.NewMenuModel("Provision new environment?", confirmItems)
		p := tea.NewProgram(confirmMenu)
		result, err := p.Run()
		if err != nil {
			return err
		}
		if result.(tui.MenuModel).Chosen() != "yes" {
			return nil
		}

		// Jump into the provision flow (FakerStudio edition → mode → worktree → plugins → provision)
		return startProvisionFlow()
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
		compose := docker.NewCompose(paths, docker.FakerStudioNone)
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
		compose := docker.NewCompose(paths, docker.FakerStudioNone)
		return compose.Logs()
	},
}
