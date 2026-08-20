<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown when a domain rule blocks an operation: posting a non-draft order,
 * completing a receipt twice, editing a document that stock already moved
 * against, and so on.
 *
 * Because the exception renders itself, services can guard their own
 * invariants and controllers stay free of defensive `if` ladders. Anything
 * thrown inside `DB::transaction()` also rolls the transaction back, so a
 * rejected operation can never leave a half-written document behind.
 */
class BusinessRuleException extends RuntimeException
{
    /**
     * Route name to redirect to instead of bouncing back, with its parameters.
     *
     * @var array{0: string, 1: array<string, mixed>}|null
     */
    protected ?array $redirect = null;

    public static function make(string $message): static
    {
        return new static($message);
    }

    /**
     * Send the user to a specific route rather than back to the previous page,
     * used when the page they came from no longer makes sense.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function redirectTo(string $route, array $parameters = []): static
    {
        $this->redirect = [$route, $parameters];

        return $this;
    }

    /**
     * Render the failure the same way the controllers used to: an inline
     * `errors.error` bag plus a flashed `error` message for the toast.
     */
    public function render(Request $request): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->getMessage(),
                'errors' => ['error' => [$this->getMessage()]],
            ], 422);
        }

        $response = $this->redirect === null
            ? back()
            : redirect()->route($this->redirect[0], $this->redirect[1]);

        return $response
            ->withErrors(['error' => $this->getMessage()])
            ->with('error', $this->getMessage());
    }
}
