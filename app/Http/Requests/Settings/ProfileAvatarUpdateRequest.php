<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileAvatarUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'avatar' => ['required', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'avatar.required' => 'Bitte wählen Sie eine Bilddatei aus.',
            'avatar.image' => 'Die Datei muss ein Bild sein.',
            'avatar.mimes' => 'Erlaubt sind JPEG, PNG, GIF oder WebP.',
            'avatar.max' => 'Das Bild darf maximal 2 MB groß sein.',
        ];
    }
}
