<?php

namespace App\Http\Controllers;

use App\Mail\SiteInvitationMail;
use App\Models\Site;
use App\Models\SiteInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class SiteCollaboratorController extends Controller
{
    public function index(Site $site): Response
    {
        $this->authorize('manageCollaborators', $site);

        $site->load('collaborators');

        return Inertia::render('sites/Collaborators', [
            'site' => $site,
        ]);
    }

    public function store(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('manageCollaborators', $site);

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'role' => ['sometimes', 'string', 'in:viewer,editor,admin'],
        ]);

        $email = $validated['email'];
        $role = $validated['role'] ?? 'editor';

        // Check if user is already owner (by email to avoid revealing if account exists)
        if (strtolower($site->user->email ?? '') === strtolower($email)) {
            return back()->withErrors(['email' => __('Dieser Nutzer ist bereits der Besitzer.')]);
        }

        // Check for existing collaborator by email
        $existingCollaborator = $site->collaborators()->where('email', $email)->first();
        if ($existingCollaborator) {
            return back()->withErrors(['email' => __('Dieser Nutzer ist bereits Mitbearbeiter.')]);
        }

        if ($site->invitations()->where('email', $email)->whereNull('accepted_at')->where('expires_at', '>', now())->exists()) {
            return back()->withErrors(['email' => __('Eine Einladung wurde bereits an diese E-Mail-Adresse gesendet.')]);
        }

        // Always create invitation and send email (do not reveal whether user exists)
        $invitation = $site->invitations()->create([
            'email' => $email,
            'token' => SiteInvitation::generateToken(),
            'role' => $role,
            'invited_by' => $request->user()->id,
            'expires_at' => now()->addDays(7),
        ]);

        Mail::to($email)->send(new SiteInvitationMail($invitation->load('inviter')));

        return back()->with('success', __('Einladung wurde gesendet. Der Eingeladene erhält eine E-Mail mit einem Link zur Annahme.'));
    }

    public function destroy(Site $site, User $user): RedirectResponse
    {
        $this->authorize('manageCollaborators', $site);

        $site->collaborators()->detach($user->id);

        return back()->with('success', __('Mitbearbeiter erfolgreich entfernt.'));
    }

    public function destroyInvitation(Site $site, SiteInvitation $invitation): RedirectResponse
    {
        $this->authorize('manageCollaborators', $site);

        if ($invitation->site_id !== $site->id) {
            abort(403);
        }

        $invitation->delete();

        return back()->with('success', __('Einladung erfolgreich gelöscht.'));
    }
}
