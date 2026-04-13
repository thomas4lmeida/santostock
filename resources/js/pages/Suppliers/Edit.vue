<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import * as SupplierController from '@/actions/App/Http/Controllers/Suppliers/SupplierController';
import SupplierForm from './SupplierForm.vue';

interface Supplier {
    id: number;
    name: string;
    contact_name: string | null;
    phone: string | null;
    email: string | null;
    notes: string | null;
}

const props = defineProps<{ supplier: Supplier }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Fornecedores', href: SupplierController.index.url() },
            { title: 'Editar', href: '#' },
        ],
    },
});

function destroy() {
    if (!confirm('Excluir este fornecedor?')) {
        return;
    }

    router.delete(
        SupplierController.destroy.url({ supplier: props.supplier.id }),
    );
}
</script>

<template>
    <Head :title="`Editar ${props.supplier.name}`" />
    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Editar fornecedor</h1>
            <button
                type="button"
                class="rounded-md bg-destructive px-3 py-1.5 text-sm font-medium text-destructive-foreground hover:opacity-90"
                @click="destroy"
            >
                Excluir
            </button>
        </div>
        <SupplierForm
            :initial="props.supplier"
            :submit-url="
                SupplierController.update.url({ supplier: props.supplier.id })
            "
            method="put"
            submit-label="Salvar alterações"
        />
    </div>
</template>
