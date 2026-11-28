# Database accounts and the audit log lock

The application uses two MySQL accounts so that a compromise of the running app cannot rewrite history.

| Account | Used for | Privileges on `sms` |
|---|---|---|
| `sms_app` | The running application | `SELECT`, `INSERT`, `UPDATE`, `DELETE` only |
| `sms_migrator` | Running migrations and seeders by hand | Everything (`ALL PRIVILEGES`) |

## Why

- The `audit_logs` table has database triggers that reject every `UPDATE` and `DELETE`.
- `sms_app` has no `TRIGGER`, `ALTER`, or `DROP` privilege, so it cannot remove those triggers, change the table, or drop it.
- A bug or stolen app login can therefore add audit entries, but not edit or erase them.
- Someone with the migrator or root account can still change anything. This protects against a compromised application, not against a malicious database administrator.

## Running migrations

The app account cannot create or change tables, so run migrations with the migrator account. The password is typed at a hidden prompt so it never lands in shell history:

```bash
read -s -p "Migrator password: " P; echo
DB_USERNAME=sms_migrator DB_PASSWORD="$P" php artisan migrate
unset P
```

The same pattern works for `migrate:fresh` and `db:seed`.

## Checking the lock

Connect as `sms_app` and try to tamper with the log. Every one of these should fail:

```sql
UPDATE audit_logs SET action = 'x' WHERE id = 1;  -- rejected by trigger
DELETE FROM audit_logs WHERE id = 1;              -- rejected by trigger
DROP TRIGGER audit_logs_no_update;                -- permission denied
DROP TABLE audit_logs;                            -- permission denied
```

The automated tests check the trigger behaviour on the test database (`tests/Feature/AuditLogLockTest.php`).

## Limits

- Cascaded foreign key actions do not fire triggers in MySQL. Users are never deleted in this app, so this does not apply today.
- Production needs its own account setup and a managed database with backups. See the deployment notes once written.
