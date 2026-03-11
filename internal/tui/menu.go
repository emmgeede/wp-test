package tui

import (
	"fmt"

	tea "github.com/charmbracelet/bubbletea"
	"github.com/charmbracelet/lipgloss"
)

var (
	styleTitle    = lipgloss.NewStyle().Bold(true).Foreground(lipgloss.Color("#44CEFF")).MarginBottom(1)
	styleCursor   = lipgloss.NewStyle().Foreground(lipgloss.Color("#F200FF"))
	styleSelected = lipgloss.NewStyle().Foreground(lipgloss.Color("#44CEFF"))
)

// MenuItem represents a menu option.
type MenuItem struct {
	Label string
	Key   string // internal identifier
}

// MenuModel is an interactive list menu.
type MenuModel struct {
	title    string
	items    []MenuItem
	cursor   int
	chosen   string
	quitting bool
}

func NewMenuModel(title string, items []MenuItem) MenuModel {
	return MenuModel{title: title, items: items}
}

func (m MenuModel) Init() tea.Cmd {
	return nil
}

func (m MenuModel) Update(msg tea.Msg) (tea.Model, tea.Cmd) {
	switch msg := msg.(type) {
	case tea.KeyMsg:
		switch msg.String() {
		case "ctrl+c", "q":
			m.quitting = true
			return m, tea.Quit
		case "up", "k":
			if m.cursor > 0 {
				m.cursor--
			}
		case "down", "j":
			if m.cursor < len(m.items)-1 {
				m.cursor++
			}
		case "enter":
			m.chosen = m.items[m.cursor].Key
			return m, tea.Quit
		}
	}
	return m, nil
}

func (m MenuModel) View() string {
	s := styleTitle.Render(m.title) + "\n"
	for i, item := range m.items {
		cursor := "  "
		label := item.Label
		if i == m.cursor {
			cursor = styleCursor.Render("▸ ")
			label = styleSelected.Render(label)
		}
		s += fmt.Sprintf("%s%s\n", cursor, label)
	}
	s += styleDim.Render("\n  ↑/↓ navigate · enter select · q quit") + "\n"
	return s
}

// Chosen returns the selected item key, or "" if quit.
func (m MenuModel) Chosen() string {
	if m.quitting {
		return ""
	}
	return m.chosen
}
