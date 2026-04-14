<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import * as TeamController from '@/actions/App/Http/Controllers/Teams/TeamController';

interface TeamUser {
    id: number;
    name: string;
}

interface Team {
    id: number;
    name: string;
    description: string | null;
    users: TeamUser[];
}

const props = defineProps<{ team: Team }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Equipes', href: TeamController.index.url() },
            { title: 'Detalhes', href: '#' },
        ],
    },
});
</script>

<template>
    <Head :title="props.team.name" />
    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">{{ props.team.name }}</h1>
            <div class="flex items-center gap-2">
                <Link
                    :href="TeamController.edit.url({ team: props.team.id })"
                    class="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:opacity-90"
                >
                    Editar
                </Link>
                <Link
                    :href="TeamController.index.url()"
                    class="rounded-md border border-input px-3 py-1.5 text-sm hover:bg-muted"
                >
                    Voltar
                </Link>
            </div>
        </div>

        <div
            class="flex flex-col gap-4 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <div v-if="props.team.description">
                <p class="text-xs font-medium text-muted-foreground">
                    Descrição
                </p>
                <p class="text-sm whitespace-pre-line">
                    {{ props.team.description }}
                </p>
            </div>

            <div>
                <p class="mb-2 text-xs font-medium text-muted-foreground">
                    Usuários
                </p>
                <ul
                    v-if="props.team.users.length > 0"
                    class="list-disc pl-5 text-sm"
                >
                    <li v-for="user in props.team.users" :key="user.id">
                        {{ user.name }}
                    </li>
                </ul>
                <p v-else class="text-sm text-muted-foreground">
                    Nenhum usuário nesta equipe.
                </p>
            </div>
        </div>
    </div>
</template>
