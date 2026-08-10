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

// UpdateWP runs wp core update + wp core update-db if an update is available.
func UpdateWP() (string, error) {
	out, err := WP("core", "update")
	if err != nil {
		// "WordPress is up to date" is not an error
		if strings.Contains(out, "up to date") || strings.Contains(out, "already at") {
			return out, nil
		}
		return out, err
	}
	// Run database update after core update
	dbOut, _ := WP("core", "update-db")
	return out + "\n" + dbOut, nil
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
	return installPluginZip(wpfakerDir, "wpfaker-*.zip")
}

// InstallWPfakerFreeFromZip copies the latest free zip into the container and installs it.
func InstallWPfakerFreeFromZip(wpfakerFreeDir string) (string, error) {
	return installPluginZip(wpfakerFreeDir, "faker-studio-lite-*.zip")
}

func installPluginZip(dir, pattern string) (string, error) {
	cmd := exec.Command("bash", "-c",
		fmt.Sprintf("ls -t %s/dist/%s 2>/dev/null | head -1", dir, pattern))
	out, err := cmd.Output()
	zipPath := strings.TrimSpace(string(out))
	if err != nil || zipPath == "" {
		return "", fmt.Errorf("no zip found in %s/dist/%s — run 'npm run build' first", dir, pattern)
	}

	copyCmd := exec.Command("docker", "cp", zipPath, config.ContainerWP+":/tmp/plugin.zip")
	if err := copyCmd.Run(); err != nil {
		return "", fmt.Errorf("docker cp failed: %w", err)
	}

	return WP("plugin", "install", "/tmp/plugin.zip", "--activate", "--force")
}
