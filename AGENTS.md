# Project Instructions — SEAIT Enrollment Management System

## Mandatory Workflow Rule

**Every time a task is given to any agent (fixer, designer, explorer, librarian, oracle, etc.), the orchestrator MUST:**

1. **Commit all current changes** before dispatching the agent
2. **Push to remote** after the agent completes and gates pass
3. **Verify working tree is clean** before starting the next task

This ensures:
- No work is lost if an agent goes rogue (as happened with fix-13)
- Every change is traceable in git history
- Remote backup exists at every checkpoint
- Clean state for the next task

## Gate Requirements Before Push

All 5 gates must pass:
- **Pest** (backend tests): 43/43 passing
- **PHPStan** (static analysis): 0 errors
- **Pint** (code style): passed
- **ESLint** (frontend lint): 0 errors, 0 warnings
- **Vite build**: succeeds

## Agent Dispatch Discipline

- Use `background: true` for independent lanes
- Scope agents to specific files/folders (no overlapping write scopes)
- Reconcile results before advancing dependent work
- Never poll background tasks — wait for notification

## Stage Completion Checklist

Before marking a stage complete:
- [ ] All gates pass
- [ ] Working tree clean (committed)
- [ ] Pushed to remote
- [ ] Audit report documented

---

*This file is loaded via `opencode.json` → `instructions: ["AGENTS.md"]`*