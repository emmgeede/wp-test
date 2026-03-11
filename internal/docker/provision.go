package docker

import (
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"time"

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
