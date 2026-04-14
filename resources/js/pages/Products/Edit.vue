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

interface Product {
    id: number;
    name: string;
    item_category_id: number;
    unit_id: number;
}

const props = defineProps<{
    product: Product;
    itemCategories: ItemCategory[];
    units: Unit[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Produtos', href: ProductController.index.url() },
            { title: 'Editar', href: '#' },
        ],
    },
});
</script>

<template>
    <Head :title="`Editar ${props.product.name}`" />
    <div class="flex flex-col gap-4 p-4">
        <h1 class="text-xl font-semibold">Editar produto</h1>
        <ProductForm
            :product="props.product"
            :item-categories="itemCategories"
            :units="units"
            :submit-action="{
                url: ProductController.update.url({ product: props.product.id }),
                method: 'put',
            }"
            submit-label="Salvar alterações"
        />
    </div>
</template>
