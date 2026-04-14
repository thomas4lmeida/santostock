<?php

namespace App\Http\Controllers\Teams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\SaveTeamRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Teams/Index', [
            'teams' => Team::withCount('users')->orderBy('name')->paginate(50)->withQueryString(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Teams/Create', [
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(SaveTeamRequest $request): RedirectResponse
    {
        $team = Team::create($request->safe()->only(['name', 'description']));
        $team->users()->sync($request->input('user_ids', []));

        return to_route('teams.index');
    }

    public function show(Team $team): Response
    {
        $team->load('users:id,name');

        return Inertia::render('Teams/Show', ['team' => $team]);
    }

    public function edit(Team $team): Response
    {
        return Inertia::render('Teams/Edit', [
            'team' => $team,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'attachedUserIds' => $team->users()->pluck('users.id'),
        ]);
    }

    public function update(SaveTeamRequest $request, Team $team): RedirectResponse
    {
        $team->update($request->safe()->only(['name', 'description']));
        $team->users()->sync($request->input('user_ids', []));

        return to_route('teams.index');
    }

    public function destroy(Team $team): RedirectResponse
    {
        $team->delete();

        return to_route('teams.index');
    }
}
