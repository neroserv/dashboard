<?php

namespace App\Http\Requests;

use App\Models\Brand;
use App\Models\GameServerAccount;
use Illuminate\Foundation\Http\FormRequest;

class ConnectGameServerDomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        $gameServerAccount = $this->route('game_server_account');

        $user = $this->user();

        if (! $gameServerAccount instanceof GameServerAccount || $user === null) {
            return false;
        }

        if (! $gameServerAccount->isCloudAccount()) {
            return false;
        }

        if (! $gameServerAccount->userCan($user, 'manage_servers')) {
            return false;
        }

        $allocation = is_array($gameServerAccount->allocation) ? $gameServerAccount->allocation : [];
        if (empty($allocation['subdomain'])) {
            return false;
        }

        $resellerDomainUuid = $this->input('reseller_domain_uuid');
        if (empty($resellerDomainUuid)) {
            return true;
        }

        $brand = $this->attributes->get('current_brand') ?? Brand::getDefault();
        $domain = $this->user()?->resellerDomainsForBrand($brand)->where('uuid', $resellerDomainUuid)->first();

        return $domain !== null;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reseller_domain_uuid' => ['required', 'string', 'exists:reseller_domains,uuid'],
            'subdomain' => ['required', 'string', 'max:63', 'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subdomain.regex' => 'Die Subdomain darf nur Kleinbuchstaben, Ziffern und Bindestriche enthalten.',
        ];
    }
}
