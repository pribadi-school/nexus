# nexus

**Connecting to Pribadi School.** Nexus is the IT infrastructure dashboard for
Pribadi School: it tracks the school's StudyTouch boards, CCTV cameras and
student Chromebooks, and the condition each one is in.

- Plain PHP 8.2 + MySQL/MariaDB, no framework and no build step on the server.
- Runs on Hostinger shared hosting (cPanel) at `nexus.pribadidepok.sch.id`.
- Sign in with your name and the shared Nexus password.

## Run it on your computer

Needs LocalWP installed (its PHP and MariaDB are reused):

```
powershell -ExecutionPolicy Bypass -File bin\dev.ps1
```

Then open http://localhost:8000. The first run creates a local database with
sample data and a local `.env`; the sign-in password is `APP_PASSWORD` in that file.

## More

- Project guide and conventions: [CLAUDE.md](CLAUDE.md)
- Design rules: [DESIGN.md](DESIGN.md)
- Database: [database/migrations](database/migrations)
- Hosting setup: [cPanel deployment guide](docs/deploy-cpanel.md)
- Chromebook reports and OU management: [Google Admin setup](docs/google-admin.md)
