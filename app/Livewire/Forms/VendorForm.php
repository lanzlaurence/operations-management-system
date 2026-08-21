<?php

namespace App\Livewire\Forms;

use App\Enums\RecordStatus;
use App\Models\Vendor;
use App\Support\Money;
use Livewire\Form;

/**
 * The vendor form, shared by the create and edit screens.
 *
 * Two things make it heavier than the configuration forms:
 *
 *  - contact persons are a repeater stored as JSON, so rows are added and
 *    removed here and blank rows are dropped before validation;
 *  - vendors keep a field-level change log, so the save records what
 *    actually changed together with the reason the user typed.
 */
class VendorForm extends Form
{
    public ?Vendor $vendor = null;

    public string $name = '';

    public string $country = '';

    public string $state_province = '';

    public string $city = '';

    public string $suburb_barangay = '';

    public string $postal_code = '';

    public string $address_line_1 = '';

    public string $address_line_2 = '';

    public string $payment_terms = '';

    public string $credit_amount = '0';

    public string $status = RecordStatus::Active->value;

    /**
     * Contact person rows: name, email, phone.
     *
     * @var array<int, array{name: string, email: string, phone: string}>
     */
    public array $contact_persons = [
        ['name' => '', 'email' => '', 'phone' => ''],
    ];

    /**
     * Reason for the change, stored on the audit entry rather than the record.
     */
    public string $update_remarks = '';

    /**
     * Attributes that make up the record, in the order the log should read.
     *
     * @var array<int, string>
     */
    private const ATTRIBUTES = [
        'name', 'country', 'state_province', 'city', 'suburb_barangay',
        'postal_code', 'address_line_1', 'address_line_2', 'payment_terms',
        'contact_persons', 'credit_amount', 'status',
    ];

    public function setVendor(Vendor $vendor): void
    {
        $this->vendor = $vendor;

        $this->name = $vendor->name;
        $this->country = (string) $vendor->country;
        $this->state_province = (string) $vendor->state_province;
        $this->city = (string) $vendor->city;
        $this->suburb_barangay = (string) $vendor->suburb_barangay;
        $this->postal_code = (string) $vendor->postal_code;
        $this->address_line_1 = (string) $vendor->address_line_1;
        $this->address_line_2 = (string) $vendor->address_line_2;
        $this->payment_terms = (string) $vendor->payment_terms;
        $this->credit_amount = (string) Money::round($vendor->credit_amount);
        $this->status = $vendor->status->value;

        $contacts = collect($vendor->contact_persons ?? [])
            ->map(fn (array $contact): array => [
                'name' => (string) ($contact['name'] ?? ''),
                'email' => (string) ($contact['email'] ?? ''),
                'phone' => (string) ($contact['phone'] ?? ''),
            ])
            ->values()
            ->all();

        $this->contact_persons = $contacts ?: [['name' => '', 'email' => '', 'phone' => '']];
    }

    // ── Contact person repeater ──────────────────────────────────────────────

    public function addContactPerson(): void
    {
        $this->contact_persons[] = ['name' => '', 'email' => '', 'phone' => ''];
    }

    public function removeContactPerson(int $index): void
    {
        unset($this->contact_persons[$index]);

        $this->contact_persons = array_values($this->contact_persons);

        if ($this->contact_persons === []) {
            $this->contact_persons = [['name' => '', 'email' => '', 'phone' => '']];
        }
    }

    // ── Validation ───────────────────────────────────────────────────────────

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'state_province' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'suburb_barangay' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'credit_amount' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'status' => ['required', RecordStatus::rule()],

            'contact_persons' => ['array', 'max:20'],
            'contact_persons.*.name' => ['required', 'string', 'max:255'],
            'contact_persons.*.email' => ['required', 'email', 'max:255'],
            'contact_persons.*.phone' => ['required', 'string', 'max:64'],

            'update_remarks' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Enter the vendor name.',
            'credit_amount.required' => 'Enter a credit limit, or 0 for none.',
            'contact_persons.*.name.required' => 'Enter the contact person name.',
            'contact_persons.*.email.required' => 'Enter the contact email.',
            'contact_persons.*.email.email' => 'Enter a valid email address.',
            'contact_persons.*.phone.required' => 'Enter the contact phone number.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'state_province' => 'state / province',
            'suburb_barangay' => 'suburb / barangay',
            'address_line_1' => 'address line 1',
            'address_line_2' => 'address line 2',
            'payment_terms' => 'payment terms',
            'credit_amount' => 'credit amount',
            'update_remarks' => 'reason for the change',
        ];
    }

    /**
     * Trim everything and drop contact rows the user left empty, so a blank
     * repeater row never fails validation on its own.
     */
    protected function prepareForValidation($attributes)
    {
        foreach (self::ATTRIBUTES as $field) {
            if ($field === 'contact_persons') {
                continue;
            }

            if (isset($attributes[$field]) && is_string($attributes[$field])) {
                $attributes[$field] = trim($attributes[$field]);
            }
        }

        $attributes['contact_persons'] = collect($attributes['contact_persons'] ?? [])
            ->map(fn (array $contact): array => [
                'name' => trim((string) ($contact['name'] ?? '')),
                'email' => trim((string) ($contact['email'] ?? '')),
                'phone' => trim((string) ($contact['phone'] ?? '')),
            ])
            ->reject(fn (array $contact): bool => $contact['name'] === ''
                && $contact['email'] === ''
                && $contact['phone'] === '')
            ->values()
            ->all();

        return $attributes;
    }

    // ── Persistence ──────────────────────────────────────────────────────────

    /**
     * Create or update the vendor and record the change in its log.
     */
    public function save(): Vendor
    {
        $data = $this->validate();

        $attributes = [
            'name' => $data['name'],
            'country' => $this->nullable($data['country'] ?? null),
            'state_province' => $this->nullable($data['state_province'] ?? null),
            'city' => $this->nullable($data['city'] ?? null),
            'suburb_barangay' => $this->nullable($data['suburb_barangay'] ?? null),
            'postal_code' => $this->nullable($data['postal_code'] ?? null),
            'address_line_1' => $this->nullable($data['address_line_1'] ?? null),
            'address_line_2' => $this->nullable($data['address_line_2'] ?? null),
            'payment_terms' => $this->nullable($data['payment_terms'] ?? null),
            'contact_persons' => $data['contact_persons'] === [] ? null : $data['contact_persons'],
            'credit_amount' => Money::round($data['credit_amount']),
            'status' => $data['status'],
        ];

        if ($this->vendor === null) {
            $vendor = Vendor::create($attributes);
            $vendor->logCreated($this->nullable($data['update_remarks'] ?? null));

            return $vendor;
        }

        // Snapshot before the write so the log records the real diff.
        $before = $this->vendor->only(self::ATTRIBUTES);

        $this->vendor->update($attributes);
        $this->vendor->logUpdated($before, $attributes, $this->nullable($data['update_remarks'] ?? null));

        return $this->vendor;
    }

    private function nullable(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return ($value === null || $value === '') ? null : $value;
    }
}
