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

var wpfakerFlag string
var wpfakerDirFlag string
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
	provisionCmd.Flags().StringVar(&wpfakerFlag, "wpfaker", "none", "WPfaker mode: none, local, zip")
	provisionCmd.Flags().StringVar(&wpfakerDirFlag, "wpfaker-dir", "", "WPfaker source directory (worktree path)")
	provisionCmd.Flags().StringVar(&pluginsFlag, "plugins", "", "Comma-separated plugins to activate")
	upCmd.Flags().StringVar(&wpfakerFlag, "wpfaker", "none", "WPfaker mode: none, local")
	upCmd.Flags().StringVar(&wpfakerDirFlag, "wpfaker-dir", "", "WPfaker source directory (worktree path)")

	rootCmd.AddCommand(provisionCmd, upCmd, downCmd, resetCmd, snapshotCmd, destroyCmd, statusCmd, logsCmd)
}

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

		// Worktree/branch selection for local mode
		if wpfakerFlag == "local" {
			paths, err := config.NewPaths()
			if err != nil {
				return err
			}
			worktrees := paths.DetectWorktrees()
			if len(worktrees) > 1 {
				var wtItems []tui.MenuItem
				for _, wt := range worktrees {
					label := wt.Branch
					if wt.Path == paths.WPfaker {
						label += "  (main repo)"
					} else {
						label += fmt.Sprintf("  (%s)", wt.Path)
					}
					wtItems = append(wtItems, tui.MenuItem{Label: label, Key: wt.Path})
				}
				wtMenu := tui.NewMenuModel("Select WPfaker branch", wtItems)
				p3 := tea.NewProgram(wtMenu)
				result3, err := p3.Run()
				if err != nil {
					return err
				}
				wtChosen := result3.(tui.MenuModel).Chosen()
				if wtChosen == "" {
					return nil
				}
				wpfakerDirFlag = wtChosen
			}
		}
	}

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
		steps := docker.ProvisionSteps(paths, mode, pluginsFlag, wpfakerDirFlag)
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
		mode := docker.WPfakerMode(wpfakerFlag)
		compose := docker.NewCompose(paths, mode)
		if wpfakerDirFlag != "" {
			compose.SetWPfakerDir(wpfakerDirFlag)
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
