<?php

namespace App\Http\Controllers;

use App\Models\ProductInvitation;
use App\Models\ProductShare;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductInvitationAcceptController extends Controller
{
    /**
     * Accept a product invitation by token.
     * Guests are redirected to login with intended URL back here.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $token = $request->query('token');
        if (! $token || ! is_string($token)) {
            return redirect()->route('dashboard')
                ->with('error', __('Einladungslink ungültig oder abgelaufen.'));
        }

        $invitation = ProductInvitation::query()
            ->where('token', $token)
            ->with('shareable')
            ->first();

        if (! $invitation) {
            return redirect()->route('dashboard')
                ->with('error', __('Einladung nicht gefunden oder abgelaufen.'));
        }

        if ($invitation->isAccepted()) {
            $url = $invitation->getShareableShowUrl();

            return redirect($url ?? route('dashboard'))
                ->with('info', __('Sie haben diese Einladung bereits angenommen.'));
        }

        if ($invitation->isExpired()) {
            return redirect()->route('dashboard')
                ->with('error', __('Diese Einladung ist abgelaufen.'));
        }

        if (! $request->user()) {
            session()->put('url.intended', route('product-invitations.accept', ['token' => $token]));

            return redirect()->route('login')
                ->with('info', __('Bitte melden Sie sich an, um die Einladung anzunehmen.'));
        }

        $user = $request->user();
        if (strtolower($invitation->email) !== strtolower($user->email)) {
            return redirect()->route('dashboard')
                ->with('error', __('Diese Einladung wurde an eine andere E-Mail-Adresse gesendet. Bitte verwenden Sie das Konto mit :email.', ['email' => $invitation->email]));
        }

        $shareable = $invitation->shareable;
        if (! $shareable) {
            return redirect()->route('dashboard')
                ->with('error', __('Das eingeladene Produkt existiert nicht mehr.'));
        }

        if (ProductShare::query()
            ->where('shareable_type', $invitation->shareable_type)
            ->where('shareable_id', $invitation->shareable_id)
            ->where('user_id', $user->id)
            ->exists()) {
            $url = $invitation->getShareableShowUrl();

            return redirect($url ?? route('dashboard'))
                ->with('info', __('Sie haben bereits Zugriff auf dieses Produkt.'));
        }

        ProductShare::query()->create([
            'shareable_type' => $invitation->shareable_type,
            'shareable_id' => $invitation->shareable_id,
            'user_id' => $user->id,
            'permissions' => $invitation->permissions ?? [],
            'invited_by' => $invitation->invited_by,
            'accepted_at' => now(),
        ]);

        $invitation->update(['accepted_at' => now()]);

        $url = $invitation->getShareableShowUrl();

        return redirect($url ?? route('dashboard'))
            ->with('success', __('Einladung angenommen. Sie haben jetzt Zugriff auf das Produkt.'));
    }
}
