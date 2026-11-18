<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ModifierRequest extends FormRequest
{
    /** Hak akses sebenarnya dicek di MenuAdminService, per aksi dan per cabang. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'price_delta' => ['required', 'integer', 'min:-10000000', 'max:10000000'],
        ];
    }

}
