<?php

declare(strict_types=1);

namespace Proflujo\Analytics\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Serves the bundled dashboard assets so no publishing or frontend build is required. */
final class AssetController
{
    private const array FILES = [
        'chart.umd.js' => 'application/javascript; charset=utf-8',
        'dashboard.js' => 'application/javascript; charset=utf-8',
        'dashboard.css' => 'text/css; charset=utf-8',
    ];

    public function __invoke(string $file): BinaryFileResponse
    {
        abort_unless(isset(self::FILES[$file]), 404);

        return response()->file(__DIR__.'/../../../public/'.$file, [
            'Content-Type' => self::FILES[$file],
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
