<?php

/*
 * Which builds of the Deilar mobile app may still run.
 *
 * The app asks `GET /api/v1/mobile/app-config` on launch and whenever it
 * returns to the foreground; a build whose Android versionCode is below
 * `min_version_code` is shown a non-dismissable "Update now" screen.
 *
 * Raise MOBILE_MIN_VERSION_CODE only AFTER the new build is live on Google
 * Play, otherwise users are locked out with nothing to update to.
 * 0 (the default) never blocks anyone.
 */
return [
    'min_version_code' => (int) env('MOBILE_MIN_VERSION_CODE', 0),
    'store_url' => env('MOBILE_STORE_URL', 'https://play.google.com/store/apps/details?id=com.deilar.card'),
];
