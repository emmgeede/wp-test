package config

import (
	"fmt"
	"os"
	"path/filepath"
)

// ProjectRoot returns the wp-test project root.
// It first walks up from cwd looking for Blueprint/.
// If not found, falls back to ~/Projects/wp-test.
func ProjectRoot() (string, error) {
	// Try walking up from cwd
	dir, err := os.Getwd()
	if err == nil {
		d := dir
		for {
			if _, err := os.Stat(filepath.Join(d, "Blueprint")); err == nil {
				return d, nil
			}
			parent := filepath.Dir(d)
			if parent == d {
				break
			}
			d = parent
		}
	}

	// Fallback: ~/Projects/wp-test
	home, err := os.UserHomeDir()
	if err != nil {
		return "", err
	}
	fallback := filepath.Join(home, "Projects", "wp-test")
	if _, err := os.Stat(filepath.Join(fallback, "Blueprint")); err == nil {
		return fallback, nil
	}
	return "", fmt.Errorf("could not find wp-test project root (no Blueprint/ directory found)")
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
	SiteURL        = "http://wpfaker.dv"
)
