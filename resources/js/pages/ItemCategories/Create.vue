<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import * as ItemCategoryController from '@/actions/App/Http/Controllers/ItemCategories/ItemCategoryController';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Categorias', href: ItemCategoryController.index.url() },
            { title: 'Nova', href: ItemCategoryController.create.url() },
        ],
    },
});

const form = useForm({ name: '' });

function submit() {
    form.post(ItemCategoryController.store.url());
}
</script>

<template>
    <Head title="Nova categoria" />
    <div class="flex flex-col gap-4 p-4">
        <h1 class="text-xl font-semibold">Nova categoria</h1>
        <form class="flex max-w-md flex-col gap-2" @submit.prevent="submit">
            <input
                v-model="form.name"
                type="text"
                class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                required
            />
            <p v-if="form.errors.name" class="text-xs text-destructive">
                {{ form.errors.name }}
            </p>
            <button
                type="submit"
                :disabled="form.processing"
                class="self-start rounded-md bg-primary px-3 py-2 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
            >
                Criar
            </button>
        </form>
    </div>
</template>
