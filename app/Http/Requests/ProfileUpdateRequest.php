<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($this->user()->id)],
            'dial_code' => ['required'],
            'phone' => ['required', 'max:255'],
            'phone_number' => ['required', 'max:255', Rule::unique(User::class)->ignore($this->user()->id)],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => trans('public.your_name'),
            'email' => trans('public.email'),
            'dial_code' => trans('public.phone_code'),
            'phone' => trans('public.phone'),
            'phone_number' => trans('public.phone_number'),
        ];
    }
}
