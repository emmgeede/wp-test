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
	WPfakerNone      WPfakerMode = "none"
	WPfakerLocal     WPfakerMode = "local"
	WPfakerZip       WPfakerMode = "zip"
	WPfakerFreeLocal WPfakerMode = "free-local"
	WPfakerFreeZip   WPfakerMode = "free-zip"
)

// Compose runs docker compose commands in the Docker/ directory.
type Compose struct {
	paths      *config.Paths
	mode       WPfakerMode
	wpfakerDir string // absolute path to WPfaker source (for local mode)
	plugins    string // comma-separated list of selected plugins
}

func NewCompose(paths *config.Paths, mode WPfakerMode) *Compose {
	dir := paths.WPfaker
	if mode == WPfakerFreeLocal || mode == WPfakerFreeZip {
		dir = paths.WPfakerFree
	}
	return &Compose{paths: paths, mode: mode, wpfakerDir: dir}
}

// SetPlugins sets the selected plugins for filtering volume mounts.
func (c *Compose) SetPlugins(plugins string) {
	c.plugins = plugins
}

// SetWPfakerDir overrides the WPfaker source directory (for worktree support).
func (c *Compose) SetWPfakerDir(dir string) {
	c.wpfakerDir = dir
}

// composeArgs returns the -f flags for docker compose.
func (c *Compose) composeArgs() []string {
	args := []string{"-f", "docker-compose.yml"}
	switch c.mode {
	case WPfakerLocal:
		args = append(args, "-f", "docker-compose.wpfaker.yml")
	case WPfakerFreeLocal:
		args = append(args, "-f", "docker-compose.wpfaker-free.yml")
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

// pluginVolumeDirs maps plugin slugs to Testplugins/ directory names.
// Only plugins listed here are mounted as volumes.
var pluginVolumeDirs = map[string]string{
	"advanced-custom-fields-pro": "advanced-custom-fields-pro",
	"advanced-custom-post-type":  "advanced-custom-post-type",
	"custom-post-type-ui":        "custom-post-type-ui",
	"jet-engine":                 "jet-engine",
	"meta-box":                   "meta-box",
	"meta-box-aio":               "meta-box-aio",
	"meta-box-builder":           "meta-box-builder",
}

// CopyBlueprint copies Blueprint/ files to Docker/.
// Filters docker-compose.yml to only mount selected plugin volumes.
// For local mode, rewrites the WPfaker mount path in docker-compose.wpfaker.yml.
func (c *Compose) CopyBlueprint() error {
	if err := os.MkdirAll(c.paths.Docker, 0o755); err != nil {
		return err
	}
	files := []string{
		"docker-compose.yml",
		"docker-compose.wpfaker.yml",
		"docker-compose.wpfaker-free.yml",
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
		// Rewrite WPfaker mount path if using a non-default directory
		if f == "docker-compose.wpfaker.yml" && c.mode == WPfakerLocal && c.wpfakerDir != c.paths.WPfaker {
			data = []byte(strings.ReplaceAll(string(data), "../../wpfaker", c.wpfakerDir))
		}
		if f == "docker-compose.wpfaker-free.yml" && c.mode == WPfakerFreeLocal && c.wpfakerDir != c.paths.WPfakerFree {
			data = []byte(strings.ReplaceAll(string(data), c.paths.WPfakerFree, c.wpfakerDir))
		}
		// Filter plugin volume mounts based on selected plugins
		if f == "docker-compose.yml" && c.plugins != "" {
			data = []byte(c.filterPluginVolumes(string(data)))
		}
		if err := os.WriteFile(dst, data, 0o644); err != nil {
			return fmt.Errorf("write %s: %w", f, err)
		}
	}
	return nil
}

// filterPluginVolumes removes volume mount lines for plugins that are not selected.
func (c *Compose) filterPluginVolumes(content string) string {
	lines := strings.Split(content, "\n")
	var result []string
	for _, line := range lines {
		if c.isUnselectedPluginMount(line) {
			continue // skip this volume mount
		}
		result = append(result, line)
	}
	return strings.Join(result, "\n")
}

// isUnselectedPluginMount checks if a line is a volume mount for a plugin not in the selected list.
func (c *Compose) isUnselectedPluginMount(line string) bool {
	trimmed := strings.TrimSpace(line)
	if !strings.Contains(trimmed, "Testplugins/") {
		return false
	}
	for slug, dir := range pluginVolumeDirs {
		if strings.Contains(trimmed, "Testplugins/"+dir) {
			// This line mounts this plugin — check if it's selected
			if !strings.Contains(c.plugins, slug) {
				return true // not selected → remove
			}
			return false // selected → keep
		}
	}
	return false
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
