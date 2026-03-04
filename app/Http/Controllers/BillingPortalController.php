<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BillingPortalController extends Controller
{
    /**
     * Mollie has no 1:1 billing portal; redirect to billing index.
     * Payment method is managed via Mollie checkout when starting a new subscription or first payment.
     */
    public function redirect(Request $request): RedirectResponse
    {
        $user = $request->user();
        $returnUrl = route('billing.index');

        if (! $user->hasMollieCustomerId()) {
            return redirect()
                ->to($returnUrl)
                ->with('info', 'Sie haben noch keine Zahlungsmethode hinterlegt. Diese wird beim ersten Abo-Abschluss angelegt.');
        }

        return redirect()
            ->to($returnUrl)
            ->with('info', 'Zahlungsmethode und Abos verwalten Sie hier. Bei der nächsten Zahlung können Sie die Methode bei Mollie anpassen.');
    }
}
