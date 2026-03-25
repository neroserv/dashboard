<?php

namespace App\Models;

use App\Models\Concerns\HasProductShares;
use App\Support\DomainRegistrar;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ResellerDomain extends Model
{
    use HasFactory, HasProductShares;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'brand_id',
        'registrar',
        'is_sandbox',
        'domain',
        'user_id',
        'skrime_id',
        'realtimeregister_domain_name',
        'status',
        'registered_at',
        'expires_at',
        'auto_renew',
        'purchase_price',
        'sale_price',
        'tld',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_sandbox' => 'boolean',
            'registered_at' => 'date',
            'expires_at' => 'date',
            'auto_renew' => 'boolean',
            'purchase_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Brand, ResellerDomain>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function profitMargin(): float
    {
        $purchase = (float) $this->purchase_price;
        $sale = (float) $this->sale_price;
        if ($purchase <= 0) {
            return 0;
        }

        return round($sale - $purchase, 2);
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    /**
     * Realtime Register may set the domain to a pending validation state until the registrant confirms via e-mail.
     */
    public function isRealtimeRegisterPendingValidation(): bool
    {
        if (($this->registrar ?? '') !== DomainRegistrar::REALTIME_REGISTER) {
            return false;
        }

        return (bool) preg_match('/pending[\s_-]*validation|pendingvalidation/i', (string) $this->status);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted(): void
    {
        static::creating(function (ResellerDomain $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
}
