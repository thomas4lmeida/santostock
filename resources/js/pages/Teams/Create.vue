<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import * as TeamController from '@/actions/App/Http/Controllers/Teams/TeamController';
import TeamForm from './TeamForm.vue';

interface User {
    id: number;
    name: string;
}

defineProps<{ users: User[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Equipes', href: TeamController.index.url() },
            { title: 'Nova equipe', href: TeamController.create.url() },
        ],
    },
});
</script>

<template>
    <Head title="Nova equipe" />
    <div class="flex flex-col gap-4 p-4">
        <h1 class="text-xl font-semibold">Nova equipe</h1>
        <TeamForm
            :users="users"
            :attached-user-ids="[]"
            :submit-url="TeamController.store.url()"
            method="post"
            submit-label="Criar equipe"
        />
    </div>
</template>
