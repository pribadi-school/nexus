# Nexus Signage

Signage is the section below Chromebooks. It runs in the existing PHP app,
with MP4/MOV videos and PNG/JPG images on the same hosting account as Nexus. It does not use AbleSign's
API or media storage.

The native Android TV player adds downloaded video playback and code pairing.
See [Android TV installation and automatic startup](android-tv.md).

## Set up a display

1. Open **Signage → Content**. Upload a video or image, or add a website URL.
   The upload popup accepts multiple MP4/MOV videos and PNG/JPG images together,
   by dropping files or choosing them. Each file has the displayed upload limit.
   Files upload one at a time with a byte-based percentage, then a saving state
   until the server confirms success. Successful files appear immediately;
   retrying a partial batch sends only the remaining files.
2. Open **Screens** and add a screen. Each screen gets its own playlist.
3. Open the screen: its playlist is on the left and the content library on the
   right. Drag library content into the playlist, drag entries to reorder them,
   then click **Save changes**. Videos play to the end; each image or website
   entry has its own display time in minutes. Add, move and remove buttons also work with keyboard
   and touch. On narrow screens the library appears below the playlist.
   **Settings** opens orientation, video fit, mute and pairing/player controls.
4. Copy the screen's player link from its edit page. Open it in the TV's browser
   or use it as a website item in AbleSign. The TV must be able to reach the
   Nexus address; `localhost` only works on the computer running Nexus.

Each player loops its playlist and checks for changes every 30 seconds.
Pausing a screen stops playback after its next check. “Online” means the player
recently checked in; it does not verify that the physical TV is switched on or
that a third-party website rendered correctly. “Never connected” means there
has not been a player check-in yet. Use **Settings → Open player** on a screen
to check its content (enable playback first if the screen is paused).

The Content library's three-dot menu can add a video or website directly to a
selected TV's playlist. Websites added this way start at 30 seconds; change
their duration in the screen editor. This direct action saves immediately.
Dragging and editing inside a screen is a draft until **Save changes**.
If another tab changes the same playlist, saving is refused so it cannot
silently overwrite those changes.

Saving TV selections and deleting content pause optional video previews and
have a 12-second browser deadline, including the response body. These JSON
actions release the PHP session lock before database/file work, so another tab
does not have to wait on the same login session. A connection failure can happen
after a change was saved: the menu keeps the draft and never repeats a write
automatically. Refresh the library to check its current state before retrying.

Existing screen assignments stay in place. An older shared playlist is copied
when its screen is opened or edited, keeping item order and website timing.
Each new screen starts with its own empty playlist. There is no separate
playlist menu or assignment selector, and this update needs no SQL migration.

The player starts videos muted by default. Playback with sound, and fullscreen,
may require a click or TV-remote action depending on the browser. A website can
refuse embedding using its own browser security headers; Nexus cannot bypass
that restriction. Preview each URL on the actual TV. This web player needs
connectivity; it does not provide native Android downloads or guaranteed
offline playback. When using AbleSign, its website-item duration must be long
enough for the Nexus playlist or it will leave the page at that duration.

## cPanel setup

- Deploy the code using the existing Nexus process and import
  `database/migrations/006_signage.sql` once, after migration 005.
- Set `APP_URL` in `.env` to the public HTTPS Nexus address so copied player
  links work on TVs.
- Keep `storage/signage/media/` (videos) and `storage/signage/screens/` (TV photos) writable by PHP and outside the public document
  root. Uploads are served through Nexus with byte-range support for video.
- PHP needs `fileinfo`, alongside the app's existing extensions. No Composer,
  Node.js, FFmpeg, worker, or background daemon is required on the server.
- `SIGNAGE_MAX_UPLOAD_MB=250` sets the application's maximum per media file.
  You can lower this value; the application caps uploads at 250 MB even if an
  older `.env` still says 500. In cPanel's MultiPHP INI Editor,
  also set `upload_max_filesize` to the desired file limit, `post_max_size`
  slightly larger (for example, 256M for a 250M upload), and suitable
  `max_input_time` / `max_execution_time` values for slow uploads. The content
  form shows the effective limit, including PHP's limits. Hosting proxy limits
  may impose a smaller cap.
- `update.sh` publishes Nexus-only 250M/256M PHP upload/request settings for
  LiteSpeed/LSAPI and CGI/FastCGI. The browser checks file size before upload;
  discarded oversized request bodies return 413, rather than a misleading
  form-expired error. Valid uploads still require the session CSRF token.
- Files consume the hosting plan's disk space and transfer allowance. Browser
  caching may reduce transfers, but repeated playback across many TVs can
  consume substantial bandwidth. Check the hosting provider's media-serving
  policy and allowance before a large rollout.
- Back up the database, `storage/signage/media/` and `storage/signage/screens/`. Runtime uploads are
  excluded from Git and will not be copied by a Git deployment.

## Player links and access

Screen links work without an administrator login. Treat them as private links:
anyone with one can view that screen's assigned content. Website items receive
no Nexus administrator credentials. **Regenerate player link** revokes the old
link; update it on the TV afterwards. Deleting a screen also revokes its link.
Media access through a screen link is limited to its assigned playlist.

Content in a playlist must be removed from that playlist before it can be
deleted from the library. Deleting a playlist unassigns it from screens.
