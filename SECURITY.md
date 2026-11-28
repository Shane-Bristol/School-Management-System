# Security

This project handles records about children, so security shapes the design rather than being added at the end.

## Status

Version 1 is in development and has not been deployed. It uses generated fake data only. Do not put real student information into any copy of this project.

## Reporting a vulnerability

Please report security problems privately through GitHub: open this repository's **Security** tab and choose **Report a vulnerability**. Please do not open a public issue for a security problem.

Include what you found, how to reproduce it, and what you think the impact is. You should get a reply within a few days.

## How access is controlled

- **No public sign-up.** Registration is disabled. An administrator creates teacher accounts, and each teacher sets their own password through an emailed link.
- **Two roles.** Administrators manage staff, classes, and students. Teachers see and record attendance only for classes they are assigned to.
- **Enforced on the server.** Every action checks permission itself. Hiding a button is never the protection. Policies are covered by automated tests for each role, including disabled accounts and attempts to reach other classes' students.
- **Disabled accounts lose access immediately.** Disabling a user ends their sessions and signs them out on their next request. Accounts are disabled, never deleted, so history stays intact.
- **Role and active status cannot be set from a form.** Those fields are not mass-assignable.
- **Sign-in protection.** Two-factor authentication and passkeys are supported. Logins are rate limited.

## How data is protected

- **Audit log.** Account, class, student, and attendance changes record who did what, when, and from which IP address. Dates of birth and attendance notes are never written to the log; it records only that they changed.
- **Append-only at the database level.** Database triggers reject every update and delete on the audit log. The application's database account cannot remove those triggers, alter the table, or drop it. See [docs/DATABASE_ACCOUNTS.md](docs/DATABASE_ACCOUNTS.md).
- **Least-privilege database accounts.** The running app uses an account limited to `SELECT`, `INSERT`, `UPDATE`, and `DELETE`. A separate account is used only to run migrations.
- **Safe output.** Pages escape all stored text, and a test checks that markup stored in the log is displayed as text rather than run.
- **Secrets stay out of the repository.** `.env` is ignored by Git and is checked before every commit.
- **Dependencies are checked.** `composer audit` and `npm audit` are run regularly, and advisories are fixed by updating.

The full analysis is in [docs/THREAT_MODEL.md](docs/THREAT_MODEL.md).

## Known limitations

These are real gaps, listed so they are not mistaken for finished work:

- Two-factor authentication is available but not yet required for administrators.
- Login rate limiting is keyed by email and IP address together. A separate per-IP limit is planned.
- Teachers can correct attendance for any past date in their own classes. Each change is logged, but a recent-days limit is under consideration.
- Withdrawn students cannot be reinstated from the screen yet.
- There is no deployment yet. HTTPS enforcement, trusted proxy settings, backups, and a restore test must be in place before any real use.
- Legal and privacy requirements for student data have not been reviewed with a school or the relevant education authority.

## Supported versions

Only the latest commit on the default branch is supported.
