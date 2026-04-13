<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import * as ItemCategoryController from '@/actions/App/Http/Controllers/ItemCategories/ItemCategoryController';

interface Category {
    id: number;
    name: string;
}

const props = defineProps<{ category: Category }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Categorias', href: ItemCategoryController.index.url() },
            { title: 'Editar', href: '#' },
        ],
    },
});

const form = useForm({ name: props.category.name });

function submit() {
    form.put(
        ItemCategoryController.update.url({ itemCategory: props.category.id }),
    );
}

function destroy() {
    if (!confirm('Excluir esta categoria?')) {
        return;
    }

    router.delete(
        ItemCategoryController.destroy.url({ itemCategory: props.category.id }),
    );
}
</script>

<template>
    <Head :title="`Editar ${props.category.name}`" />
    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Editar categoria</h1>
            <button
                type="button"
                class="rounded-md bg-destructive px-3 py-1.5 text-sm font-medium text-destructive-foreground hover:opacity-90"
                @click="destroy"
            >
                Excluir
            </button>
        </div>
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
                Salvar
            </button>
        </form>
    </div>
</template>
