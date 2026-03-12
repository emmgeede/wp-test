package tui

import (
	"fmt"

	tea "github.com/charmbracelet/bubbletea"
)

// ChecklistItem represents a selectable item.
type ChecklistItem struct {
	Label   string
	Key     string
	Checked bool
}

// ChecklistModel is a multi-select checklist.
type ChecklistModel struct {
	title    string
	items    []ChecklistItem
	cursor   int
	quitting bool
	allIdx   int // index of the "All" toggle, -1 if none
}

// NewChecklistModel creates a checklist with an "All" toggle at position 0.
func NewChecklistModel(title string, items []ChecklistItem) ChecklistModel {
	all := ChecklistItem{Label: "All", Key: "_all"}
	withAll := append([]ChecklistItem{all}, items...)
	return ChecklistModel{
		title:  title,
		items:  withAll,
		allIdx: 0,
	}
}

func (m ChecklistModel) Init() tea.Cmd {
	return nil
}

func (m ChecklistModel) Update(msg tea.Msg) (tea.Model, tea.Cmd) {
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
		case " ":
			m.toggle(m.cursor)
		case "enter":
			return m, tea.Quit
		}
	}
	return m, nil
}

func (m *ChecklistModel) toggle(idx int) {
	if idx == m.allIdx {
		newState := !m.items[m.allIdx].Checked
		for i := range m.items {
			m.items[i].Checked = newState
		}
	} else {
		m.items[idx].Checked = !m.items[idx].Checked
		if m.allIdx >= 0 {
			allChecked := true
			for i, item := range m.items {
				if i == m.allIdx {
					continue
				}
				if !item.Checked {
					allChecked = false
					break
				}
			}
			m.items[m.allIdx].Checked = allChecked
		}
	}
}

func (m ChecklistModel) View() string {
	s := styleTitle.Render(m.title) + "\n"
	for i, item := range m.items {
		cursor := "  "
		if i == m.cursor {
			cursor = styleCursor.Render("▸ ")
		}

		check := "[ ]"
		if item.Checked {
			check = styleSelected.Render("[✓]")
		}

		label := item.Label
		if i == m.cursor {
			label = styleSelected.Render(label)
		}

		s += fmt.Sprintf("%s%s %s\n", cursor, check, label)

		if i == m.allIdx {
			s += styleDim.Render("  ─────────────────") + "\n"
		}
	}
	s += styleDim.Render("\n  Space: toggle · Enter: confirm · q: cancel") + "\n"
	return s
}

// Selected returns the keys of all checked items (excluding "_all").
func (m ChecklistModel) Selected() []string {
	if m.quitting {
		return nil
	}
	var sel []string
	for _, item := range m.items {
		if item.Checked && item.Key != "_all" {
			sel = append(sel, item.Key)
		}
	}
	return sel
}

// Cancelled returns true if the user quit without confirming.
func (m ChecklistModel) Cancelled() bool {
	return m.quitting
}
