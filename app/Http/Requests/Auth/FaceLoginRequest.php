<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class FaceLoginRequest extends FormRequest
{
    protected array $fields = ['id_number', 'id_type']; // You can override this via constructor or config

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'base64img' => ['required', 'string'],
        ];

        foreach ($this->fields as $field) {
            $rules[$field] = config("sss-acop.field_rules.$field", ['required']);
        }

        return $rules;
    }

    public function prepareForValidation(): void
    {
        // Optionally allow dynamic override from controller
        if ($this->has('fields') && is_array($this->input('fields'))) {
            $this->fields = $this->input('fields');
        }
    }
}
