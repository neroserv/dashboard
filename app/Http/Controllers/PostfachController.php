<?php

namespace App\Http\Controllers;

use App\Models\UserEmailLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PostfachController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->renderPostfach($request, null);
    }

    public function show(Request $request, int $postfach): Response
    {
        $selected = UserEmailLog::where('user_id', $request->user()->id)->findOrFail($postfach);

        return $this->renderPostfach($request, $selected);
    }

    private function renderPostfach(Request $request, ?UserEmailLog $selected): Response
    {
        $user = $request->user();
        $emails = UserEmailLog::where('user_id', $user->id)
            ->orderByDesc('sent_at')
            ->limit(100)
            ->get(['id', 'subject', 'snippet', 'sent_at'])
            ->map(fn (UserEmailLog $log) => [
                'id' => $log->id,
                'subject' => $log->subject,
                'snippet' => $log->snippet,
                'sent_at' => $log->sent_at?->format('d.m.Y'),
            ]);

        $selectedEmail = null;
        if ($selected !== null) {
            $selectedEmail = [
                'id' => $selected->id,
                'subject' => $selected->subject,
                'body_html' => $selected->body_html,
                'sent_at' => $selected->sent_at?->format('d.m.Y H:i'),
            ];
        }

        return Inertia::render('account/Postfach', [
            'emails' => $emails,
            'selectedEmail' => $selectedEmail,
        ]);
    }
}
