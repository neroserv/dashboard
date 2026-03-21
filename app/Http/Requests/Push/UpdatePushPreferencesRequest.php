<?php

namespace App\Http\Requests\Push;

use App\Models\EmailTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePushPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'master_enabled' => ['required', 'boolean'],
            'sync_with_email' => ['required', 'boolean'],
            'types' => ['nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $types = $this->input('types');
            if (! is_array($types)) {
                return;
            }
            $validKeys = EmailTemplate::query()->pluck('key')->all();
            foreach ($types as $key => $value) {
                if (! in_array($key, $validKeys, true)) {
                    $validator->errors()->add('types', "Unbekannter Benachrichtigungstyp: {$key}");

                    return;
                }
                if (! is_bool($value)) {
                    $validator->errors()->add("types.{$key}", 'Muss ein boolescher Wert sein.');

                    return;
                }
            }
        });
    }
}
