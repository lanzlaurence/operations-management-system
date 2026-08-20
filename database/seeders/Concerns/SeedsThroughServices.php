<?php

namespace Database\Seeders\Concerns;

use App\Models\Charge;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Material;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Helpers for seeders that build documents through the application services
 * instead of writing rows directly.
 *
 * Two reasons for going through the services:
 *
 *  1. The sample data is guaranteed to be consistent - totals, VAT splits,
 *     stock balances, movement logs, average costs and the audit trail all
 *     come out exactly as they would from the UI.
 *  2. The seeders double as a smoke test: if a service rule breaks, seeding
 *     fails loudly instead of producing data no screen can explain.
 *
 * The services read the acting user from the auth context, so the seeder signs
 * in as the administrator for the duration of the run.
 */
trait SeedsThroughServices
{
    /** @var Collection<int, Material>|null */
    private ?Collection $materialCache = null;

    /** @var Collection<int, Location>|null */
    private ?Collection $locationCache = null;

    /** @var Collection<int, Vendor>|null */
    private ?Collection $vendorCache = null;

    /** @var Collection<int, Customer>|null */
    private ?Collection $customerCache = null;

    /** @var Collection<int, Charge>|null */
    private ?Collection $chargeCache = null;

    /**
     * Run the callback signed in as the first (administrator) user, so every
     * document records a real author. Skips silently when no user exists yet.
     */
    protected function asAdministrator(callable $callback): void
    {
        $admin = User::query()->oldest('id')->first();

        if ($admin === null) {
            $this->command?->warn(static::class . ' skipped: no user to attribute the documents to.');

            return;
        }

        Auth::login($admin);

        try {
            $callback($admin);
        } finally {
            Auth::logout();
        }
    }

    protected function material(string $code): ?Material
    {
        $this->materialCache ??= Material::query()->active()->get();

        return $this->materialCache->firstWhere('code', $code);
    }

    protected function materialId(string $code): ?int
    {
        return $this->material($code)?->id;
    }

    protected function location(string $code): ?Location
    {
        $this->locationCache ??= Location::query()->get();

        return $this->locationCache->firstWhere('code', $code);
    }

    protected function locationId(string $code): ?int
    {
        return $this->location($code)?->id;
    }

    protected function vendorId(string $code): ?int
    {
        $this->vendorCache ??= Vendor::query()->get();

        return $this->vendorCache->firstWhere('code', $code)?->id;
    }

    protected function customerId(string $code): ?int
    {
        $this->customerCache ??= Customer::query()->get();

        return $this->customerCache->firstWhere('code', $code)?->id;
    }

    /**
     * Charge rows for a document payload, resolved by name.
     *
     * @param  array<int, string>  $names
     * @return array<int, array{charge_id: int}>
     */
    protected function chargeRows(array $names): array
    {
        $this->chargeCache ??= Charge::query()->active()->get();

        return collect($names)
            ->map(fn (string $name): ?Charge => $this->chargeCache->firstWhere('name', $name))
            ->filter()
            ->map(fn (Charge $charge): array => ['charge_id' => $charge->id])
            ->values()
            ->all();
    }

    /**
     * Date `$days` ago as `Y-m-d`, so the sample data always looks recent
     * regardless of when the database is seeded.
     */
    protected function daysAgo(int $days): string
    {
        return today()->subDays($days)->toDateString();
    }

    /**
     * Date `$days` in the future as `Y-m-d`.
     */
    protected function daysAhead(int $days): string
    {
        return today()->addDays($days)->toDateString();
    }
}
