<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';
import * as OrderController from '@/actions/App/Http/Controllers/Orders/OrderController';
import type { Paginated } from '@/types/pagination';

interface OrderRow {
    id: number;
    supplier_id: number;
    product_id: number;
    ordered_quantity: number;
    status: string;
    status_label: string;
    created_at: string;
    supplier: { id: number; name: string } | null;
    product: { id: number; name: string } | null;
}

interface StatusOption {
    value: string;
    label: string;
}

const props = defineProps<{
    orders: Paginated<OrderRow>;
    filters: { status: string | null; supplier_id: number | null };
    statuses: StatusOption[];
}>();

const page = usePage<{ auth: { user: { role: string | null } | null } }>();
const isAdmin = computed(() => page.props.auth?.user?.role === 'administrador');

const local = reactive({
    status: props.filters.status ?? '',
    supplier_id: props.filters.supplier_id ?? '',
});

watch(
    () => [local.status, local.supplier_id],
    () => {
        router.get(
            OrderController.index.url(),
            {
                status: local.status || undefined,
                supplier_id: local.supplier_id || undefined,
            },
            { preserveState: true, replace: true },
        );
    },
);

function destroy(id: number) {
    if (!confirm('Excluir este pedido?')) return;
    router.delete(OrderController.destroy.url({ order: id }));
}

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Pedidos', href: OrderController.index.url() }],
    },
});
</script>

<template>
    <Head title="Pedidos" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Pedidos</h1>
            <Link
                v-if="isAdmin"
                :href="OrderController.create.url()"
                class="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:opacity-90"
            >
                Novo pedido
            </Link>
        </div>

        <div class="flex flex-wrap items-end gap-3">
            <div class="flex flex-col gap-1">
                <label class="text-xs text-muted-foreground" for="status-filter">Status</label>
                <select
                    id="status-filter"
                    v-model="local.status"
                    class="rounded-md border border-input bg-background px-3 py-1.5 text-sm"
                >
                    <option value="">Todos</option>
                    <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-sidebar-border/70 bg-muted/50">
                    <tr>
                        <th class="px-3 py-2">Fornecedor</th>
                        <th class="px-3 py-2">Produto</th>
                        <th class="px-3 py-2">Quantidade</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="order in orders.data" :key="order.id" class="border-b border-sidebar-border/40 last:border-0">
                        <td class="px-3 py-2">{{ order.supplier?.name ?? '—' }}</td>
                        <td class="px-3 py-2">{{ order.product?.name ?? '—' }}</td>
                        <td class="px-3 py-2">{{ order.ordered_quantity }}</td>
                        <td class="px-3 py-2">
                            <span class="rounded-full bg-muted px-2 py-0.5 text-xs">{{ order.status_label }}</span>
                        </td>
                        <td class="px-3 py-2 text-right">
                            <Link :href="OrderController.show.url({ order: order.id })" class="text-primary hover:underline">
                                Ver
                            </Link>
                            <template v-if="isAdmin">
                                <Link
                                    :href="OrderController.edit.url({ order: order.id })"
                                    class="ml-3 text-primary hover:underline"
                                >
                                    Editar
                                </Link>
                                <button
                                    type="button"
                                    class="ml-3 text-destructive hover:underline"
                                    @click="destroy(order.id)"
                                >
                                    Excluir
                                </button>
                            </template>
                        </td>
                    </tr>
                    <tr v-if="orders.data.length === 0">
                        <td colspan="5" class="px-3 py-6 text-center text-sm text-muted-foreground">
                            Nenhum pedido cadastrado.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
