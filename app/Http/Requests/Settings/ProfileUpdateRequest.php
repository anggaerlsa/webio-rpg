<?php

namespace App\Http\Requests\Settings;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
        ];

        // Gelar (job) = nama-meta yang ditampilkan di Panel Dewa (mis. "Dewa Hujan").
        // Hanya superadmin yang boleh mengubahnya; untuk pemain, job dikendalikan game
        // (Commoner/adventurer/merchant) sehingga tidak diizinkan diedit di sini.
        if ($this->user()->isSuperadmin()) {
            $rules['job'] = ['required', 'string', 'max:60'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.unique' => 'Email sudah dipakai akun lain.',
            'job.required' => 'Gelar wajib diisi.',
            'job.max' => 'Gelar maksimal 60 karakter.',
        ];
    }
}
