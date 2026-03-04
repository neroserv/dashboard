<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ApiTokenController extends Controller
{
    public function index(Request $request): Response
    {
        $tokens = $request->user()
            ->tokens()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'created_at' => $token->created_at->toIso8601String(),
            ])
            ->values()
            ->all();

        return Inertia::render('settings/ApiTokens', [
            'tokens' => $tokens,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('personal_access_tokens', 'name')
                    ->where('tokenable_id', $user->id)
                    ->where('tokenable_type', $user->getMorphClass()),
            ],
        ], [
            'name.required' => 'Bitte einen Namen für den Token angeben.',
            'name.unique' => 'Ein Token mit diesem Namen existiert bereits.',
        ]);

        $token = $request->user()->createToken($validated['name']);

        return redirect()->back()->with('flash', [
            'newToken' => [
                'plainTextToken' => $token->plainTextToken,
                'name' => $validated['name'],
            ],
        ]);
    }

    public function destroy(Request $request, string $token): RedirectResponse
    {
        $request->user()
            ->tokens()
            ->where('id', $token)
            ->firstOrFail()
            ->delete();

        return redirect()->back();
    }
}
