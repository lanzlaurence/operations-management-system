<?php

namespace App\Livewire\Forms;

use App\Enums\RecordStatus;
use App\Models\Material;
use App\Support\Money;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * The material form, shared by the create and edit screens.
 *
 * Two fields are deliberately absent: `avg_unit_cost` and `avg_unit_price`.
 * Those are derived from actual receipts and issues by MaterialCostingService,
 * so letting a form write them would put a number on screen that the next
 * movement silently overwrites. `unit_cost` and `unit_price` are the
 * maintained list values, and they are what this form owns.
 */
class MaterialForm extends Form
{
    public ?Material $material = null;

    // Identity
    public string $code = '';

    public string $sku = '';

    public string $name = '';

    public string $description = '';

    // Classification
    public string $brand_id = '';

    public string $category_id = '';

    public string $uom_id = '';

    // Pricing (list values)
    public string $unit_cost = '0';

    public string $unit_price = '0';

    // Stock levels
    public string $min_stock_level = '0';

    public string $max_stock_level = '0';

    public string $reorder_level = '0';

    // Dimensions
    public string $weight = '';

    public string $length = '';

    public string $width = '';

    public string $height = '';

    public string $volume = '';

    // Flags
    public bool $track_serial_number = false;

    public bool $track_batch_number = false;

    public string $status = RecordStatus::Active->value;

    /** Reason for the change, stored on the audit entry rather than the record. */
    public string $update_remarks = '';

    /**
     * Attributes that make up the record, in the order the log should read.
     *
     * @var array<int, string>
     */
    private const ATTRIBUTES = [
        'code', 'sku', 'name', 'description',
        'brand_id', 'category_id', 'uom_id',
        'unit_cost', 'unit_price',
        'min_stock_level', 'max_stock_level', 'reorder_level',
        'weight', 'length', 'width', 'height', 'volume',
        'track_serial_number', 'track_batch_number', 'status',
    ];

    public function setMaterial(Material $material): void
    {
        $this->material = $material;

        $this->code = $material->code;
        $this->sku = (string) $material->sku;
        $this->name = $material->name;
        $this->description = (string) $material->description;

        $this->brand_id = (string) $material->brand_id;
        $this->category_id = (string) $material->category_id;
        $this->uom_id = (string) $material->uom_id;

        $this->unit_cost = (string) Money::round($material->unit_cost);
        $this->unit_price = (string) Money::round($material->unit_price);

        $this->min_stock_level = (string) (int) $material->min_stock_level;
        $this->max_stock_level = (string) (int) $material->max_stock_level;
        $this->reorder_level = (string) (int) $material->reorder_level;

        $this->weight = $this->decimalOrBlank($material->weight);
        $this->length = $this->decimalOrBlank($material->length);
        $this->width = $this->decimalOrBlank($material->width);
        $this->height = $this->decimalOrBlank($material->height);
        $this->volume = $this->decimalOrBlank($material->volume);

        $this->track_serial_number = (bool) $material->track_serial_number;
        $this->track_batch_number = (bool) $material->track_batch_number;
        $this->status = $material->status->value;
    }

