<?php

namespace App\Http\Requests;

use App\Models\Discount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->role->canManageMenu();
    }

    public function rules(): array
    {
        $current = $this->route('discount');
        $isPercent = $this->input('type') === Discount::TYPE_PERCENT;

        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('discounts', 'code')->ignore($current)],
            'type' => ['required', Rule::in([Discount::TYPE_PERCENT, Discount::TYPE_FIXED])],
            'value' => ['required', 'integer', 'min:1', $isPercent ? 'max:100' : 'max:10000000'],
            'max_discount' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'scope' => ['required', Rule::in([Discount::SCOPE_ORDER, Discount::SCOPE_ITEMS, Discount::SCOPE_CATEGORIES])],
            'target_ids' => ['nullable', 'array', 'required_unless:scope,' . Discount::SCOPE_ORDER],
            'target_ids.*' => ['integer'],
            'min_spend' => ['nullable', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'days' => ['nullable', 'array'],
            'days.*' => ['integer', 'between:1,7'],
            'time_from' => ['nullable', 'date_format:H:i', 'required_with:time_until'],
            'time_until' => ['nullable', 'date_format:H:i', 'required_with:time_from'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'all_branches' => ['nullable', 'boolean'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'Kode hanya boleh huruf, angka, strip, atau garis bawah.',
            'code.unique' => 'Kode ini sudah dipakai promo lain.',
            'target_ids.required_unless' => 'Pilih minimal satu menu atau kategori.',
            'ends_at.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'value.max' => 'Nilai terlalu besar.',
        ];
    }

    /** Data siap simpan: kotak centang yang tidak dicentang menjadi false. */
    public function discountData(): array
    {
        return array_merge($this->validated(), [
            'all_branches' => $this->boolean('all_branches'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
