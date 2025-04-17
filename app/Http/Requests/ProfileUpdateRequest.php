<?php

namespace App\Http\Requests;


use Illuminate\Foundation\Http\FormRequest;
use Propaganistas\LaravelPhone\Rules\Phone;
use Illuminate\Validation\Rule;
use App\Models\User;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'mobile' => [
                'required',
                (new Phone)->type('mobile')->country('PH'),
                Rule::unique(User::class)->ignore($this->user()->id)],
            'photo' => ['nullable', 'image', 'max:2048'], // ✅ added
        ];
    }
}
