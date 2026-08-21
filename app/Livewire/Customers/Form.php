<?php

namespace App\Livewire\Customers;

use App\Enums\RecordStatus;
use App\Livewire\Forms\CustomerForm;
use App\Livewire\Support\MasterForm;
use App\Models\Customer;
use App\Models\Preference;
use Illuminate\Database\Eloquent\Model;
use Livewire\Form as LivewireForm;

/**
 * Create and edit screen for a customer.
 *
 * Adds two things to the shared base: the contact-person repeater, and the
 * "reason for the change" note that is stored on the audit entry when an
 * existing customer is edited.
 */
class Form extends MasterForm
{
    public CustomerForm $form;

    public ?Customer $customer = null;

    public function mount(?Customer $customer = null): void
    {
        if ($customer?->exists) {
            $this->customer = $customer;
            $this->form->setCustomer($customer);
        }
    }

    protected function formObject(): LivewireForm
    {
        return $this->form;
    }

    protected function record(): ?Model
    {
        return $this->customer;
    }

    protected function indexRoute(): string
    {
        return 'customers.index';
    }

    protected function label(): string
    {
        return 'Customer';
    }

    protected function view(): string
    {
        return 'livewire.customers.form';
    }

    protected function title(): string
    {
        return $this->isEditing()
            ? "Edit {$this->customer->code} — {$this->customer->name}"
            : 'Create Customer';
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
