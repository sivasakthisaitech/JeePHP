# BackupO

Create an encrypted backup and upload it to the private JeePHP Backups folder in Google Drive:

```bash
php jeep backup:key-generate
php jeep drive:connect path/to/desktop-oauth-client.json
php jeep backup:database --drive
```

The generated encryption key must be kept privately and separately from the backups. Restore replaces existing tables; JeePHP asks for `RESTORE` confirmation and requires `--force`.
