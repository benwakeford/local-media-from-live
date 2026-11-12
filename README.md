# local-media-from-live

On a local WordPress site, pull media from its live version when it's not found on the local disk.

A single-file must-use plugin for local development copies of WordPress sites (built for [LocalWP](https://localwp.com/), works with anything that sets `WP_ENVIRONMENT_TYPE` to `local`). It means you can pull a site's database without downloading gigabytes of uploads: anything missing locally is shown from the live site instead, and anything you do have locally is used as normal.

## What it handles

- Attachment URLs: media library, featured images, ACF image fields, `srcset`.
- Upload URLs hardcoded anywhere in the front-end HTML: post content, theme templates, inline CSS backgrounds.
- TimThumb links (`thumb.php?src=...`) used by older themes. TimThumb can't fetch images from another site, so when the original isn't on the local disk the link is replaced with the live image.

It only ever rewrites URLs; nothing is downloaded or written to disk.

## Safety

- Does nothing unless `wp_get_environment_type()` is `local`, or the site opts in with `LMFL_ALLOW_NON_LOCAL` (see below).
- Does nothing unless `LMFL_LIVE_URL` is defined (shows a notice in wp-admin instead).
- Does nothing if `LMFL_LIVE_URL` is the site's own domain (with or without `www.`), so a wp-config copied to live by mistake can't point the site at itself.
- Keep it out of your live deployments anyway. The setup below keeps it out of the site's own repo.

## Install (shared copy, symlinked)

Keep one clone of this repo and symlink the plugin file into each local site, so a `git pull` here updates every site.

```sh
git clone https://github.com/benwakeford/local-media-from-live.git ~/path/to/local-media-from-live

ln -s ~/path/to/local-media-from-live/local-media-from-live.php \
  /path/to/site/app/public/wp-content/mu-plugins/local-media-from-live.php
```

Use an absolute path for the link. Create `mu-plugins` first if the site doesn't have one.

Make sure the site's own `.gitignore` excludes the link (`git status` in the site should stay clean).

## Configure (per site, in wp-config.php)

```php
define( 'WP_ENVIRONMENT_TYPE', 'local' );

// Local: Media from Live
define( 'LMFL_LIVE_URL', 'https://www.example.com' );
```

| Constant | Required | Default | Notes |
|---|---|---|---|
| `LMFL_LIVE_URL` | Yes | | The live site. Include `www.` if live uses it. Trailing slash optional. |
| `LMFL_LIVE_UPLOADS_URL` | No | `LMFL_LIVE_URL` + `/wp-content/uploads` | Only if live's uploads are somewhere else (custom upload path, CDN). |
| `LMFL_TIMTHUMB` | No | `true` | Set to `false` to leave `thumb.php` links alone. |
| `LMFL_ALLOW_NON_LOCAL` | No | `false` | Set to `true` to run on a copy that isn't `local`, e.g. a client preview on InstaWP. Only ever set it in that copy's own wp-config.php. |

## Preview copies (InstaWP etc.)

Useful when a site is pushed to a throwaway host for client previews and copying the whole uploads folder takes too long.

1. Copy the plugin file into the preview site's `wp-content/mu-plugins/` as a real file (a symlink won't survive the trip).
2. Add to the preview site's wp-config.php:

   ```php
   define( 'LMFL_LIVE_URL', 'https://www.example.com' );
   define( 'LMFL_ALLOW_NON_LOCAL', true );
   ```
3. Push without media, or only the recent upload months the preview needs. Anything new that isn't on live yet still has to be on the preview site itself.

Never set `LMFL_ALLOW_NON_LOCAL` on a staging or production server that clients or the public use for real.

Put site-specific settings here rather than editing the plugin, so every site runs the same file.

## Notes

- Keep any local uploads archive **outside** `wp-content/uploads`. Cloud-only placeholder files (Dropbox, iCloud) count as "on disk", so the plugin won't swap them and the web server can't serve them.
- Turn it off for one site by removing the symlink or the `LMFL_LIVE_URL` line.

## Changelog

- **2.1.0** `LMFL_ALLOW_NON_LOCAL` lets a preview copy (e.g. InstaWP) opt in. Does nothing if `LMFL_LIVE_URL` points at the site's own domain.
- **2.0.1** TimThumb links with a site-relative `src` (no domain, as some WooThemes themes produce) are now handled.
- **2.0.0** Settings moved to wp-config.php so one copy serves every site. Optional live uploads URL. TimThumb handling can be switched off. Admin notice when not configured.
- **1.1** TimThumb `thumb.php` support.
- **1.0** First version.

## Licence

GPL-2.0-or-later.
