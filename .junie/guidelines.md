# Technical Guidelines

## Working with `docs/tasks.md`

To maintain an accurate track of project progress, follow these instructions when working with the task list:

- **Marking Completion**: Mark tasks as completed by changing `[ ]` to `[x]`.
- **Task Integrity**: Keep the existing development phases intact. Do not remove phases even if all tasks within them are finished.
- **Adding New Tasks**: 
    - If a task needs to be broken down further or a new requirement is discovered, add new tasks under the appropriate phase.
    - Every new or modified task **MUST** be linked to a plan item in `docs/plan.md` and a requirement in `docs/requirements.md`.
    - Format links as: `Link: Plan P[X.Y] | Req [X.Y]`.
- **Consistency**: Maintain the existing formatting style (enumerated bold task titles, bulleted sub-tasks, and link lines).
- **Validation**: Ensure that before submitting any work, the corresponding tasks in `docs/tasks.md` reflect the current state of the implementation.
