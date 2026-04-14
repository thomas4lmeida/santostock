<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import * as TeamController from '@/actions/App/Http/Controllers/Teams/TeamController';

interface User {
    id: number;
    name: string;
}

interface TeamValues {
    name: string;
    description: string | null;
}

const props = defineProps<{
    team?: Partial<TeamValues> & { id?: number };
    users: User[];
    attachedUserIds: number[];
    submitUrl: string;
    method: 'post' | 'put';
    submitLabel: string;
}>();

const form = useForm({
    name: props.team?.name ?? '',
    description: props.team?.description ?? null,
    user_ids: [...props.attachedUserIds],
});

function toggleUser(userId: number) {
    const index = form.user_ids.indexOf(userId);

    if (index === -1) {
        form.user_ids.push(userId);
    } else {
        form.user_ids.splice(index, 1);
    }
}

function submit() {
    form[props.method](props.submitUrl);
}
</script>

<template>
    <form class="flex max-w-2xl flex-col gap-4" @submit.prevent="submit">
        <div class="flex flex-col gap-1">
            <label class="text-sm font-medium" for="name">Nome</label>
            <input
                id="name"
                v-model="form.name"
                type="text"
                class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                required
            />
            <p v-if="form.errors.name" class="text-xs text-destructive">
                {{ form.errors.name }}
            </p>
        </div>

        <div class="flex flex-col gap-1">
            <label class="text-sm font-medium" for="description"
                >Descrição</label
            >
            <textarea
                id="description"
                v-model="form.description"
                rows="3"
                class="rounded-md border border-input bg-background px-3 py-2 text-sm"
            />
            <p v-if="form.errors.description" class="text-xs text-destructive">
                {{ form.errors.description }}
            </p>
        </div>

        <div class="flex flex-col gap-1">
            <label class="text-sm font-medium">Usuários</label>
            <div
                class="h-40 overflow-y-auto rounded-md border border-input bg-background px-3 py-2 text-sm"
            >
                <label
                    v-for="user in users"
                    :key="user.id"
                    class="flex cursor-pointer items-center gap-2 py-1"
                >
                    <input
                        type="checkbox"
                        :value="user.id"
                        :checked="form.user_ids.includes(user.id)"
                        class="rounded"
                        @change="toggleUser(user.id)"
                    />
                    {{ user.name }}
                </label>
                <p v-if="users.length === 0" class="text-muted-foreground">
                    Nenhum usuário disponível.
                </p>
            </div>
            <p v-if="form.errors.user_ids" class="text-xs text-destructive">
                {{ form.errors.user_ids }}
            </p>
        </div>

        <div class="flex items-center gap-3">
            <button
                type="submit"
                :disabled="form.processing"
                class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
            >
                {{ submitLabel }}
            </button>
            <Link
                :href="TeamController.index.url()"
                class="text-sm text-muted-foreground hover:underline"
            >
                Cancelar
            </Link>
        </div>
    </form>
</template>
