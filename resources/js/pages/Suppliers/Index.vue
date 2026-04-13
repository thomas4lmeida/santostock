<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import * as SupplierController from '@/actions/App/Http/Controllers/Suppliers/SupplierController';
import type { Paginated } from '@/types/pagination';

interface Supplier {
    id: number;
    name: string;
    contact_name: string | null;
    phone: string | null;
    email: string | null;
}

defineProps<{ suppliers: Paginated<Supplier> }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Fornecedores', href: SupplierController.index.url() },
        ],
    },
});
</script>

<template>
    <Head title="Fornecedores" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Fornecedores</h1>
            <Link
                :href="SupplierController.create.url()"
                class="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:opacity-90"
            >
                Novo fornecedor
            </Link>
        </div>

        <div
            class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <table class="w-full text-left text-sm">
                <thead class="border-b border-sidebar-border/70 bg-muted/50">
                    <tr>
                        <th class="px-3 py-2">Nome</th>
                        <th class="px-3 py-2">Contato</th>
                        <th class="px-3 py-2">Telefone</th>
                        <th class="px-3 py-2">E-mail</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="supplier in suppliers.data"
                        :key="supplier.id"
                        class="border-b border-sidebar-border/40 last:border-0"
                    >
                        <td class="px-3 py-2">{{ supplier.name }}</td>
                        <td class="px-3 py-2">
                            {{ supplier.contact_name ?? '—' }}
                        </td>
                        <td class="px-3 py-2">{{ supplier.phone ?? '—' }}</td>
                        <td class="px-3 py-2">{{ supplier.email ?? '—' }}</td>
                        <td class="px-3 py-2 text-right">
                            <Link
                                :href="
                                    SupplierController.edit.url({
                                        supplier: supplier.id,
                                    })
                                "
                                class="text-primary hover:underline"
                            >
                                Editar
                            </Link>
                        </td>
                    </tr>
                    <tr v-if="suppliers.data.length === 0">
                        <td
                            colspan="5"
                            class="px-3 py-6 text-center text-sm text-muted-foreground"
                        >
                            Nenhum fornecedor cadastrado.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
