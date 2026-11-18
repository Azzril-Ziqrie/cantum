<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ModifierGroupRequest extends FormRequest
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
            'required' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['required' => $this->boolean('required')]);
    }
}
