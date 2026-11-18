<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BulkPriceRequest extends FormRequest
{
    /** Hak akses sebenarnya dicek di MenuAdminService, per aksi dan per cabang. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'exists:categories,id'],
            'mode' => ['required', 'in:percent,amount'],
            'value' => ['required', 'integer', 'between:-100000000,100000000'],
        ];
    }

}
