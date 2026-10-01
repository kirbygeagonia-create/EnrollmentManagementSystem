<?php

namespace App\Services;

class ChromiumLocator
{
    /**
     * Locate a Chrome/Edge binary for Browsershot.
     *
     * PDF rendering shells out to a real browser. The npm `puppeteer` package is not
     * installed on the school machines, so without an explicit binary path every
     * "Download PDF" fails inside Browsershot with a message about a missing Chrome.
     */
    public static function locate(): ?string
    {
        foreach (self::candidates() as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function candidates(): array
    {
        $configured = config('services.chrome_path');

        $paths = [
            is_string($configured) && $configured !== '' ? $configured : null,
            'C:\Program Files\Google\Chrome\Application\chrome.exe',
            'C:\Program Files (x86)\Google\Chrome\Application\chrome.exe',
            'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe',
            'C:\Program Files\Microsoft\Edge\Application\msedge.exe',
            '/usr/bin/google-chrome',
            '/usr/bin/google-chrome-stable',
            '/usr/bin/chromium-browser',
            '/usr/bin/chromium',
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        ];

        return array_values(array_filter(
            $paths,
            fn (?string $path): bool => $path !== null
        ));
    }
}
