# Nexus Signage

Signage is the section below Chromebooks. It runs in the existing PHP app,
with MP4 files on the same hosting account as Nexus. It does not use AbleSign's
API or media storage.

The native Android TV player adds downloaded video playback and code pairing.
See [Android TV installation and automatic startup](android-tv.md).

## Set up a display

1. Open **Signage → Content**. Upload an MP4 or add a website URL.
2. Open **Playlists**, create a playlist, and add content in playback order.
   Videos play to the end; website items have a display time in seconds.
3. Open **Screens**, add a screen, and assign its playlist. Select orientation,
   video fit, and whether videos should be muted.
4. Copy the screen's player link from its edit page. Open it in the TV's browser
   or use it as a website item in AbleSign. The TV must be able to reach the
   Nexus address; `localhost` only works on the computer running Nexus.

Each player loops its playlist and checks for changes every 30 seconds.
Pausing a screen stops playback after its next check. “Online” means the player
recently checked in; it does not verify that the physical TV is switched on or
that a third-party website rendered correctly. “Never connected” means there
has not been a player check-in yet. Use **Preview playlist** on a playlist to check
its content before assigning it to a TV.

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
- Keep `storage/signage/media/` writable by PHP and outside the public document
  root. Uploads are served through Nexus with byte-range support for video.
- PHP needs `fileinfo`, alongside the app's existing extensions. No Composer,
  Node.js, FFmpeg, worker, or background daemon is required on the server.
- `SIGNAGE_MAX_UPLOAD_MB=500` sets the application's maximum per MP4. This is
  configurable; it is not an AbleSign quota. In cPanel's MultiPHP INI Editor,
  also set `upload_max_filesize` to the desired file limit, `post_max_size`
  slightly larger (for example, 512M for a 500M upload), and suitable
  `max_input_time` / `max_execution_time` values for slow uploads. The content
  form shows the effective limit, including PHP's limits. Hosting proxy limits
  may impose a smaller cap.
- Files consume the hosting plan's disk space and transfer allowance. Browser
  caching may reduce transfers, but repeated playback across many TVs can
  consume substantial bandwidth. Check the hosting provider's media-serving
  policy and allowance before a large rollout.
- Back up both the database and `storage/signage/media/`. Runtime uploads are
  excluded from Git and will not be copied by a Git deployment.

## Player links and access

Screen links work without an administrator login. Treat them as private links:
anyone with one can view that screen's assigned content. Website items receive
no Nexus administrator credentials. **Regenerate player link** revokes the old
link; update it on the TV afterwards. Deleting a screen also revokes its link.
Media access through a screen link is limited to its assigned playlist.

Content in a playlist must be removed from that playlist before it can be
deleted from the library. Deleting a playlist unassigns it from screens.
