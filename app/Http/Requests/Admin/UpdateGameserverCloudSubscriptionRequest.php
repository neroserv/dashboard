<?php

namespace App\Http\Requests\Admin;

use App\Models\GameserverCloudPlan;
use App\Models\GameserverCloudSubscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateGameserverCloudSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'gameserver_cloud_plan_id' => [
                'required_if:plan_display_user_defined,false',
                'nullable',
                'integer',
                'exists:gameserver_cloud_plans,id',
            ],
            'plan_display_user_defined' => ['nullable', 'boolean'],
            'custom_max_cpu' => ['nullable', 'integer', 'min:0'],
            'custom_max_memory_mb' => ['nullable', 'integer', 'min:0'],
            'custom_max_disk_gb' => ['nullable', 'integer', 'min:0'],
            'custom_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $subscription = $this->route('subscription');
            if (! $subscription instanceof GameserverCloudSubscription) {
                return;
            }

            $planDisplayUserDefined = $this->boolean('plan_display_user_defined');
            if ($planDisplayUserDefined) {
                return;
            }

            $newPlanId = (int) $this->input('gameserver_cloud_plan_id');
            if ($newPlanId < 1) {
                return;
            }

            $newPlan = GameserverCloudPlan::find($newPlanId);
            if (! $newPlan) {
                return;
            }

            $currentBrandId = $subscription->gameserverCloudPlan?->brand_id;
            if ($currentBrandId !== null && $newPlan->brand_id !== $currentBrandId) {
                $validator->errors()->add(
                    'gameserver_cloud_plan_id',
                    'Der gewählte Plan gehört nicht zur gleichen Marke wie das Abo.'
                );
            }
        });
    }
}
