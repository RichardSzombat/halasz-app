<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorksheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->filter(fn ($item) => filled(data_get($item, 'billable_item_id')))
            ->map(fn ($item) => [
                'worksheet_item_id' => data_get($item, 'worksheet_item_id'),
                'billable_item_id' => data_get($item, 'billable_item_id'),
                'quantity' => data_get($item, 'quantity', 1),
            ])
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
            'items.*.worksheet_item_id' => ['nullable', 'integer', Rule::exists('worksheet_items', 'id')],
            'items.*.billable_item_id' => ['required', 'integer', Rule::exists('billable_items', 'id')],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ];
    }

    public function attributes(): array
    {
        return [
            'worksheet_number' => 'munkalap száma',
            'work_date' => 'dátum',
            'note' => 'megjegyzés',
            'items' => 'elvégzett tételek',
            'items.*.billable_item_id' => 'tétel',
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
}
