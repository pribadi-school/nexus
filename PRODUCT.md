# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

The Pribadi School IT team manages school infrastructure through Nexus.

## Product Purpose

Track school devices and their condition, and manage content displayed on
corridor Android TVs from the same web application.

## Operating Context

Nexus already manages StudyTouch boards, CCTV cameras and student Chromebooks.
The signage addition manages screens, uploaded MP4s, website addresses and
ordered playlists. TVs can use the native Nexus Signage Android TV player,
the browser player, or a website item in AbleSign.

## Capabilities and Constraints

- Preserve the existing English interface and Nexus identity.
- Use the existing PHP and MySQL/MariaDB application without server build steps.
- Store uploaded media on the school's cPanel hosting account.
- The Android TV player pairs by a code approved in Nexus, downloads MP4s to
  local storage, and retains the last complete playlist for offline video.
  Website content needs a network connection.
- Automatic TV startup uses a one-time default Home/launcher setup. The app
  also handles boot broadcasts on supported devices and resumes after standby.
- Actual hosting disk, transfer and upload limits depend on the hosting plan.

## Brand Commitments

Nexus uses its existing emblem, lowercase wordmark and shared Pribadi app
design system, documented in DESIGN.md.
