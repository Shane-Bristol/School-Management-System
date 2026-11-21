# Threat Model: School Management System (Version 1)

Last updated: 2026-11-20

## 1. What we are protecting

| Asset | Why it matters |
|---|---|
| Student records (names, dates of birth, class) | Personal data about minors |
| Attendance records | Official school records; tampering has real consequences |
| Staff accounts and credentials | Gateway to everything else |
| Audit log | Evidence of who changed what; must be trustworthy |
| Application secrets (`.env`: app key, database password) | Compromise exposes everything |

## 2. Who uses the system

| Role | Can do |
|---|---|
| **Admin** | Create and disable staff accounts, manage classes and student records, assign teachers, view the audit log |
| **Teacher** | View students and record attendance **only for classes they are assigned to** |
| **Anonymous visitor** | Reach the login page only |

Version 1 has staff accounts only. Students and parents have no logins.

## 3. Trust boundaries

1. Browser to web application (untrusted input crosses here)
2. Web application to MySQL (only through the dedicated `sms_app` user)
3. Developer machine to GitHub (SSH key, no secrets in the repo)

## 4. Threats and mitigations

| # | Threat | Mitigation | Status |
|---|---|---|---|
| 1 | Stranger creates an account | Public registration disabled; accounts created by admins only | Done (config/fortify.php) |
| 2 | Password guessing / credential stuffing | Login rate limiting; strong password rules; two-factor authentication | Starter kit provides; verify and test |
| 3 | Stolen or weak passwords | Two-factor and passkeys available; passwords hashed by Laravel | Starter kit provides |
| 4 | Teacher reads or edits another class's students | Authorization policies keyed to the `class_teacher` table; tests per role | Planned |
| 5 | Privilege escalation (teacher acts as admin) | Role checked server-side on every admin action; role never taken from user input | Planned |
| 6 | SQL injection | Eloquent / query builder parameter binding only; no raw string-built SQL | Planned (review in code review) |
| 7 | Cross-site scripting (XSS) | Blade escaping by default; no unescaped output of user data | Planned (review) |
| 8 | Cross-site request forgery | Laravel CSRF protection on all forms | Framework default; keep enabled |
| 9 | Silent tampering with grades or attendance | Every create, update, and delete writes an `audit_logs` row | Planned |
| 10 | Audit log altered after the fact | Application only inserts; later, revoke UPDATE and DELETE on `audit_logs` from the app's database user | Planned |
| 11 | Secrets leaked in the repository | `.env`, `vendor/`, `node_modules/` git-ignored; checked before each commit | Done |
| 12 | Database over-privilege if app is compromised | App connects as `sms_app`, limited to the `sms` database only | Done |
| 13 | Default admin credentials | First admin created by a seeder that reads its password from `.env`; no hard-coded password | Planned |
| 14 | Former staff keep access | Accounts disabled with `is_active = false`, checked at login and on every request | Planned |
| 15 | Real student data exposed in development | Development and demos use generated fake data only | Policy |
| 16 | Developer account takeover | GitHub SSH key with passphrase; two-factor on GitHub; FileVault on the laptop | In progress |

## 5. Out of scope for version 1

- Student and parent logins (parent portal comes later)
- Denial-of-service protection beyond basic rate limiting
- Backups and disaster recovery (to be designed before any real deployment)

## 6. Before any real deployment

- Threat model reviewed again, including hosting on AWS
- HTTPS enforced; secure cookie settings reviewed
- Dependencies checked with `composer audit` and `npm audit`
- Legal and privacy requirements for student data confirmed with the school and Ministry
- Backups and a restore test in place
