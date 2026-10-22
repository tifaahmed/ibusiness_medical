<?php

namespace App\Support;

/**
 * Just enough user-agent reading for the visitors list: device class, OS and
 * browser. Not a full parser — an unknown agent simply yields nulls.
 */
class UserAgentInfo
{
    /** @return array{device_type: ?string, os: ?string, browser: ?string, is_bot: bool} */
    public static function parse(?string $ua): array
    {
        if ($ua === null || trim($ua) === '') {
            return ['device_type' => null, 'os' => null, 'browser' => null, 'is_bot' => false];
        }

        $bot = (bool) preg_match('/bot|crawl|spider|slurp|facebookexternalhit|preview|headless|lighthouse|curl|wget|python-requests|monitor/i', $ua);

        $device = match (true) {
            $bot => 'bot',
            (bool) preg_match('/ipad|tablet|kindle|silk/i', $ua) => 'tablet',
            (bool) preg_match('/mobi|iphone|ipod|android/i', $ua) => 'mobile',
            default => 'desktop',
        };

        $os = match (true) {
            (bool) preg_match('/windows/i', $ua) => 'Windows',
            (bool) preg_match('/iphone|ipad|ipod|ios|cfnetwork|darwin/i', $ua) => 'iOS',
            (bool) preg_match('/android/i', $ua) => 'Android',
            (bool) preg_match('/mac os x|macintosh/i', $ua) => 'macOS',
            (bool) preg_match('/linux|x11/i', $ua) => 'Linux',
            default => null,
        };

        $browser = match (true) {
            (bool) preg_match('/edg(e|a|ios)?\//i', $ua) => 'Edge',
            (bool) preg_match('/opr\/|opera/i', $ua) => 'Opera',
            (bool) preg_match('/samsungbrowser/i', $ua) => 'Samsung Internet',
            (bool) preg_match('/firefox|fxios/i', $ua) => 'Firefox',
            (bool) preg_match('/chrome|crios/i', $ua) => 'Chrome',
            (bool) preg_match('/safari/i', $ua) => 'Safari',
            (bool) preg_match('/okhttp|dart|expo|react-native/i', $ua) => 'App',
            default => null,
        };

        return ['device_type' => $device, 'os' => $os, 'browser' => $browser, 'is_bot' => $bot];
    }
}