    // ── Validation ───────────────────────────────────────────────────────────

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // The code is assigned by the model on create, and editable after.
            'code' => [
                $this->material === null ? 'nullable' : 'required',
                'string',
                'max:255',
                Rule::unique('materials', 'code')
                    ->ignore($this->material?->id)
                    ->whereNull('deleted_at'),
            ],
            'sku' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('materials', 'sku')
                    ->ignore($this->material?->id)
                    ->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],

            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'uom_id' => ['nullable', 'integer', 'exists:uoms,id'],

            'unit_cost' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],

            'min_stock_level' => ['required', 'integer', 'min:0', 'max:99999999'],
            'max_stock_level' => [
                'required', 'integer', 'min:0', 'max:99999999',
                // Closure rules keep the cross-field checks in the rule set, so
                // the error lands on the field the user has to change.
                function (string $attribute, mixed $value, callable $fail): void {
                    if ((int) $value > 0 && (int) $value < (int) $this->min_stock_level) {
                        $fail('The maximum stock level cannot be lower than the minimum.');
                    }
                },
            ],
            'reorder_level' => [
                'required', 'integer', 'min:0', 'max:99999999',
                function (string $attribute, mixed $value, callable $fail): void {
                    $max = (int) $this->max_stock_level;

                    if ($max > 0 && (int) $value > $max) {
                        $fail('The reorder level cannot be above the maximum stock level.');
                    }
                },
            ],

            'weight' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'length' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'width' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'height' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'volume' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],

            'track_serial_number' => ['boolean'],
            'track_batch_number' => ['boolean'],
            'status' => ['required', RecordStatus::rule()],

            'update_remarks' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Enter the material name.',
            'code.required' => 'Enter the material code.',
            'code.unique' => 'That material code is already in use.',
            'sku.unique' => 'That SKU is already in use.',
            'unit_cost.required' => 'Enter the list unit cost, or 0 if unknown.',
            'unit_price.required' => 'Enter the list selling price, or 0 if unknown.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'brand_id' => 'brand',
            'category_id' => 'category',
            'uom_id' => 'unit of measurement',
            'unit_cost' => 'unit cost',
            'unit_price' => 'unit price',
            'min_stock_level' => 'minimum stock level',
            'max_stock_level' => 'maximum stock level',
            'reorder_level' => 'reorder level',
            'update_remarks' => 'reason for the change',
        ];
    }

    protected function prepareForValidation($attributes)
    {
        foreach (['code', 'sku', 'name', 'description', 'update_remarks'] as $field) {
            if (isset($attributes[$field]) && is_string($attributes[$field])) {
                $attributes[$field] = trim($attributes[$field]);
            }
        }

        $attributes['code'] = strtoupper((string) ($attributes['code'] ?? ''));
        $attributes['sku'] = strtoupper((string) ($attributes['sku'] ?? ''));

        // Empty selects and blank dimensions are nulls, not zeroes or ''.
        foreach (['brand_id', 'category_id', 'uom_id', 'weight', 'length', 'width', 'height', 'volume'] as $field) {
            if (($attributes[$field] ?? '') === '') {
                $attributes[$field] = null;
            }
        }

        return $attributes;
    }

    // ── Persistence ──────────────────────────────────────────────────────────

    public function save(): Material
    {
        $data = $this->validate();

        $attributes = [
            'sku' => $this->nullable($data['sku'] ?? null),
            'name' => $data['name'],
            'description' => $this->nullable($data['description'] ?? null),
            'brand_id' => $data['brand_id'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'uom_id' => $data['uom_id'] ?? null,
            'unit_cost' => Money::round($data['unit_cost']),
            'unit_price' => Money::round($data['unit_price']),
            'min_stock_level' => (int) $data['min_stock_level'],
            'max_stock_level' => (int) $data['max_stock_level'],
            'reorder_level' => (int) $data['reorder_level'],
            'weight' => $data['weight'] ?? null,
            'length' => $data['length'] ?? null,
            'width' => $data['width'] ?? null,
            'height' => $data['height'] ?? null,
            'volume' => $data['volume'] ?? null,
            'track_serial_number' => (bool) $data['track_serial_number'],
            'track_batch_number' => (bool) $data['track_batch_number'],
            'status' => $data['status'],
        ];

        if ($this->material === null) {
            // The code is generated by the model unless one was supplied.
            $material = Material::create([
                ...$attributes,
                'code' => $this->nullable($data['code'] ?? null),
            ]);

            $material->logCreated($this->nullable($data['update_remarks'] ?? null));

            return $material;
        }

        $before = $this->material->only(self::ATTRIBUTES);
        $attributes['code'] = $data['code'];

        $this->material->update($attributes);
        $this->material->logUpdated($before, $attributes, $this->nullable($data['update_remarks'] ?? null));

        return $this->material;
    }

    private function nullable(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return ($value === null || $value === '') ? null : $value;
    }

    private function decimalOrBlank(mixed $value): string
    {
        return ($value === null || $value === '') ? '' : (string) Money::round($value);
    }
}
