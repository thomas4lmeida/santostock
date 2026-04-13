<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import * as SupplierController from '@/actions/App/Http/Controllers/Suppliers/SupplierController';

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
            { title: 'Detalhes', href: '#' },
        ],
    },
});
</script>

<template>
    <Head :title="props.supplier.name" />
    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">{{ props.supplier.name }}</h1>
            <Link
                :href="
                    SupplierController.edit.url({ supplier: props.supplier.id })
                "
                class="rounded-md border border-input px-3 py-1.5 text-sm hover:bg-muted"
            >
                Editar
            </Link>
        </div>
        <dl
            class="grid grid-cols-1 gap-3 rounded-xl border border-sidebar-border/70 p-4 sm:grid-cols-2 dark:border-sidebar-border"
        >
            <div>
                <dt class="text-xs font-medium text-muted-foreground">
                    Contato
                </dt>
                <dd class="text-sm">
                    {{ props.supplier.contact_name ?? '—' }}
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-muted-foreground">
                    Telefone
                </dt>
                <dd class="text-sm">{{ props.supplier.phone ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-muted-foreground">
                    E-mail
                </dt>
                <dd class="text-sm">{{ props.supplier.email ?? '—' }}</dd>
            </div>
            <div v-if="props.supplier.notes" class="sm:col-span-2">
                <dt class="text-xs font-medium text-muted-foreground">
                    Observações
                </dt>
                <dd class="text-sm whitespace-pre-line">
                    {{ props.supplier.notes }}
                </dd>
            </div>
        </dl>
    </div>
</template>
