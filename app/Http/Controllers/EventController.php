<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Http\Requests\SaveEventRequest;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Event::query()->orderBy('starts_at');

        if ($from = $request->date('from')) {
            $query->where('starts_at', '>=', $from->startOfDay());
        }
        if ($to = $request->date('to')) {
            $query->where('starts_at', '<=', $to->endOfDay());
        }

        $status = EventStatus::tryFrom($request->string('status')->toString());
        match ($status) {
            EventStatus::Upcoming => $query->where('starts_at', '>', now()),
            EventStatus::Ongoing => $query->where('starts_at', '<=', now())->where('ends_at', '>=', now()),
            EventStatus::Past => $query->where('ends_at', '<', now()),
            null => null,
        };

        return Inertia::render('Events/Index', [
            'events' => $query->paginate(50)->withQueryString(),
            'filters' => $request->only(['status', 'from', 'to']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Events/Create');
    }

    public function store(SaveEventRequest $request): RedirectResponse
    {
        Event::create($request->validated());

        return to_route('events.index');
    }

    public function show(Event $event): Response
    {
        return Inertia::render('Events/Show', ['event' => $event]);
    }

    public function edit(Event $event): Response
    {
        return Inertia::render('Events/Edit', ['event' => $event]);
    }

    public function update(SaveEventRequest $request, Event $event): RedirectResponse
    {
        $event->update($request->validated());

        return to_route('events.index');
    }

    public function destroy(Event $event): RedirectResponse
    {
        $event->delete();

        return to_route('events.index');
    }
}
