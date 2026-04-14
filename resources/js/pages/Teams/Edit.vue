<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import * as TeamController from '@/actions/App/Http/Controllers/Teams/TeamController';
import TeamForm from './TeamForm.vue';

interface Team {
    id: number;
    name: string;
    description: string | null;
}

interface User {
    id: number;
    name: string;
}

const props = defineProps<{
    team: Team;
    users: User[];
    attachedUserIds: number[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Equipes', href: TeamController.index.url() },
            { title: 'Editar', href: '#' },
        ],
    },
});
</script>

<template>
    <Head :title="`Editar ${props.team.name}`" />
    <div class="flex flex-col gap-4 p-4">
        <h1 class="text-xl font-semibold">Editar equipe</h1>
        <TeamForm
            :team="props.team"
            :users="users"
            :attached-user-ids="attachedUserIds"
            :submit-url="TeamController.update.url({ team: props.team.id })"
            method="put"
            submit-label="Salvar alterações"
        />
    </div>
</template>
