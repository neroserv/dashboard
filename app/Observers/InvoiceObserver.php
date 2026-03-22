<?php

namespace App\Observers;

use App\Jobs\SyncInvoiceToInvoiceNinjaJob;
use App\Models\Invoice;

class InvoiceObserver
{
    /**
     * New invoice row (line items may not exist yet — then InvoiceLineItemObserver dispatches).
     */
    public function created(Invoice $invoice): void
    {
        if (! $invoice->lineItems()->exists()) {
            return;
        }

        SyncInvoiceToInvoiceNinjaJob::dispatch($invoice->id);
    }

    public function updated(Invoice $invoice): void
    {
        if (! $invoice->lineItems()->exists()) {
            return;
        }

        if ($this->hasRelevantAttributeChange($invoice)) {
            SyncInvoiceToInvoiceNinjaJob::dispatch($invoice->id);
        }
    }

    protected function hasRelevantAttributeChange(Invoice $invoice): bool
    {
        $relevant = [
            'status',
            'amount',
            'invoice_date',
            'due_date',
            'number',
            'user_id',
            'tax',
            'type',
            'site_subscription_id',
            'mollie_payment_id',
            'uuid',
        ];

        foreach ($relevant as $attr) {
            if ($invoice->wasChanged($attr)) {
                return true;
            }
        }

        if ($this->metadataMeaningfullyChanged($invoice)) {
            return true;
        }

        return false;
    }

    /**
     * Ignore Invoice Ninja sync keys so internal metadata writes do not re-trigger sync.
     */
    protected function metadataMeaningfullyChanged(Invoice $invoice): bool
    {
        if (! $invoice->wasChanged('metadata')) {
            return false;
        }

        $old = $this->normalizedMetadataForCompare($invoice->getOriginal('metadata'));
        $new = $this->normalizedMetadataForCompare($invoice->getAttributes()['metadata'] ?? $invoice->metadata);

        return $old !== $new;
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizedMetadataForCompare(mixed $metadata): array
    {
        if (! is_array($metadata)) {
            return [];
        }

        unset($metadata['invoice_ninja_invoice_id'], $metadata['invoice_ninja_synced_at']);
        ksort($metadata);

        return $metadata;
    }
}
