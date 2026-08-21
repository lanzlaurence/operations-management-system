<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a file from the private disk to an authenticated user.
 *
 * The only controller left in the application: everything else is a Livewire
 * component, but a file download is a plain HTTP response with no component
 * state behind it.
 */
class FileController extends Controller
{
    public function show(Request $request): StreamedResponse
    {
        $path = trim((string) $request->query('path'));

        // A missing or traversing path is a 404, not a 500: the parameter is
        // user-supplied, so it cannot be assumed to be a usable string.
        if ($path === '' || str_contains($path, '..')) {
            abort(404);
        }

        if (! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return Storage::disk('local')->response($path);
    }
}
