<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import * as WarehouseController from '@/actions/App/Http/Controllers/Warehouses/WarehouseController';
import type { Paginated } from '@/types/pagination';

interface Warehouse {
    id: number;
    name: string;
}

defineProps<{ warehouses: Paginated<Warehouse> }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Armazéns', href: WarehouseController.index.url() }],
    },
});

const page = usePage<{ auth: { user: { role: string | null } | null }; errors: { delete?: string } }>();
const isAdmin = computed(() => page.props.auth.user?.role === 'administrador');
const deleteError = computed(() => page.props.errors?.delete ?? null);

function destroy(warehouse: Warehouse) {
    if (!confirm('Excluir este armazém?')) {
        return;
    }

    router.delete(WarehouseController.destroy.url({ warehouse: warehouse.id }));
}
</script>

<template>
    <Head title="Armazéns" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Armazéns</h1>
            <Link
                v-if="isAdmin"
                :href="WarehouseController.create.url()"
                class="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:opacity-90"
            >
                Novo armazém
            </Link>
        </div>

        <p v-if="deleteError" class="text-sm text-destructive">
            {{ deleteError }}
        </p>

        <div
            class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <table class="w-full text-left text-sm">
                <thead class="border-b border-sidebar-border/70 bg-muted/50">
                    <tr>
                        <th class="px-3 py-2">Nome</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="warehouse in warehouses.data"
                        :key="warehouse.id"
                        class="border-b border-sidebar-border/40 last:border-0"
                    >
                        <td class="px-3 py-2">{{ warehouse.name }}</td>
                        <td class="px-3 py-2 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <Link
                                    :href="
                                        WarehouseController.show.url({
                                            warehouse: warehouse.id,
                                        })
                                    "
                                    class="text-primary hover:underline"
                                >
                                    Ver
                                </Link>
                                <Link
                                    v-if="isAdmin"
                                    :href="
                                        WarehouseController.edit.url({
                                            warehouse: warehouse.id,
                                        })
                                    "
                                    class="text-primary hover:underline"
                                >
                                    Editar
                                </Link>
                                <button
                                    v-if="isAdmin"
                                    type="button"
                                    class="text-destructive hover:underline"
                                    @click="destroy(warehouse)"
                                >
                                    Excluir
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="warehouses.data.length === 0">
                        <td
                            colspan="2"
                            class="px-3 py-6 text-center text-sm text-muted-foreground"
                        >
                            Nenhum armazém cadastrado.
                            <Link
                                v-if="isAdmin"
                                :href="WarehouseController.create.url()"
                                class="text-primary hover:underline"
                            >
                                Criar armazém
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
