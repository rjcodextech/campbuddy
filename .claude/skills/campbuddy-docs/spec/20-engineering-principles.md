# 20. Engineering principles

[← Back to index](../SKILL.md) · Previous: [19. Suggested build order](19-build-order.md) · Next: [21. Performance & scalability hygiene →](21-performance-hygiene.md)

Process discipline for whoever (or whichever agent) implements this spec — these matter as much as the feature list:

1. Inspect and understand the existing project structure before adding to it.
2. Reuse existing components where appropriate — don't rewrite stable, working functionality unnecessarily.
3. Don't change unrelated functionality while implementing a specific requirement.
4. Keep components modular; avoid giant files mixing unrelated logic.
5. Separate concerns: presentation, business logic, event data, persistence, and API communication each stay in their own layer (Laravel's own conventions — Models, Jobs, Controllers, Blade views, [6](06-technology-stack.md) — already push in this direction; don't fight them).
6. Maintain backwards compatibility with existing CampBuddy functionality — don't remove a feature unless a requirement explicitly replaces it.
7. Code should be readable, appropriately named, documented where the *why* isn't obvious from the code itself, and free of unnecessary duplication.
8. Don't add a dependency when a small amount of native functionality solves the problem cleanly ([4.2](04-non-functional-requirements.md#42-performance), [5.5](05-system-architecture.md#55-asset-build--laravels-default-vite-pipeline) — vanilla JS stays the default).
9. Don't present placeholder implementations as finished features — an empty state ([4.3](04-non-functional-requirements.md#43-error-handling--empty-states)) is a deliberate design choice; a silently-broken feature dressed up to look done is not.
10. Test responsive behavior ([15.4](15-testing.md#154-responsive-testing)) before considering any UI feature finished, not as a separate pass at the very end.
