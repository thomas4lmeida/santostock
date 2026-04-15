<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

interface Option {
    id: number;
    name: string;
}

interface OrderValues {
    id?: number;
    supplier_id: number | null;
    product_id: number | null;
    ordered_quantity: number | null;
    notes: string | null;
}

const props = withDefaults(
    defineProps<{
        initial?: Partial<OrderValues>;
        suppliers: Option[];
        products: Option[];
        canEditQuantity?: boolean;
        submitUrl: string;
        method: 'post' | 'put';
        submitLabel: string;
    }>(),
    {
        // Vue coerces absent boolean props to false; default to true so
        // pages that omit this prop (Create) keep the field editable.
        canEditQuantity: true,
    },
);

const form = useForm({
    supplier_id: props.initial?.supplier_id ?? null,
    product_id: props.initial?.product_id ?? null,
    ordered_quantity: props.initial?.ordered_quantity ?? null,
    notes: props.initial?.notes ?? '',
});

function submit() {
    form[props.method](props.submitUrl);
}
</script>

<template>
    <form class="flex max-w-2xl flex-col gap-4" @submit.prevent="submit">
        <div class="flex flex-col gap-1">
            <label class="text-sm font-medium" for="supplier_id">Fornecedor</label>
            <select
                id="supplier_id"
                v-model="form.supplier_id"
                class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                required
            >
                <option :value="null" disabled>Selecione…</option>
                <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
            <p v-if="form.errors.supplier_id" class="text-xs text-destructive">
                {{ form.errors.supplier_id }}
            </p>
        </div>

        <div class="flex flex-col gap-1">
            <label class="text-sm font-medium" for="product_id">Produto</label>
            <select
                id="product_id"
                v-model="form.product_id"
                class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                required
            >
                <option :value="null" disabled>Selecione…</option>
                <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
            <p v-if="form.errors.product_id" class="text-xs text-destructive">
                {{ form.errors.product_id }}
            </p>
        </div>

        <div class="flex flex-col gap-1">
            <label class="text-sm font-medium" for="ordered_quantity">Quantidade</label>
            <input
                id="ordered_quantity"
                v-model.number="form.ordered_quantity"
                type="number"
                min="1"
                :disabled="!canEditQuantity"
                class="rounded-md border border-input bg-background px-3 py-2 text-sm disabled:opacity-60"
                required
            />
            <p v-if="!canEditQuantity" class="text-xs text-muted-foreground">
                Quantidade bloqueada: existem recebimentos registrados.
            </p>
            <p v-if="form.errors.ordered_quantity" class="text-xs text-destructive">
                {{ form.errors.ordered_quantity }}
            </p>
        </div>

        <div class="flex flex-col gap-1">
            <label class="text-sm font-medium" for="notes">Observações</label>
            <textarea
                id="notes"
                v-model="form.notes"
                rows="3"
                class="rounded-md border border-input bg-background px-3 py-2 text-sm"
            />
        </div>

        <div>
            <button
                type="submit"
                :disabled="form.processing"
                class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
            >
                {{ submitLabel }}
            </button>
        </div>
    </form>
</template>
