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
        $configured = config('services.chrome_path');

        if (is_string($configured) && $configured !== '') {
            // An operator who named a binary means that one. Falling back to a different
            // browser would draw the paper with a different layout engine, and the four
            // printed papers are signed off against one specific rendering.
            return file_exists($configured) ? $configured : null;
        }

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
        $local = getenv('LOCALAPPDATA');

        $paths = [
            'C:\Program Files\Google\Chrome\Application\chrome.exe',
            'C:\Program Files (x86)\Google\Chrome\Application\chrome.exe',
            // A user-scope install is the other half of what school machines actually
            // run; without it a desk with Chrome in its own profile looks broken.
            is_string($local) ? $local.'\Google\Chrome\Application\chrome.exe' : null,
            'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe',
            'C:\Program Files\Microsoft\Edge\Application\msedge.exe',
            is_string($local) ? $local.'\Microsoft\Edge\Application\msedge.exe' : null,
            '/usr/bin/google-chrome',
            '/usr/bin/google-chrome-stable',
            '/opt/google/chrome/chrome',
            '/usr/bin/chromium-browser',
            '/usr/bin/chromium',
            '/snap/bin/chromium',
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        ];

        return array_values(array_filter(
            $paths,
            fn (?string $path): bool => $path !== null
        ));
    }
}
