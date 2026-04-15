<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import * as OrderController from '@/actions/App/Http/Controllers/Orders/OrderController';
import * as ReceiptController from '@/actions/App/Http/Controllers/Receipts/ReceiptController';

interface Warehouse {
    id: number;
    name: string;
}

interface Order {
    id: number;
    ordered_quantity: number;
    warehouse_id: number | null;
    warehouse: Warehouse | null;
    product: { id: number; name: string } | null;
}

const props = defineProps<{
    order: Order;
    saldo: number;
    warehouses: Warehouse[];
}>();

const form = useForm({
    warehouse_id: props.order.warehouse_id ?? props.warehouses[0]?.id ?? null,
    quantity: props.saldo,
    idempotency_key: crypto.randomUUID(),
    photos: [] as File[],
});

const warehouseLocked = computed(() => props.order.warehouse_id !== null);
const photosInput = ref<HTMLInputElement | null>(null);

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Pedidos', href: OrderController.index.url() },
            { title: 'Detalhes', href: '#' },
            { title: 'Registrar recebimento', href: '#' },
        ],
    },
});

function onPhotos(event: Event) {
    const input = event.target as HTMLInputElement;
    form.photos = input.files ? Array.from(input.files) : [];
}

function submit() {
    form.post(ReceiptController.store.url({ order: props.order.id }), {
        forceFormData: true,
    });
}
</script>

<template>
    <Head title="Registrar recebimento" />
    <form class="flex max-w-xl flex-col gap-4 p-4" @submit.prevent="submit">
        <h1 class="text-xl font-semibold">Registrar recebimento</h1>

        <p class="text-sm text-muted-foreground">
            Pedido #{{ order.id }} — {{ order.product?.name }} — saldo {{ saldo }}
        </p>

        <label class="flex flex-col gap-1 text-sm">
            <span>Armazém</span>
            <select
                v-model="form.warehouse_id"
                :disabled="warehouseLocked"
                class="rounded-md border bg-background px-3 py-2"
            >
                <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
            </select>
            <span v-if="warehouseLocked" class="text-xs text-muted-foreground">
                Armazém fixado pelo primeiro recebimento.
            </span>
            <span v-if="form.errors.warehouse_id" class="text-xs text-destructive">{{ form.errors.warehouse_id }}</span>
        </label>

        <label class="flex flex-col gap-1 text-sm">
            <span>Quantidade (máx. {{ saldo }})</span>
            <input
                v-model.number="form.quantity"
                type="number"
                :max="saldo"
                min="1"
                class="rounded-md border bg-background px-3 py-2"
            />
            <span v-if="form.errors.quantity" class="text-xs text-destructive">{{ form.errors.quantity }}</span>
        </label>

        <label class="flex flex-col gap-1 text-sm">
            <span>Fotos (1 a 10, até 10 MB cada)</span>
            <input
                ref="photosInput"
                type="file"
                multiple
                accept="image/*,.heic,.heif"
                class="rounded-md border bg-background px-3 py-2"
                @change="onPhotos"
            />
            <span v-if="form.errors.photos" class="text-xs text-destructive">{{ form.errors.photos }}</span>
        </label>

        <div class="flex gap-2">
            <button
                type="submit"
                :disabled="form.processing"
                class="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
            >
                Registrar
            </button>
        </div>
    </form>
</template>
