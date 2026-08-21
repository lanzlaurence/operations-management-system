<?php

namespace App\Livewire\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Form;

/**
 * Base create/edit screen for master data.
 *
 * One component serves both routes: with a record bound it edits, without one
 * it creates. Validation, the redirect, the flash message and the
 * "save and add another" flow are the same everywhere, so they live here.
 *
 * A subclass declares its typed form property (Livewire needs the concrete
 * class to hydrate it), binds the record in `mount()`, and answers:
 *
 *     protected function formObject(): Form   // return $this->form
 *     protected function record(): ?Model
 *     protected function indexRoute(): string // 'brands.index'
 *     protected function label(): string      // 'Brand'
 *     protected function view(): string       // 'livewire.brands.form'
 */
#[Layout('components.layouts.app')]
abstract class MasterForm extends Component
{
    abstract protected function formObject(): Form;

    /**
     * The record being edited, or null when creating.
     */
    abstract protected function record(): ?Model;

    /**
     * Route to return to after a successful save.
     */
    abstract protected function indexRoute(): string;

    /**
     * Singular human name used in titles and messages.
     */
    abstract protected function label(): string;

    abstract protected function view(): string;

    public function isEditing(): bool
    {
        return $this->record() !== null;
    }

    /**
     * Validate a single field as the user leaves it, so mistakes surface
     * next to the input instead of on submit.
     */
    public function updated(string $property): void
    {
        $this->validateOnly($property);
    }

    public function save(): void
    {
        $editing = $this->isEditing();
        $record = $this->formObject()->save();

        session()->flash('success', sprintf(
            '%s %s %s.',
            $this->label(),
            $this->recordName($record),
            $editing ? 'updated' : 'created',
        ));

        $this->redirectRoute($this->indexRoute(), navigate: true);
    }

    /**
     * Save and clear the form - the usual pattern when someone is entering a
     * batch of master data.
     */
    public function saveAndAddAnother(): void
    {
        $record = $this->formObject()->save();

        $this->formObject()->reset();
        $this->resetValidation();

        $this->dispatch('toast', type: 'success', message: sprintf(
            '%s %s created.',
            $this->label(),
            $this->recordName($record),
        ));
    }

    public function render(): View
    {
        return view($this->view())->title($this->title());
    }

    protected function title(): string
    {
        return $this->isEditing()
            ? 'Edit '.$this->recordName($this->record())
            : 'Create '.$this->label();
    }

    /**
     * How a saved record is referred to in messages: its code when it has one,
     * otherwise its name.
     */
    protected function recordName(?Model $record): string
    {
        if ($record === null) {
            return '';
        }

        return (string) ($record->getAttribute('code') ?? $record->getAttribute('name') ?? $record->getKey());
    }
}
