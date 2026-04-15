<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import * as OrderController from '@/actions/App/Http/Controllers/Orders/OrderController';
import OrderForm from './OrderForm.vue';

interface Option {
    id: number;
    name: string;
}

defineProps<{
    suppliers: Option[];
    products: Option[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Pedidos', href: OrderController.index.url() },
            { title: 'Novo', href: OrderController.create.url() },
        ],
    },
});
</script>

<template>
    <Head title="Novo pedido" />
    <div class="flex flex-col gap-4 p-4">
        <h1 class="text-xl font-semibold">Novo pedido</h1>
        <OrderForm
            :suppliers="suppliers"
            :products="products"
            :submit-url="OrderController.store.url()"
            method="post"
            submit-label="Criar pedido"
        />
    </div>
</template>
