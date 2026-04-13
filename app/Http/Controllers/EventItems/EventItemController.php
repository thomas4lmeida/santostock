<?php

namespace App\Http\Controllers\EventItems;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventItems\SaveEventItemRequest;
use App\Models\Event;
use App\Models\EventItem;
use Illuminate\Http\RedirectResponse;

class EventItemController extends Controller
{
    public function store(SaveEventItemRequest $request, Event $event): RedirectResponse
    {
        $event->items()->create($request->validated());

        return to_route('events.show', $event);
    }

    public function update(SaveEventItemRequest $request, Event $event, EventItem $item): RedirectResponse
    {
        abort_unless($item->event_id === $event->id, 404);

        $item->update($request->validated());

        return to_route('events.show', $event);
    }

    public function destroy(Event $event, EventItem $item): RedirectResponse
    {
        abort_unless($item->event_id === $event->id, 404);

        $item->delete();

        return to_route('events.show', $event);
    }
}
