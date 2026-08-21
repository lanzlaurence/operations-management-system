<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Base controller.
 *
 * `AuthorizesRequests` is what lets the controllers call `$this->authorize()`
 * against the policies in App\Policies instead of repeating permission names
 * in middleware definitions.
 */
abstract class Controller
{
    use AuthorizesRequests;
}
