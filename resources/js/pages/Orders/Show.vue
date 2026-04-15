<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import * as OrderController from '@/actions/App/Http/Controllers/Orders/OrderController';
import CancelOrderController from '@/actions/App/Http/Controllers/Orders/CancelOrderController';
import CloseShortOrderController from '@/actions/App/Http/Controllers/Orders/CloseShortOrderController';

interface Order {
    id: number;
    supplier_id: number;
    product_id: number;
    ordered_quantity: number;
    status: string;
    status_label: string;
    notes: string | null;
    supplier: { id: number; name: string } | null;
    product: { id: number; name: string } | null;
    receipts: Array<{ id: number; quantity: number }>;
}

const props = defineProps<{
    order: Order;
    canCancel: boolean;
    canCloseShort: boolean;
}>();

const page = usePage<{ auth: { user: { role: string | null } | null } }>();
const isAdmin = computed(() => page.props.auth?.user?.role === 'administrador');

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Pedidos', href: OrderController.index.url() },
            { title: 'Detalhes', href: '#' },
        ],
    },
});

function cancel() {
    if (!confirm('Cancelar este pedido?')) return;
    router.post(CancelOrderController.url({ order: props.order.id }));
}

function closeShort() {
    if (!confirm('Encerrar pedido como saldo curto?')) return;
    router.post(CloseShortOrderController.url({ order: props.order.id }));
}
</script>

<template>
    <Head :title="`Pedido #${props.order.id}`" />
    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Pedido #{{ props.order.id }}</h1>
            <div v-if="isAdmin" class="flex gap-2">
                <button
                    v-if="canCancel"
                    type="button"
                    class="rounded-md bg-destructive px-3 py-1.5 text-sm font-medium text-destructive-foreground hover:opacity-90"
                    @click="cancel"
                >
                    Cancelar
                </button>
                <button
                    v-if="canCloseShort"
                    type="button"
                    class="rounded-md bg-muted px-3 py-1.5 text-sm font-medium hover:opacity-90"
                    @click="closeShort"
                >
                    Encerrar com saldo curto
                </button>
            </div>
        </div>

        <dl class="grid max-w-2xl grid-cols-2 gap-3 text-sm">
            <dt class="text-muted-foreground">Fornecedor</dt>
            <dd>{{ order.supplier?.name ?? '—' }}</dd>
            <dt class="text-muted-foreground">Produto</dt>
            <dd>{{ order.product?.name ?? '—' }}</dd>
            <dt class="text-muted-foreground">Quantidade</dt>
            <dd>{{ order.ordered_quantity }}</dd>
            <dt class="text-muted-foreground">Status</dt>
            <dd>{{ order.status_label }}</dd>
            <dt class="text-muted-foreground">Observações</dt>
            <dd>{{ order.notes ?? '—' }}</dd>
        </dl>
    </div>
</template>
