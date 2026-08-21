<?php

namespace App\Livewire\Vendors;

use App\Enums\RecordStatus;
use App\Livewire\Forms\VendorForm;
use App\Livewire\Support\MasterForm;
use App\Models\Preference;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Livewire\Form as LivewireForm;

/**
 * Create and edit screen for a vendor.
 *
 * Adds two things to the shared base: the contact-person repeater, and the
 * "reason for the change" note that is stored on the audit entry when an
 * existing vendor is edited.
 */
class Form extends MasterForm
{
    public VendorForm $form;

    public ?Vendor $vendor = null;

    public function mount(?Vendor $vendor = null): void
    {
        if ($vendor?->exists) {
            $this->vendor = $vendor;
            $this->form->setVendor($vendor);
        }
    }

    protected function formObject(): LivewireForm
    {
        return $this->form;
    }

    protected function record(): ?Model
    {
        return $this->vendor;
    }

    protected function indexRoute(): string
    {
        return 'vendors.index';
    }

    protected function label(): string
    {
        return 'Vendor';
    }

    protected function view(): string
    {
        return 'livewire.vendors.form';
    }

    protected function title(): string
    {
        return $this->isEditing()
            ? "Edit {$this->vendor->code} — {$this->vendor->name}"
            : 'Create Vendor';
    }

    // ── Contact persons ──────────────────────────────────────────────────────

    public function addContactPerson(): void
    {
        $this->form->addContactPerson();
    }

    public function removeContactPerson(int $index): void
    {
        $this->form->removeContactPerson($index);
        $this->resetValidation("form.contact_persons.{$index}");
    }

    /**
     * Options and display settings the form needs.
     *
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return [
            'statuses' => RecordStatus::options(),
            'currency' => Preference::get('currency', 'PHP'),
            'paymentTerms' => ['Cash on Delivery', 'Net 7', 'Net 15', 'Net 30', 'Net 45', 'Net 60', 'Net 90'],
        ];
    }
}
