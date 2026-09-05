---
paths:
  - resources/js/app.jsx
---

# Js

## Rebuild production assets after adding Inertia pages
The production bundle must be rebuilt after adding or changing files under resources/js/Pages. A stale bundle can omit a newly added page module, causing Inertia page resolution to throw and leave form submissions in their processing state.
