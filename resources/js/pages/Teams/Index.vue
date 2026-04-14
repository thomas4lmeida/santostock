<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import * as TeamController from '@/actions/App/Http/Controllers/Teams/TeamController';
import type { Paginated } from '@/types/pagination';

interface Team {
    id: number;
    name: string;
    description: string | null;
    users_count: number;
}

defineProps<{ teams: Paginated<Team> }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Equipes', href: TeamController.index.url() }],
    },
});

function destroy(team: Team) {
    if (!confirm('Excluir esta equipe?')) {
        return;
    }

    router.delete(TeamController.destroy.url({ team: team.id }));
}
</script>

<template>
    <Head title="Equipes" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Equipes</h1>
            <Link
                :href="TeamController.create.url()"
                class="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:opacity-90"
            >
                Nova equipe
            </Link>
        </div>

        <div
            class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <table class="w-full text-left text-sm">
                <thead class="border-b border-sidebar-border/70 bg-muted/50">
                    <tr>
                        <th class="px-3 py-2">Nome</th>
                        <th class="px-3 py-2">Descrição</th>
                        <th class="px-3 py-2">Nº de usuários</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="team in teams.data"
                        :key="team.id"
                        class="border-b border-sidebar-border/40 last:border-0"
                    >
                        <td class="px-3 py-2">{{ team.name }}</td>
                        <td class="px-3 py-2">{{ team.description ?? '—' }}</td>
                        <td class="px-3 py-2">{{ team.users_count }}</td>
                        <td class="px-3 py-2 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <Link
                                    :href="
                                        TeamController.show.url({
                                            team: team.id,
                                        })
                                    "
                                    class="text-primary hover:underline"
                                >
                                    Ver
                                </Link>
                                <Link
                                    :href="
                                        TeamController.edit.url({
                                            team: team.id,
                                        })
                                    "
                                    class="text-primary hover:underline"
                                >
                                    Editar
                                </Link>
                                <button
                                    type="button"
                                    class="text-destructive hover:underline"
                                    @click="destroy(team)"
                                >
                                    Excluir
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="teams.data.length === 0">
                        <td
                            colspan="4"
                            class="px-3 py-6 text-center text-sm text-muted-foreground"
                        >
                            Nenhuma equipe cadastrada.
                            <Link
                                :href="TeamController.create.url()"
                                class="text-primary hover:underline"
                            >
                                Criar equipe
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
