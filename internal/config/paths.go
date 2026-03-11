package config

import (
	"fmt"
	"os"
	"path/filepath"
)

// ProjectRoot returns the wp-test project root.
// It walks up from cwd looking for Blueprint/.
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
