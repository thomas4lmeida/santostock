<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import * as ItemCategoryController from '@/actions/App/Http/Controllers/ItemCategories/ItemCategoryController';
import type { Paginated } from '@/types/pagination';

interface Category {
    id: number;
    name: string;
}

defineProps<{ categories: Paginated<Category> }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Categorias', href: ItemCategoryController.index.url() },
        ],
    },
});

const form = useForm({ name: '' });

function submit() {
    form.post(ItemCategoryController.store.url(), {
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <Head title="Categorias" />

    <div class="flex flex-col gap-4 p-4">
        <h1 class="text-xl font-semibold">Categorias de itens</h1>

        <form class="flex max-w-md gap-2" @submit.prevent="submit">
            <input
                v-model="form.name"
                type="text"
                placeholder="Nova categoria"
                class="flex-1 rounded-md border border-input bg-background px-3 py-2 text-sm"
                required
            />
            <button
                type="submit"
                :disabled="form.processing"
                class="rounded-md bg-primary px-3 py-2 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
            >
                Adicionar
            </button>
        </form>
        <p v-if="form.errors.name" class="text-xs text-destructive">
            {{ form.errors.name }}
        </p>

        <div
            class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <table class="w-full text-left text-sm">
                <thead class="border-b border-sidebar-border/70 bg-muted/50">
                    <tr>
                        <th class="px-3 py-2">Nome</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="category in categories.data"
                        :key="category.id"
                        class="border-b border-sidebar-border/40 last:border-0"
                    >
                        <td class="px-3 py-2">{{ category.name }}</td>
                        <td class="px-3 py-2 text-right">
                            <Link
                                :href="
                                    ItemCategoryController.edit.url({
                                        itemCategory: category.id,
                                    })
                                "
                                class="text-primary hover:underline"
                            >
                                Editar
                            </Link>
                        </td>
                    </tr>
                    <tr v-if="categories.data.length === 0">
                        <td
                            colspan="2"
                            class="px-3 py-6 text-center text-sm text-muted-foreground"
                        >
                            Nenhuma categoria cadastrada.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
