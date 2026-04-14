<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import * as ProductController from '@/actions/App/Http/Controllers/Products/ProductController';
import ProductForm from './ProductForm.vue';

interface ItemCategory {
    id: number;
    name: string;
}

interface Unit {
    id: number;
    name: string;
    abbreviation: string;
}

defineProps<{
    itemCategories: ItemCategory[];
    units: Unit[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Produtos', href: ProductController.index.url() },
            { title: 'Novo produto', href: ProductController.create.url() },
        ],
    },
});
</script>

<template>
    <Head title="Novo produto" />
    <div class="flex flex-col gap-4 p-4">
        <h1 class="text-xl font-semibold">Novo produto</h1>
        <ProductForm
            :item-categories="itemCategories"
            :units="units"
            :submit-action="{ url: ProductController.store.url(), method: 'post' }"
            submit-label="Criar produto"
        />
    </div>
</template>
