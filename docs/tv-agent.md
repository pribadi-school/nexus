# TV control helper (always-on school PC)

The Nexus website runs on the internet and cannot reach TVs on the school
network. The helper `bin/tv-agent.ps1` runs on an always-on school PC, asks
Nexus every 10 seconds for actions, and runs them on the TVs over ADB
(network USB debugging). Each screen's page in Nexus then shows a **TV control**
card with four buttons:

| Button | What runs on the TV |
|---|---|
| Open Nexus | `am start` of the Nexus app (after someone pressed Home) |
| Check status | Which app is on screen, Wi-Fi state, time since start |
| Screenshot | `screencap`, sent back as a 1280 px JPEG |
| Restart TV | `reboot` (asks for confirmation) |

Only these actions exist. The helper checks each TV's serial number before
every action, so a reused IP address cannot control the wrong TV. Actions not
collected within 10 minutes expire instead of running late.

## 1. Server (once)

1. Import `database/migrations/013_signage_tv_control.sql` in phpMyAdmin.
2. Add a long random secret to the server `.env`:
   ```
   SIGNAGE_TV_AGENT_TOKEN=<64 random hex characters>
   ```
   Generate one in cPanel Terminal: `php -r "echo bin2hex(random_bytes(32));"`.
   Without it the TV control card stays hidden and the helper API is off.

## 2. Helper PC (once)

1. Copy the repository's `bin` folder (at least `tv-agent.ps1` and
   `tv-agent.example.json`) to the PC, e.g. `C:\NexusTV\`.
2. Download Android **platform-tools** from Google
   (https://developer.android.com/tools/releases/platform-tools), unzip to
   `C:\platform-tools\`.
3. Copy `tv-agent.example.json` to `tv-agent.json` and fill in:
   - `token`: the same value as `SIGNAGE_TV_AGENT_TOKEN`.
   - `screens`: one entry per TV. `screen_id` is the number in the Nexus
     address `/signage/screens/<id>/edit`; `address` is the TV's reserved IP;
     `serial` is from `adb -s <ip>:5555 shell getprop ro.serialno`.
   Keep this file on the PC only: it holds the secret.
4. Allow this PC on each TV once: run
   `C:\platform-tools\adb.exe connect <tv-ip>:5555`, then choose **Always allow**
   on the TV.
5. Test: `powershell -ExecutionPolicy Bypass -File C:\NexusTV\tv-agent.ps1 -Once`.
   The TV control card should now say **Helper PC online**.

## 3. Start automatically

In an administrator Command Prompt on the helper PC:

```
schtasks /Create /TN "Nexus TV helper" /SC ONSTART /RU SYSTEM /RL HIGHEST /TR "powershell -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File C:\NexusTV\tv-agent.ps1"
schtasks /Run /TN "Nexus TV helper"
```

The log is `tv-agent.log` next to the script. Running as SYSTEM uses
SYSTEM's own ADB key, so step 2.4 must be repeated once from the task (press
**Check status** in Nexus and choose **Always allow** on each TV), or run the
task as the signed-in user instead (`/RU <user>` with `/SC ONLOGON`).

## Notes

- TVs need fixed addresses (MikroTik DHCP reservations) and debugging left on.
- A TV that is off, off Wi-Fi, or showing a debugging prompt reports a clear
  failure message in the card.
- Screenshots can show student content: only the latest 50 are kept, served to
  signed-in people only.
