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
		// TUI menu will go here in Task 9
		fmt.Println("WPT — run 'wpt --help' for available commands")
	},
}

func main() {
	if err := rootCmd.Execute(); err != nil {
		os.Exit(1)
	}
}
