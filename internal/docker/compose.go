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
	paths *config.Paths
	mode  WPfakerMode
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
