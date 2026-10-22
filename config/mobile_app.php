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
 *
 * iOS has its own pair: a build whose iOS build number (CFBundleVersion) is
 * below `ios_min_build` must update, via the App Store link. Same rule: raise
 * MOBILE_IOS_MIN_BUILD only AFTER the build is live on the App Store.
 */
return [
    'min_version_code' => (int) env('MOBILE_MIN_VERSION_CODE', 0),
    'store_url' => env('MOBILE_STORE_URL', 'https://play.google.com/store/apps/details?id=com.deilar.card'),
    'ios_min_build' => (int) env('MOBILE_IOS_MIN_BUILD', 0),
    'ios_store_url' => env('MOBILE_IOS_STORE_URL', ''),
];
