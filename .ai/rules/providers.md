---
paths:
  - '{app/Models/User.php,app/Http/Controllers/Auth/**,app/Providers/AppServiceProvider.php,routes/web.php}'
---

# Providers

## Central role-aware dashboard redirects
Use User::homeRouteName() as the sole post-authentication destination for student and teacher users. The teacher dashboard remains the existing `dashboard` route; the current student dashboard is `student.dashboard`. Do not reintroduce an intended or generic dashboard redirect that bypasses the role check.
