<?php

namespace App\Support;

class PrintAssets
{
    private static ?string $logoDataUri = null;

    /**
     * The school logo as a data URI, for the print templates.
     *
     * Browsershot renders a print view from a temporary file:// page, so an
     * asset() URL sends the headless browser back into the application: under a
     * single-threaded server (php artisan serve) that request can never be
     * served while the PDF request is still open, the render dies on a navigation
     * timeout, and every "Download PDF" is a 500. When the app URL is simply
     * unreachable the image fails quietly, and the school prints its documents
     * with no logo on them. Inlining keeps each document self-contained — and
     * correct with the network down.
     */
    public static function logoDataUri(): string
    {
        if (self::$logoDataUri !== null) {
            return self::$logoDataUri;
        }

        $path = public_path('images/logos/seait-logo.png');
        $bytes = is_file($path) ? @file_get_contents($path) : false;

        return self::$logoDataUri = $bytes === false ? '' : 'data:image/png;base64,'.base64_encode($bytes);
    }
}
