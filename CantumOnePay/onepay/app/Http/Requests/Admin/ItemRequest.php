<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ItemRequest extends FormRequest
{
    /** Hak akses sebenarnya dicek di MenuAdminService, per aksi dan per cabang. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'integer', 'min:0', 'max:100000000'],
            'cost' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'station' => ['required', 'in:kitchen,bar'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_best_seller' => ['boolean'],
            'is_active' => ['boolean'],
            'is_bundle' => ['boolean'],
            'available_from' => ['nullable', 'date_format:H:i', 'required_with:available_until'],
            'available_until' => ['nullable', 'date_format:H:i', 'required_with:available_from'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_photo' => ['boolean'],
            'components' => ['nullable', 'array', 'max:20'],
            'components.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'components.*.qty' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $flags = ['is_best_seller', 'is_active', 'is_bundle', 'remove_photo'];
        $this->merge(array_combine($flags, array_map(fn ($flag) => $this->boolean($flag), $flags)));
    }

    /** Atribut item tanpa foto dan isi paket. */
    public function itemAttributes(): array
    {
        return collect($this->validated())->except(['photo', 'components'])->merge(['cost' => (int) $this->input('cost', 0), 'sort_order' => (int) $this->input('sort_order', 0)])->all();
    }

    public function components(): array
    {
        return array_values((array) $this->validated('components', []));
    }
}
