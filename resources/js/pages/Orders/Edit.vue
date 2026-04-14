<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import * as OrderController from '@/actions/App/Http/Controllers/Orders/OrderController';
import OrderForm from './OrderForm.vue';

interface Option {
    id: number;
    name: string;
}

interface Order {
    id: number;
    supplier_id: number;
    product_id: number;
    ordered_quantity: number;
    notes: string | null;
    status: string;
}

const props = defineProps<{
    order: Order;
    suppliers: Option[];
    products: Option[];
    canEditQuantity: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Pedidos', href: OrderController.index.url() },
            { title: 'Editar', href: '#' },
        ],
    },
});

function destroy() {
    if (!confirm('Excluir este pedido?')) return;
    router.delete(OrderController.destroy.url({ order: props.order.id }));
}
</script>

<template>
    <Head :title="`Editar pedido #${props.order.id}`" />
    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Editar pedido</h1>
            <button
                type="button"
                class="rounded-md bg-destructive px-3 py-1.5 text-sm font-medium text-destructive-foreground hover:opacity-90"
                @click="destroy"
            >
                Excluir
            </button>
        </div>
        <OrderForm
            :initial="props.order"
            :suppliers="suppliers"
            :products="products"
            :can-edit-quantity="canEditQuantity"
            :submit-url="OrderController.update.url({ order: props.order.id })"
            method="put"
            submit-label="Salvar alterações"
        />
    </div>
</template>
