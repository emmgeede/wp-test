package config

import (
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
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

// Worktree represents a git worktree entry.
type Worktree struct {
	Path   string // absolute path
	Branch string // branch name (e.g. "master", "feature/foo")
}

// DetectWorktrees returns all git worktrees for the WPfaker repo.
// The main worktree is always first. Returns nil if git fails.
func (p *Paths) DetectWorktrees() []Worktree {
	cmd := exec.Command("git", "-C", p.WPfaker, "worktree", "list", "--porcelain")
	out, err := cmd.Output()
	if err != nil {
		return nil
	}

	var worktrees []Worktree
	var current Worktree

	for _, line := range strings.Split(strings.TrimSpace(string(out)), "\n") {
		if strings.HasPrefix(line, "worktree ") {
			if current.Path != "" {
				worktrees = append(worktrees, current)
			}
			current = Worktree{Path: strings.TrimPrefix(line, "worktree ")}
		} else if strings.HasPrefix(line, "branch refs/heads/") {
			current.Branch = strings.TrimPrefix(line, "branch refs/heads/")
		}
	}
	if current.Path != "" {
		worktrees = append(worktrees, current)
	}

	return worktrees
}
