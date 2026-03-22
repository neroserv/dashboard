<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\InvoiceNinjaSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncInvoiceToInvoiceNinjaJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $invoiceId) {}

    public function handle(InvoiceNinjaSyncService $invoiceNinjaSyncService): void
    {
        $invoice = Invoice::query()->find($this->invoiceId);
        if ($invoice === null) {
            return;
        }

        try {
            $invoiceNinjaSyncService->sync($invoice);
        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
