<?php

namespace App\Http\Controllers;

use App\Models\SiteInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SiteInvitationAcceptController extends Controller
{
    /**
     * Accept a site invitation by token.
     * Guests are redirected to login with intended URL back here.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $token = $request->query('token');
        if (! $token || ! is_string($token)) {
            return redirect()->route('dashboard')
                ->with('error', __('Einladungslink ungültig oder abgelaufen.'));
        }

        $invitation = SiteInvitation::query()
            ->where('token', $token)
            ->with('site')
            ->first();

        if (! $invitation) {
            return redirect()->route('dashboard')
                ->with('error', __('Einladung nicht gefunden oder abgelaufen.'));
        }

        if ($invitation->isAccepted()) {
            return redirect()->route('sites.show', $invitation->site)
                ->with('info', __('Sie haben diese Einladung bereits angenommen.'));
        }

        if ($invitation->isExpired()) {
            return redirect()->route('dashboard')
                ->with('error', __('Diese Einladung ist abgelaufen.'));
        }

        if (! $request->user()) {
            session()->put('url.intended', route('site-invitations.accept', ['token' => $token]));

            return redirect()->route('login')
                ->with('info', __('Bitte melden Sie sich an, um die Einladung anzunehmen.'));
        }

        $user = $request->user();
        if (strtolower($invitation->email) !== strtolower($user->email)) {
            return redirect()->route('dashboard')
                ->with('error', __('Diese Einladung wurde an eine andere E-Mail-Adresse gesendet. Bitte verwenden Sie das Konto mit :email.', ['email' => $invitation->email]));
        }

        $site = $invitation->site;
        if (! $site) {
            return redirect()->route('dashboard')
                ->with('error', __('Die eingeladene Site existiert nicht mehr.'));
        }

        if ($site->collaborators()->where('user_id', $user->id)->exists()) {
            return redirect()->route('sites.show', $site)
                ->with('info', __('Sie haben bereits Zugriff auf diese Site.'));
        }

        $site->collaborators()->attach($user->id, [
            'invited_by' => $invitation->invited_by,
            'invited_at' => now(),
        ]);

        $invitation->update(['accepted_at' => now()]);

        return redirect()->route('sites.show', $site)
            ->with('success', __('Einladung angenommen. Sie haben jetzt Zugriff auf die Site.'));
    }
}
