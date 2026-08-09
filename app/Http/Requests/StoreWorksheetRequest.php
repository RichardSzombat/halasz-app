<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreWorksheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->filter(fn ($item) => filled(data_get($item, 'billable_item_id'))
                || filled(data_get($item, 'custom_name'))
                || filled(data_get($item, 'custom_price')))
            ->map(function ($item): array {
                $billableItemId = data_get($item, 'billable_item_id');
                $type = filled($billableItemId) ? 'catalog' : 'custom';

                return [
                    'type' => $type,
                    'worksheet_item_id' => data_get($item, 'worksheet_item_id'),
                    'billable_item_id' => $type === 'catalog' ? $billableItemId : null,
                    'custom_name' => $this->normalizeStringInput(data_get($item, 'custom_name')),
                    'custom_price' => $this->normalizeIntegerInput(data_get($item, 'custom_price')),
                    'quantity' => $this->normalizeIntegerInput(data_get($item, 'quantity', 1)),
                ];
            })
            ->values()
            ->all();

        $workDate = $this->normalizeDateInput($this->input('work_date'));

        $this->merge([
            'items' => $items,
            'work_date' => $workDate,
        ]);
    }

    public function rules(): array
    {
        return [
            'worksheet_number' => ['required', 'string', 'max:255'],
            'work_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.type' => ['required', 'string', Rule::in(['catalog', 'custom'])],
            'items.*.worksheet_item_id' => ['nullable', 'integer', Rule::exists('worksheet_items', 'id')],
            'items.*.billable_item_id' => ['nullable', 'integer', Rule::exists('billable_items', 'id')],
            'items.*.custom_name' => ['nullable', 'string', 'max:255'],
            'items.*.custom_price' => ['nullable', 'integer', 'min:1', 'max:999999999'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ($this->input('items', []) as $index => $item) {
                if (data_get($item, 'type') === 'catalog' && blank(data_get($item, 'billable_item_id'))) {
                    $validator->errors()->add("items.{$index}.billable_item_id", 'A tétel megadása kötelező.');
                }

                if (data_get($item, 'type') !== 'custom') {
                    continue;
                }

                if (blank(data_get($item, 'custom_name'))) {
                    $validator->errors()->add("items.{$index}.custom_name", 'Az egyedi tétel nevének megadása kötelező.');
                }

                if (blank(data_get($item, 'custom_price'))) {
                    $validator->errors()->add("items.{$index}.custom_price", 'Az egyedi tétel összegének megadása kötelező.');
                }
            }
        });
    }

    public function attributes(): array
    {
        return [
            'worksheet_number' => 'munkalap száma',
            'work_date' => 'dátum',
            'note' => 'megjegyzés',
            'items' => 'elvégzett tételek',
            'items.*.billable_item_id' => 'tétel',
            'items.*.custom_name' => 'egyedi tétel neve',
            'items.*.custom_price' => 'egyedi tétel összege',
            'items.*.quantity' => 'darabszám',
        ];
    }

    private function normalizeDateInput(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return Carbon::createFromFormat('Y-m-d', $value)->toDateString();
        }

        if (preg_match('/^\d{4}\.\d{2}\.\d{2}$/', $value)) {
            return Carbon::createFromFormat('Y.m.d', $value)->toDateString();
        }

        return $value;
    }

    private function normalizeStringInput(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function normalizeIntegerInput(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return preg_replace('/\s+/', '', $value);
    }
}
