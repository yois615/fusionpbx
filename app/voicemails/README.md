# Voicemail WAV export

The **Export WAVs** button on the voicemail messages page exports every message
in the page's authorized mailbox scope, across all pagination pages. It is
available only with `voicemail_message_view` permission and filesystem storage
(not base64). The export endpoint enforces the same restrictions and rechecks
mailbox access for status and download requests.

The export snapshots message metadata, then starts a separate PHP CLI worker.
No audio is loaded into the web request or PHP strings. The worker checks WAV
files and checkpoints its real prepared/skipped counts every 100 messages or
once per second. These are preparation counts, not bytes compressed or an
estimate of remaining time. Missing, unreadable, non-WAV and out-of-storage
symlink targets are skipped and counted. MP3s are not converted. Message read/
saved state is unchanged.

After preparation, **Finalizing ZIP...** remains visible with an indeterminate
progress indicator until libzip has actually read the source files and closed
the archive. A recording removed during finalization causes an explicit
failure instead of a partial-success download. ZIP entries use
`mailbox-id/msg_message-uuid.wav` names. Files use ZIP's STORE method to avoid
expensive audio compression; provision disk space for approximately the total
source WAV size. There is one ZIP and one user-clicked download, not automatic
multiple downloads. Reloading the page resumes status polling.

## Server requirements

- Unix-based FusionPBX server with `exec` enabled for the web PHP process.
- PHP CLI at `PHP_BINDIR/php` (normally `/usr/bin/php`), with the `zip`
  extension enabled in **both CLI and web PHP**.
- The web process must be allowed to launch the CLI worker. It inherits the
  web user's filesystem permissions and must be able to read voicemail WAVs.
- A writable, private system temporary directory outside the web document root.
  Exports are stored below `sys_get_temp_dir()/fusionpbx-voicemail-exports`,
  with private per-domain/user directories. One export can run per domain/user.
- Enough disk space and process resources for the complete ZIP. PHP CLI runs
  without a request execution timeout; web-server request timeouts do not
  govern it. Operating-system process limits still apply.

Archives expire 24 hours after export creation. Status/download checks and a
new export clean up expired files for that user. To reclaim exports even when
users never return, schedule the following hourly **as the web server user**
(adjust the installation path and PHP temporary-directory configuration):

```cron
0 * * * * /usr/bin/php /var/www/fusionpbx/app/voicemails/resources/jobs/voicemail_message_export.php --cleanup
```

Cleanup uses nonblocking locks and never removes an active export or download.
Creating another export replaces that user's previous completed archive.
Worker errors are logged to PHP's error log and the private export's
`worker.log`; the page shows an explicit failure. A worker that cannot start
is reported as failed after a 30-second startup grace period. No cron job is
needed for ZIP generation itself.

## Focused tests

From the repository root, run:

```shell
php app/voicemails/resources/tests/voicemail_message_export.php
php app/voicemails/resources/tests/voicemail_messages_export.php
node app/voicemails/resources/tests/voicemail_message_export.js
```

The PHP test runs the real CLI worker against 205 generated WAV files and
checks the ZIP, download bytes, progress stages, skipped files, expiry and
locking. The endpoint test executes copies of the actual page and endpoint
with isolated authentication/database fixtures to verify storage-mode
visibility, request validation, CSRF and mailbox-access guards. The JavaScript
test checks the banner lifecycle and verifies that downloading requires an
explicit click. A deployed-server smoke test is still needed for the web
user's CLI-launch permissions and the site's actual mailbox data.
