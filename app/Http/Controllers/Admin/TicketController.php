<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkTicketsRequest;
use App\Http\Requests\Admin\MergeTicketRequest;
use App\Http\Requests\Admin\StoreTicketMessageRequest;
use App\Http\Requests\Admin\StoreTicketTimeLogRequest;
use App\Http\Requests\Admin\StoreTicketTodoRequest;
use App\Http\Requests\Admin\UpdateTicketRequest;
use App\Http\Requests\Admin\UpdateTicketTodoRequest;
use App\Models\AdminActivityLog;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketMessage;
use App\Models\TicketMessageAttachment;
use App\Models\TicketMessageTemplate;
use App\Models\TicketPriority;
use App\Models\TicketService;
use App\Models\TicketTimeLog;
use App\Models\TicketTodo;
use App\Models\User;
use App\Notifications\TicketReplyNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketController extends Controller
{
    public function index(Request $request): Response
    {
        $allowedSorts = [
            'id',
            'subject',
            'created_at',
            'updated_at',
            'status_display',
            'customer',
            'category',
            'priority',
            'site_name',
            'assigned_to_name',
        ];

        $sort = $request->query('sort', 'updated_at');
        if (! is_string($sort) || ! in_array($sort, $allowedSorts, true)) {
            $sort = 'updated_at';
        }

        $direction = strtolower((string) $request->query('direction', 'desc'));
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        $tableState = [
            'search' => is_string($request->query('search')) ? trim($request->query('search')) : '',
            'include_archived' => $request->boolean('include_archived'),
            'sort' => $sort,
            'direction' => $direction,
            'status' => is_string($request->query('status')) ? $request->query('status') : '',
            'ticket_category_id' => is_string($request->query('ticket_category_id')) ? $request->query('ticket_category_id') : '',
            'ticket_priority_id' => is_string($request->query('ticket_priority_id')) ? $request->query('ticket_priority_id') : '',
            'user_id' => is_string($request->query('user_id')) ? $request->query('user_id') : '',
            'assigned_to' => is_string($request->query('assigned_to')) ? $request->query('assigned_to') : '',
        ];

        $query = Ticket::query()
            ->with([
                'user:id,name,email,avatar_path',
                'ticketCategory:id,name,slug',
                'ticketPriority:id,name,slug,color',
                'ticketServices',
                'assignedTo:id,name,avatar_path',
                'latestPublicMessage' => fn ($q) => $q->with('user:id,is_admin'),
            ]);

        $this->applyAdminTicketIndexClosedVisibility($query, $request);

        if ($tableState['status'] !== '') {
            $query->where('tickets.status', $tableState['status']);
        }
        if ($tableState['ticket_category_id'] !== '') {
            $query->where('tickets.ticket_category_id', $tableState['ticket_category_id']);
        }
        if ($tableState['ticket_priority_id'] !== '') {
            $query->where('tickets.ticket_priority_id', $tableState['ticket_priority_id']);
        }
        if ($tableState['user_id'] !== '') {
            $query->where('tickets.user_id', $tableState['user_id']);
        }
        if ($tableState['assigned_to'] !== '') {
            if ($tableState['assigned_to'] === '0') {
                $query->whereNull('tickets.assigned_to');
            } else {
                $query->where('tickets.assigned_to', $tableState['assigned_to']);
            }
        }

        $this->applyAdminTicketIndexSearch($query, $tableState['search']);
        $this->applyAdminTicketIndexSorting($query, $sort, $direction);

        $tickets = $query->paginate(15)->withQueryString();

        $tickets->through(function (Ticket $ticket): Ticket {
            $summary = $ticket->ticketServices->isEmpty()
                ? 'Allgemein / Kein Dienst'
                : $ticket->ticketServices->map(fn (TicketService $ts) => $ts->resolveLabel())->join(', ');
            $ticket->setAttribute('service_display', $summary);

            $last = $ticket->latestPublicMessage;
            $fromCustomer = $last !== null && ! $last->user?->is_admin;
            $ticket->setAttribute('last_message_from_customer', $fromCustomer);
            $ticket->unsetRelation('latestPublicMessage');

            return $ticket;
        });

        $categories = TicketCategory::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'slug']);
        $priorities = TicketPriority::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'slug', 'color']);
        $admins = User::query()->where('is_admin', true)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('admin/tickets/Index', [
            'tickets' => $tickets,
            'categories' => $categories,
            'priorities' => $priorities,
            'admins' => $admins,
            'tableState' => $tableState,
        ]);
    }

    private function applyAdminTicketIndexClosedVisibility(Builder $query, Request $request): void
    {
        if ($request->boolean('include_archived')) {
            return;
        }

        if ($request->filled('status') && $request->query('status') === 'closed') {
            return;
        }

        $query->where(function ($q) {
            $q->where('tickets.status', '!=', 'closed')
                ->orWhere(function ($q2) {
                    $q2->where('tickets.status', 'closed')
                        ->where(function ($q3) {
                            $q3->where('tickets.closed_at', '>=', now()->subHours(24))
                                ->orWhere(function ($q4) {
                                    $q4->whereNull('tickets.closed_at')
                                        ->where('tickets.updated_at', '>=', now()->subHours(24));
                                });
                        });
                });
        });
    }

    private function applyAdminTicketIndexSearch(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
        $like = '%'.$escaped.'%';

        $query->where(function ($q) use ($like, $search) {
            $q->where('tickets.subject', 'like', $like);
            if (ctype_digit($search)) {
                $q->orWhere('tickets.id', (int) $search);
            }
            $q->orWhereHas('user', function ($userQuery) use ($like) {
                $userQuery->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
            $q->orWhereHas('ticketCategory', fn ($c) => $c->where('name', 'like', $like));
            $q->orWhereHas('assignedTo', fn ($a) => $a->where('name', 'like', $like));
        });
    }

    private function applyAdminTicketIndexSorting(Builder $query, string $sort, string $direction): void
    {
        $dir = $direction === 'asc' ? 'asc' : 'desc';

        // Geschlossene Tickets ans Ende der Liste (innerhalb der gewählten Sortierung).
        $query->orderByRaw('CASE WHEN tickets.status = ? THEN 1 ELSE 0 END ASC', ['closed']);

        match ($sort) {
            'id' => $query->orderBy('tickets.id', $dir),
            'subject' => $query->orderBy('tickets.subject', $dir),
            'created_at' => $query->orderBy('tickets.created_at', $dir),
            'updated_at' => $query->orderBy('tickets.updated_at', $dir),
            'status_display' => $query->orderBy('tickets.status', $dir),
            'customer' => $query->orderBy(
                User::query()->select('name')->whereColumn('users.id', 'tickets.user_id'),
                $dir
            ),
            'category' => $query->orderBy(
                TicketCategory::query()->select('name')->whereColumn('id', 'tickets.ticket_category_id'),
                $dir
            ),
            'priority' => $query->orderBy(
                TicketPriority::query()->select('sort_order')->whereColumn('id', 'tickets.ticket_priority_id'),
                $dir
            )->orderBy(
                TicketPriority::query()->select('name')->whereColumn('id', 'tickets.ticket_priority_id'),
                $dir
            ),
            'assigned_to_name' => $query->orderBy(
                User::query()->select('name')->whereColumn('users.id', 'tickets.assigned_to'),
                $dir
            ),
            'site_name' => $query->orderByRaw(
                '(SELECT MIN(id) FROM ticket_services WHERE ticket_services.ticket_id = tickets.id) '.$dir
            ),
            default => $query->orderBy('tickets.updated_at', 'desc'),
        };
    }

    public function bulk(BulkTicketsRequest $request): RedirectResponse
    {
        $action = $request->validated('action');
        $ids = $request->validated('ticket_ids');
        $adminUserId = $request->user()->id;

        $tickets = Ticket::query()->whereIn('id', $ids)->orderBy('id')->get();
        $uniqueIds = array_values(array_unique($ids));
        if ($tickets->count() !== count($uniqueIds)) {
            return redirect()->route('admin.tickets.index')->with('error', 'Einige ausgewählte Tickets existieren nicht.');
        }

        $count = 0;

        DB::transaction(function () use ($tickets, $action, $request, $adminUserId, &$count): void {
            foreach ($tickets as $ticket) {
                $update = match ($action) {
                    'assign' => ['assigned_to' => $request->validated('assigned_to')],
                    'status' => ['status' => $request->validated('status')],
                    'priority' => ['ticket_priority_id' => $request->validated('ticket_priority_id')],
                    'category' => ['ticket_category_id' => $request->validated('ticket_category_id')],
                    default => [],
                };

                if ($update === []) {
                    continue;
                }

                $keys = array_keys($update);
                $old = $ticket->only($keys);
                $ticket->update($update);
                $ticket->refresh();
                $new = $ticket->only($keys);
                $count++;
                AdminActivityLog::log($adminUserId, 'ticket_updated', Ticket::class, $ticket->id, $old, $new);
            }
        });

        $message = $count === 1
            ? '1 Ticket wurde aktualisiert.'
            : $count.' Tickets wurden aktualisiert.';

        return redirect()->back()->with('success', $message);
    }

    public function show(Ticket $ticket): Response
    {
        $ticket->load([
            'user:id,name,email,avatar_path',
            'ticketCategory',
            'ticketPriority',
            'ticketServices',
            'assignedTo:id,name,avatar_path',
            'tags:id,name,slug,color',
            'messages' => fn ($q) => $q->with(['user:id,name,is_admin,avatar_path', 'attachments'])->orderBy('created_at'),
        ]);
        $lastPublicMessage = $ticket->messages->filter(fn (TicketMessage $m) => ! $m->is_internal)->last();
        $lastMessageFromCustomer = $lastPublicMessage !== null && ! $lastPublicMessage->user?->is_admin;
        $categories = \App\Models\TicketCategory::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'slug']);
        $priorities = \App\Models\TicketPriority::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'slug', 'color']);
        $admins = User::query()->where('is_admin', true)->orderBy('name')->get(['id', 'name', 'avatar_path']);
        $customerSites = [];
        $recentTickets = Ticket::query()
            ->where('user_id', $ticket->user_id)
            ->where('id', '!=', $ticket->id)
            ->latest()
            ->limit(5)
            ->get(['id', 'uuid', 'subject', 'status', 'created_at']);
        $allTags = \App\Models\Tag::query()->orderBy('name')->get(['id', 'name', 'slug', 'color']);

        $ticketArray = $ticket->toArray();
        unset($ticketArray['time_logs'], $ticketArray['todos'], $ticketArray['timeLogs'], $ticketArray['todos']);
        $ticketArray['assigned_to'] = $ticket->getRawOriginal('assigned_to');
        foreach ($ticketArray['messages'] ?? [] as $i => $msg) {
            $message = $ticket->messages[$i];
            $ticketArray['messages'][$i]['attachments'] = $message->attachments->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'download_url' => route('admin.tickets.attachments.download', ['ticket' => $ticket, 'attachment' => $a->id]),
            ])->values()->all();
        }

        $activityLogs = AdminActivityLog::query()
            ->where('model_type', Ticket::class)
            ->where('model_id', $ticket->id)
            ->whereIn('action', ['ticket_updated', 'ticket_merged'])
            ->with('user:id,name')
            ->orderBy('created_at')
            ->get();

        $statusLabels = [
            'open' => 'Offen',
            'in_progress' => 'In Bearbeitung',
            'waiting_customer' => 'Warte auf Kunde',
            'resolved' => 'Erledigt',
            'closed' => 'Geschlossen',
        ];
        $ticketActivityLogs = $activityLogs->map(function (AdminActivityLog $log) use ($categories, $priorities, $admins, $statusLabels) {
            $user = $log->user ? ['id' => $log->user->id, 'name' => $log->user->name] : null;
            $old = $log->old_values ?? [];
            $new = $log->new_values ?? [];
            $descriptions = [];
            $actionType = 'updated';

            if ($log->action === 'ticket_merged') {
                return [
                    'id' => 'log-'.$log->id,
                    'type' => 'activity',
                    'action_type' => 'merged',
                    'created_at' => $log->created_at->toIso8601String(),
                    'user' => $user,
                    'description' => 'Ticket zusammengeführt',
                ];
            }

            if (isset($new['status']) && ($old['status'] ?? null) !== $new['status']) {
                $descriptions[] = 'Status: '.($statusLabels[$old['status'] ?? ''] ?? $old['status'] ?? '–').' → '.($statusLabels[$new['status']] ?? $new['status']);
                $actionType = 'status_change';
            }
            if (isset($new['ticket_category_id']) && ($old['ticket_category_id'] ?? null) != $new['ticket_category_id']) {
                $cat = $categories->firstWhere('id', $new['ticket_category_id']);
                $descriptions[] = 'Kategorie geändert'.($cat ? ' zu '.$cat->name : '');
                $actionType = 'category_change';
            }
            if (array_key_exists('ticket_priority_id', $new) && ($old['ticket_priority_id'] ?? null) != $new['ticket_priority_id']) {
                $prio = $priorities->firstWhere('id', $new['ticket_priority_id']);
                $descriptions[] = 'Priorität'.($prio ? ': '.$prio->name : ' geändert');
                $actionType = 'priority_change';
            }
            if (array_key_exists('assigned_to', $new) && ($old['assigned_to'] ?? null) != $new['assigned_to']) {
                $assigned = $new['assigned_to'] ? $admins->firstWhere('id', $new['assigned_to']) : null;
                $descriptions[] = $assigned ? 'Zugewiesen an '.$assigned->name : 'Zuweisung entfernt';
                $actionType = 'assigned_change';
            }
            if ($descriptions === []) {
                return null;
            }

            return [
                'id' => 'log-'.$log->id,
                'type' => 'activity',
                'action_type' => $actionType,
                'created_at' => $log->created_at->toIso8601String(),
                'user' => $user,
                'description' => implode('; ', $descriptions),
            ];
        })->filter()->values()->all();

        $ticketMessageTemplates = TicketMessageTemplate::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'body']);

        $affectedServices = $ticket->ticketServices->map(function (TicketService $ts) {
            $url = $this->resolveServiceAdminUrl($ts->service_type, $ts->service_id);

            return ['type' => $ts->service_type, 'id' => $ts->service_id, 'label' => $ts->resolveLabel(), 'url' => $url];
        })->values()->all();

        $serviceName = $affectedServices !== []
            ? implode(', ', array_column($affectedServices, 'label'))
            : 'Allgemein / Kein Dienst';

        return Inertia::render('admin/tickets/Show', [
            'ticket' => $ticketArray,
            'categories' => $categories,
            'priorities' => $priorities,
            'admins' => $admins,
            'customerSites' => $customerSites,
            'recentTickets' => $recentTickets,
            'lastMessageFromCustomer' => $lastMessageFromCustomer,
            'allTags' => $allTags,
            'ticketActivityLogs' => $ticketActivityLogs,
            'ticketMessageTemplates' => $ticketMessageTemplates,
            'serviceName' => $serviceName,
            'affectedServices' => $affectedServices,
        ]);
    }

    /**
     * Admin URL for the given service (sites, domains, webspace, gaming, teamspeak). Null if not linkable.
     */
    private function resolveServiceAdminUrl(string $serviceType, int $serviceId): ?string
    {
        try {
            return match ($serviceType) {
                'reseller_domain' => (function () use ($serviceId) {
                    $model = \App\Models\ResellerDomain::find($serviceId);

                    return $model ? route('admin.domains.show', $model) : null;
                })(),
                'webspace_account' => (function () use ($serviceId) {
                    $model = \App\Models\WebspaceAccount::find($serviceId);

                    return $model ? route('admin.webspace-accounts.show', $model) : null;
                })(),
                'game_server_account' => (function () use ($serviceId) {
                    $model = \App\Models\GameServerAccount::find($serviceId);

                    return $model ? route('admin.gaming-accounts.show', $model) : null;
                })(),
                'teamspeak_server_account' => (function () use ($serviceId) {
                    $model = \App\Models\TeamSpeakServerAccount::find($serviceId);

                    return $model ? route('admin.teamspeak-accounts.show', $model) : null;
                })(),
                default => null,
            };
        } catch (\Throwable) {
            return null;
        }
    }

    public function downloadAttachment(Request $request, Ticket $ticket, TicketMessageAttachment $attachment): StreamedResponse|RedirectResponse
    {
        $attachment->load('ticketMessage');
        if ($attachment->ticketMessage->ticket_id !== $ticket->id) {
            abort(404);
        }
        if (! Storage::disk('local')->exists($attachment->path)) {
            abort(404);
        }

        return Storage::disk('local')->download($attachment->path, $attachment->name);
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket): RedirectResponse
    {
        $validated = $request->validated();
        $allowed = ['status', 'ticket_category_id', 'ticket_priority_id', 'assigned_to', 'due_at'];
        $update = array_intersect_key($validated, array_flip($allowed));
        if (array_key_exists('due_at', $update) && $update['due_at'] === '') {
            $update['due_at'] = null;
        }

        $old = array_intersect_key($ticket->getOriginal(), array_flip($allowed));
        $ticket->update($update);
        if (array_key_exists('tag_ids', $validated)) {
            $ticket->tags()->sync($validated['tag_ids']);
        }
        $new = array_merge($update, array_key_exists('tag_ids', $validated) ? ['tag_ids' => $validated['tag_ids']] : []);

        AdminActivityLog::log($request->user()->id, 'ticket_updated', Ticket::class, $ticket->id, $old, $new);

        return redirect()->route('admin.tickets.show', $ticket)->with('success', 'Ticket aktualisiert.');
    }

    public function storeMessage(StoreTicketMessageRequest $request, Ticket $ticket): RedirectResponse
    {
        $isInternal = $request->boolean('is_internal', false);
        $body = $request->validated('body');
        if (! $isInternal) {
            $signature = $request->user()->ticket_signature;
            if ($signature !== null && trim($signature) !== '') {
                $body = $body."\n\n--\n".trim($signature);
            }
        }
        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'body' => $body,
            'is_internal' => $isInternal,
            'sent_via_admin' => true,
        ]);
        if (! $isInternal) {
            $ticket->update(['status' => 'waiting_customer']);
            $ticket->user?->notify(new TicketReplyNotification($ticket));
        }

        AdminActivityLog::log($request->user()->id, 'ticket_message_stored', Ticket::class, $ticket->id, null, ['is_internal' => $isInternal]);

        return redirect()->route('admin.tickets.show', $ticket)->with('success', 'Antwort gespeichert.');
    }

    public function storeTimeLog(StoreTicketTimeLogRequest $request, Ticket $ticket): RedirectResponse
    {
        $validated = $request->validated();
        TicketTimeLog::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'minutes' => (int) $validated['minutes'],
            'description' => $validated['description'] ?? null,
            'logged_at' => isset($validated['logged_at']) ? $validated['logged_at'] : now(),
        ]);

        AdminActivityLog::log($request->user()->id, 'ticket_time_log_added', Ticket::class, $ticket->id, null, ['minutes' => $validated['minutes']]);

        return redirect()->route('admin.tickets.show', $ticket)->with('success', 'Zeiteintrag hinzugefügt.');
    }

    public function storeTodo(StoreTicketTodoRequest $request, Ticket $ticket): RedirectResponse
    {
        $maxSort = $ticket->todos()->max('sort_order') ?? 0;
        $todo = TicketTodo::create([
            'ticket_id' => $ticket->id,
            'created_by' => $request->user()->id,
            'title' => $request->validated('title'),
            'sort_order' => $maxSort + 1,
        ]);

        AdminActivityLog::log($request->user()->id, 'ticket_todo_added', TicketTodo::class, $todo->id, null, ['title' => $todo->title]);

        return redirect()->route('admin.tickets.show', $ticket)->with('success', 'To-do hinzugefügt.');
    }

    public function updateTodo(UpdateTicketTodoRequest $request, Ticket $ticket, TicketTodo $todo): RedirectResponse
    {
        if ($todo->ticket_id !== $ticket->id) {
            abort(404);
        }
        $validated = $request->validated();
        $old = $todo->only(['title', 'is_done']);
        $todo->update(array_intersect_key($validated, array_flip(['title', 'is_done'])));

        AdminActivityLog::log($request->user()->id, 'ticket_todo_updated', TicketTodo::class, $todo->id, $old, $validated);

        return redirect()->route('admin.tickets.show', $ticket)->with('success', 'To-do aktualisiert.');
    }

    public function destroyTodo(Ticket $ticket, TicketTodo $todo): RedirectResponse
    {
        if ($todo->ticket_id !== $ticket->id) {
            abort(404);
        }
        $old = $todo->only(['title', 'is_done']);
        $todo->delete();

        AdminActivityLog::log($request->user()->id, 'ticket_todo_deleted', TicketTodo::class, $todo->id, $old, null);

        return redirect()->route('admin.tickets.show', $ticket)->with('success', 'To-do gelöscht.');
    }

    public function merge(MergeTicketRequest $request, Ticket $ticket): RedirectResponse
    {
        $targetUuid = $request->validated('target_ticket_uuid');
        $target = Ticket::where('uuid', $targetUuid)->firstOrFail();

        if ($target->user_id !== $ticket->user_id) {
            return redirect()->route('admin.tickets.show', $ticket)->with('error', 'Ziel-Ticket muss dem gleichen Kunden gehören.');
        }

        $ticket->messages()->update(['ticket_id' => $target->id]);
        $ticket->update([
            'status' => 'closed',
            'subject' => $ticket->subject.' [Zusammengeführt in #'.$target->id.']',
        ]);

        AdminActivityLog::log($request->user()->id, 'ticket_merged', Ticket::class, $target->id, ['source_ticket_id' => $ticket->id], null);

        return redirect()->route('admin.tickets.show', $target)->with('success', 'Ticket wurde zusammengeführt.');
    }
}
