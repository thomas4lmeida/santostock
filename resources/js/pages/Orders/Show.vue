<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import * as OrderController from '@/actions/App/Http/Controllers/Orders/OrderController';
import CancelOrderController from '@/actions/App/Http/Controllers/Orders/CancelOrderController';
import CloseShortOrderController from '@/actions/App/Http/Controllers/Orders/CloseShortOrderController';
import * as ReceiptController from '@/actions/App/Http/Controllers/Receipts/ReceiptController';
import CorrectReceiptController from '@/actions/App/Http/Controllers/Receipts/CorrectReceiptController';
import * as AttachmentViewController from '@/actions/App/Http/Controllers/Attachments/AttachmentViewController';

interface Attachment {
    id: number;
    thumbnail_path: string | null;
    original_filename: string;
}

interface Receipt {
    id: number;
    quantity: number;
    reason: string | null;
    corrects_receipt_id: number | null;
    attachments: Attachment[];
}

interface Order {
    id: number;
    supplier_id: number;
    product_id: number;
    warehouse_id: number | null;
    ordered_quantity: number;
    status: string;
    status_label: string;
    notes: string | null;
    supplier: { id: number; name: string } | null;
    product: { id: number; name: string } | null;
    warehouse: { id: number; name: string } | null;
    receipts: Receipt[];
}

const props = defineProps<{
    order: Order;
    saldo: number;
    canCancel: boolean;
    canCloseShort: boolean;
    canCreateReceipt: boolean;
    canCorrectReceipt: boolean;
}>();

const page = usePage<{ auth: { user: { role: string | null } | null } }>();
const isAdmin = computed(() => page.props.auth?.user?.role === 'administrador');

const positiveReceipts = computed(() => props.order.receipts.filter((r) => r.quantity > 0));
const corrections = computed(() => props.order.receipts.filter((r) => r.quantity < 0));

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

function correctReceipt(receipt: Receipt) {
    const reason = prompt('Motivo da correção (obrigatório)');
    if (!reason) return;
    const delta = Number(prompt('Delta (negativo)', String(-receipt.quantity)));
    if (!delta || delta >= 0) return;
    router.post(CorrectReceiptController.url({ receipt: receipt.id }), {
        delta_quantity: delta,
        reason,
    });
}
</script>

<template>
    <Head :title="`Pedido #${props.order.id}`" />
    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Pedido #{{ props.order.id }}</h1>
            <div class="flex gap-2">
                <a
                    v-if="canCreateReceipt"
                    :href="ReceiptController.create.url({ order: props.order.id })"
                    class="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:opacity-90"
                >
                    Registrar recebimento
                </a>
                <button
                    v-if="isAdmin && canCancel"
                    type="button"
                    class="rounded-md bg-destructive px-3 py-1.5 text-sm font-medium text-destructive-foreground hover:opacity-90"
                    @click="cancel"
                >
                    Cancelar
                </button>
                <button
                    v-if="isAdmin && canCloseShort"
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
            <dt class="text-muted-foreground">Armazém</dt>
            <dd>{{ order.warehouse?.name ?? '—' }}</dd>
            <dt class="text-muted-foreground">Quantidade</dt>
            <dd>{{ order.ordered_quantity }}</dd>
            <dt class="text-muted-foreground">Saldo</dt>
            <dd>{{ saldo }}</dd>
            <dt class="text-muted-foreground">Status</dt>
            <dd>{{ order.status_label }}</dd>
            <dt class="text-muted-foreground">Observações</dt>
            <dd>{{ order.notes ?? '—' }}</dd>
        </dl>

        <section v-if="positiveReceipts.length" class="flex flex-col gap-2">
            <h2 class="text-lg font-medium">Recebimentos</h2>
            <ul class="flex flex-col gap-2">
                <li
                    v-for="receipt in positiveReceipts"
                    :key="receipt.id"
                    class="rounded-md border p-3 text-sm"
                >
                    <div class="flex items-center justify-between">
                        <span>Quantidade: {{ receipt.quantity }}</span>
                        <button
                            v-if="canCorrectReceipt"
                            type="button"
                            class="rounded-md border px-2 py-1 text-xs hover:bg-muted"
                            @click="correctReceipt(receipt)"
                        >
                            Estornar
                        </button>
                    </div>
                    <div v-if="receipt.attachments.length" class="mt-2 flex flex-wrap gap-2">
                        <a
                            v-for="att in receipt.attachments"
                            :key="att.id"
                            :href="AttachmentViewController.original.url({ attachment: att.id })"
                            target="_blank"
                            class="block"
                        >
                            <img
                                v-if="att.thumbnail_path"
                                :src="AttachmentViewController.thumbnail.url({ attachment: att.id })"
                                :alt="att.original_filename"
                                class="h-16 w-16 rounded object-cover"
                            />
                            <span v-else class="inline-flex h-16 w-16 items-center justify-center rounded bg-muted text-xs">
                                Processando…
                            </span>
                        </a>
                    </div>
                </li>
            </ul>
        </section>

        <section v-if="corrections.length" class="flex flex-col gap-2">
            <h2 class="text-lg font-medium">Ajustes</h2>
            <ul class="flex flex-col gap-2">
                <li
                    v-for="correction in corrections"
                    :key="correction.id"
                    class="rounded-md border border-dashed p-3 text-sm"
                >
                    <div>Delta: {{ correction.quantity }}</div>
                    <div v-if="correction.reason" class="text-muted-foreground">{{ correction.reason }}</div>
                </li>
            </ul>
        </section>
    </div>
</template>
