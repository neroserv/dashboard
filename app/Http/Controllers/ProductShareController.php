<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductInvitationRequest;
use App\Http\Requests\UpdateProductShareRequest;
use App\Mail\TransactionalTemplateMail;
use App\Models\GameServerAccount;
use App\Models\GameserverCloudSubscription;
use App\Models\ProductInvitation;
use App\Models\ProductShare;
use App\Models\ResellerDomain;
use App\Models\TeamSpeakServerAccount;
use App\Models\WebspaceAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class ProductShareController extends Controller
{
    /**
     * Whether the share row refers to the billing/owner user of the shareable (must not be edited or removed via this API).
     */
    protected function shareTargetsProductOwner(object $shareable, ProductShare $share): bool
    {
        $ownerUserId = $shareable->user_id ?? null;
        if ($ownerUserId === null) {
            return false;
        }

        return (int) $share->user_id === (int) $ownerUserId;
    }

    /**
     * Allowed permission keys for the given shareable type.
     *
     * @return list<string>
     */
    protected function allowedPermissions(object $shareable): array
    {
        $allowed = config('product-share-permissions', []);

        return $allowed[get_class($shareable)] ?? ['view'];
    }

    /**
     * Store a new invitation (always send email; do not reveal if user exists).
     */
    public function storeInvitation(StoreProductInvitationRequest $request, WebspaceAccount|GameServerAccount|TeamSpeakServerAccount|GameserverCloudSubscription|ResellerDomain $shareable): RedirectResponse
    {
        return $this->storeInvitationForShareable($request, $shareable);
    }

    /**
     * @internal Used by route-specific wrappers so Laravel can resolve the model by parameter name.
     *
     * @param  WebspaceAccount|GameServerAccount|TeamSpeakServerAccount|GameserverCloudSubscription|ResellerDomain  $shareable
     */
    private function storeInvitationForShareable(StoreProductInvitationRequest $request, object $shareable): RedirectResponse
    {
        $this->authorize('manageCollaborators', $shareable);

        $allowed = $this->allowedPermissions($shareable);
        $permissions = array_values(array_intersect(
            (array) $request->validated('permissions'),
            $allowed
        ));
        if ($permissions === []) {
            throw ValidationException::withMessages([
                'permissions' => [__('Bitte wählen Sie mindestens eine gültige Berechtigung.')],
            ]);
        }

        $email = $request->validated('email');

        if ($shareable->isOwnedBy($request->user())) {
            $ownerEmail = $shareable->user->email ?? '';
            if (strtolower($ownerEmail) === strtolower($email)) {
                return back()->withErrors(['email' => __('Sie können sich nicht selbst einladen.')]);
            }
        }

        $existingInvitation = $shareable->productInvitations()
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->exists();
        if ($existingInvitation) {
            return back()->withErrors(['email' => __('Eine Einladung an diese E-Mail-Adresse wurde bereits gesendet.')]);
        }

        $invitation = $shareable->productInvitations()->create([
            'email' => $email,
            'token' => ProductInvitation::generateToken(),
            'permissions' => $permissions,
            'invited_by' => $request->user()->id,
            'expires_at' => now()->addDays(7),
        ]);

        $invitation->load(['inviter', 'inviter.brand']);
        $inviterName = $invitation->inviter?->name ?? config('app.name');
        $typeLabel = $invitation->getShareableTypeLabel();
        $productName = $invitation->getShareableDisplayName();
        $permissionLabels = $invitation->getPermissionLabelsForDisplay();
        $acceptUrl = route('product-invitations.accept', ['token' => $invitation->token]);

        $content = [
            'subject' => __('Einladung: Zugriff auf :type ":name"', ['type' => $typeLabel, 'name' => $productName]),
            'greeting' => __('Hallo,'),
            'body' => __(
                ':inviter hat Sie eingeladen, auf folgenden Produktzugriff zu erhalten:',
                ['inviter' => $inviterName]
            )."\n\n**{$typeLabel}: {$productName}**\n\n"
            .__('Berechtigungen:')." {$permissionLabels}\n\n"
            .__('Diese Einladung ist 7 Tage gültig. Sie müssen eingeloggt sein, um die Einladung anzunehmen. Falls Sie noch kein Konto haben, registrieren Sie sich zuerst und öffnen Sie danach den Link erneut.')
            ."\n\n"
            .__('Falls Sie diese Einladung nicht erwartet haben, können Sie diese E-Mail ignorieren.'),
            'action_text' => __('Einladung annehmen'),
        ];

        Mail::to($email)->send(new TransactionalTemplateMail(
            $content,
            $acceptUrl,
            isTest: false,
            brand: $invitation->inviter?->brand
        ));

        return back()->with('success', __('Einladung wurde gesendet. Der Eingeladene erhält eine E-Mail mit einem Link zur Annahme.'));
    }

    public function storeInvitationWebspace(StoreProductInvitationRequest $request, WebspaceAccount $webspace_account): RedirectResponse
    {
        return $this->storeInvitationForShareable($request, $webspace_account);
    }

    public function storeInvitationGameServer(StoreProductInvitationRequest $request, GameServerAccount $game_server_account): RedirectResponse
    {
        return $this->storeInvitationForShareable($request, $game_server_account);
    }

    public function storeInvitationTeamSpeak(StoreProductInvitationRequest $request, TeamSpeakServerAccount $team_speak_server_account): RedirectResponse
    {
        return $this->storeInvitationForShareable($request, $team_speak_server_account);
    }

    public function storeInvitationGameserverCloud(StoreProductInvitationRequest $request, GameserverCloudSubscription $subscription): RedirectResponse
    {
        return $this->storeInvitationForShareable($request, $subscription);
    }

    public function storeInvitationDomain(StoreProductInvitationRequest $request, ResellerDomain $reseller_domain): RedirectResponse
    {
        return $this->storeInvitationForShareable($request, $reseller_domain);
    }

    /**
     * Update permissions for an existing share.
     */
    public function updateShare(UpdateProductShareRequest $request, WebspaceAccount|GameServerAccount|TeamSpeakServerAccount|GameserverCloudSubscription|ResellerDomain $shareable, ProductShare $share): RedirectResponse
    {
        return $this->updateShareForShareable($request, $shareable, $share);
    }

    /**
     * @param  WebspaceAccount|GameServerAccount|TeamSpeakServerAccount|GameserverCloudSubscription|ResellerDomain  $shareable
     */
    private function updateShareForShareable(UpdateProductShareRequest $request, object $shareable, ProductShare $share): RedirectResponse
    {
        $this->authorize('manageCollaborators', $shareable);

        if ($share->shareable_type !== get_class($shareable) || (string) $share->shareable_id !== (string) $shareable->getKey()) {
            abort(404);
        }

        if ($this->shareTargetsProductOwner($shareable, $share)) {
            abort(403);
        }

        $allowed = $this->allowedPermissions($shareable);
        $permissions = array_values(array_intersect(
            (array) $request->validated('permissions'),
            $allowed
        ));
        if ($permissions === []) {
            throw ValidationException::withMessages([
                'permissions' => [__('Bitte wählen Sie mindestens eine gültige Berechtigung.')],
            ]);
        }

        $share->update(['permissions' => $permissions]);

        return back()->with('success', __('Berechtigungen wurden aktualisiert.'));
    }

    public function updateShareWebspace(UpdateProductShareRequest $request, WebspaceAccount $webspace_account, ProductShare $share): RedirectResponse
    {
        return $this->updateShareForShareable($request, $webspace_account, $share);
    }

    public function updateShareGameServer(UpdateProductShareRequest $request, GameServerAccount $game_server_account, ProductShare $share): RedirectResponse
    {
        return $this->updateShareForShareable($request, $game_server_account, $share);
    }

    public function updateShareTeamSpeak(UpdateProductShareRequest $request, TeamSpeakServerAccount $team_speak_server_account, ProductShare $share): RedirectResponse
    {
        return $this->updateShareForShareable($request, $team_speak_server_account, $share);
    }

    public function updateShareGameserverCloud(UpdateProductShareRequest $request, GameserverCloudSubscription $subscription, ProductShare $share): RedirectResponse
    {
        return $this->updateShareForShareable($request, $subscription, $share);
    }

    public function updateShareDomain(UpdateProductShareRequest $request, ResellerDomain $reseller_domain, ProductShare $share): RedirectResponse
    {
        return $this->updateShareForShareable($request, $reseller_domain, $share);
    }

    /**
     * Remove a share (revoke access).
     */
    public function destroyShare(Request $request, WebspaceAccount|GameServerAccount|TeamSpeakServerAccount|GameserverCloudSubscription|ResellerDomain $shareable, ProductShare $share): RedirectResponse
    {
        return $this->destroyShareForShareable($request, $shareable, $share);
    }

    /**
     * @param  WebspaceAccount|GameServerAccount|TeamSpeakServerAccount|GameserverCloudSubscription|ResellerDomain  $shareable
     */
    private function destroyShareForShareable(Request $request, object $shareable, ProductShare $share): RedirectResponse
    {
        $this->authorize('manageCollaborators', $shareable);

        if ($share->shareable_type !== get_class($shareable) || (string) $share->shareable_id !== (string) $shareable->getKey()) {
            abort(404);
        }

        if ($this->shareTargetsProductOwner($shareable, $share)) {
            abort(403);
        }

        $share->delete();

        return back()->with('success', __('Zugriff wurde entfernt.'));
    }

    public function destroyShareWebspace(Request $request, WebspaceAccount $webspace_account, ProductShare $share): RedirectResponse
    {
        return $this->destroyShareForShareable($request, $webspace_account, $share);
    }

    public function destroyShareGameServer(Request $request, GameServerAccount $game_server_account, ProductShare $share): RedirectResponse
    {
        return $this->destroyShareForShareable($request, $game_server_account, $share);
    }

    public function destroyShareTeamSpeak(Request $request, TeamSpeakServerAccount $team_speak_server_account, ProductShare $share): RedirectResponse
    {
        return $this->destroyShareForShareable($request, $team_speak_server_account, $share);
    }

    public function destroyShareGameserverCloud(Request $request, GameserverCloudSubscription $subscription, ProductShare $share): RedirectResponse
    {
        return $this->destroyShareForShareable($request, $subscription, $share);
    }

    public function destroyShareDomain(Request $request, ResellerDomain $reseller_domain, ProductShare $share): RedirectResponse
    {
        return $this->destroyShareForShareable($request, $reseller_domain, $share);
    }

    /**
     * Cancel a pending invitation.
     */
    public function destroyInvitation(Request $request, WebspaceAccount|GameServerAccount|TeamSpeakServerAccount|GameserverCloudSubscription|ResellerDomain $shareable, ProductInvitation $invitation): RedirectResponse
    {
        return $this->destroyInvitationForShareable($request, $shareable, $invitation);
    }

    /**
     * @param  WebspaceAccount|GameServerAccount|TeamSpeakServerAccount|GameserverCloudSubscription|ResellerDomain  $shareable
     */
    private function destroyInvitationForShareable(Request $request, object $shareable, ProductInvitation $invitation): RedirectResponse
    {
        $this->authorize('manageCollaborators', $shareable);

        if ($invitation->shareable_type !== get_class($shareable) || (string) $invitation->shareable_id !== (string) $shareable->getKey()) {
            abort(404);
        }

        $invitation->delete();

        return back()->with('success', __('Einladung wurde zurückgezogen.'));
    }

    public function destroyInvitationWebspace(Request $request, WebspaceAccount $webspace_account, ProductInvitation $invitation): RedirectResponse
    {
        return $this->destroyInvitationForShareable($request, $webspace_account, $invitation);
    }

    public function destroyInvitationGameServer(Request $request, GameServerAccount $game_server_account, ProductInvitation $invitation): RedirectResponse
    {
        return $this->destroyInvitationForShareable($request, $game_server_account, $invitation);
    }

    public function destroyInvitationTeamSpeak(Request $request, TeamSpeakServerAccount $team_speak_server_account, ProductInvitation $invitation): RedirectResponse
    {
        return $this->destroyInvitationForShareable($request, $team_speak_server_account, $invitation);
    }

    public function destroyInvitationGameserverCloud(Request $request, GameserverCloudSubscription $subscription, ProductInvitation $invitation): RedirectResponse
    {
        return $this->destroyInvitationForShareable($request, $subscription, $invitation);
    }

    public function destroyInvitationDomain(Request $request, ResellerDomain $reseller_domain, ProductInvitation $invitation): RedirectResponse
    {
        return $this->destroyInvitationForShareable($request, $reseller_domain, $invitation);
    }
}
