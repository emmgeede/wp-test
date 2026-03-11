package tui

import (
	"fmt"

	"github.com/charmbracelet/bubbles/spinner"
	tea "github.com/charmbracelet/bubbletea"
	"github.com/charmbracelet/lipgloss"
)

var (
	styleSuccess = lipgloss.NewStyle().Foreground(lipgloss.Color("#44CEFF"))
	styleError   = lipgloss.NewStyle().Foreground(lipgloss.Color("#FF4444"))
	styleDim     = lipgloss.NewStyle().Faint(true)
)

// Step is a named operation to execute.
type Step struct {
	Name string
	Fn   func() error
}

// stepDoneMsg signals a step completed (with optional error).
type stepDoneMsg struct {
	err error
}

// SpinnerModel runs steps sequentially with a spinner.
type SpinnerModel struct {
	steps   []Step
	current int
	done    bool
	err     error
	spinner spinner.Model
}

func NewSpinnerModel(steps []Step) SpinnerModel {
	s := spinner.New()
	s.Spinner = spinner.Dot
	s.Style = lipgloss.NewStyle().Foreground(lipgloss.Color("#44CEFF"))
	return SpinnerModel{
		steps:   steps,
		spinner: s,
	}
}

func (m SpinnerModel) Init() tea.Cmd {
	return tea.Batch(m.spinner.Tick, m.runCurrentStep())
}

func (m SpinnerModel) Update(msg tea.Msg) (tea.Model, tea.Cmd) {
	switch msg := msg.(type) {
	case tea.KeyMsg:
		if msg.String() == "ctrl+c" {
			return m, tea.Quit
		}
	case spinner.TickMsg:
		var cmd tea.Cmd
		m.spinner, cmd = m.spinner.Update(msg)
		return m, cmd
	case stepDoneMsg:
		if msg.err != nil {
			m.err = msg.err
			m.done = true
			return m, tea.Quit
		}
		m.current++
		if m.current >= len(m.steps) {
			m.done = true
			return m, tea.Quit
		}
		return m, m.runCurrentStep()
	}
	return m, nil
}

func (m SpinnerModel) View() string {
	var s string
	for i, step := range m.steps {
		if i < m.current {
			s += styleSuccess.Render("  \u2713 "+step.Name) + "\n"
		} else if i == m.current && !m.done {
			s += fmt.Sprintf("  %s %s\n", m.spinner.View(), step.Name)
		} else if i == m.current && m.err != nil {
			s += styleError.Render("  \u2717 "+step.Name) + "\n"
			s += styleError.Render("    "+m.err.Error()) + "\n"
		} else {
			s += styleDim.Render("  \u00b7 "+step.Name) + "\n"
		}
	}
	if m.done && m.err == nil {
		s += "\n" + styleSuccess.Render("  Done!") + "\n"
	}
	return s
}

func (m SpinnerModel) runCurrentStep() tea.Cmd {
	step := m.steps[m.current]
	return func() tea.Msg {
		return stepDoneMsg{err: step.Fn()}
	}
}

// Err returns the error if the spinner failed.
func (m SpinnerModel) Err() error {
	return m.err
}
