<?php

namespace App\Observers;

use App\Jobs\SyncInvoiceToInvoiceNinjaJob;
use App\Models\InvoiceLineItem;

class InvoiceLineItemObserver
{
    public function saved(InvoiceLineItem $invoiceLineItem): void
    {
        SyncInvoiceToInvoiceNinjaJob::dispatch($invoiceLineItem->invoice_id);
    }

    public function deleted(InvoiceLineItem $invoiceLineItem): void
    {
        SyncInvoiceToInvoiceNinjaJob::dispatch($invoiceLineItem->invoice_id);
    }
}
