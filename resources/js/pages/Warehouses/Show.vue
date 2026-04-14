<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import * as WarehouseController from '@/actions/App/Http/Controllers/Warehouses/WarehouseController';

interface Warehouse {
    id: number;
    name: string;
}

const props = defineProps<{ warehouse: Warehouse }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Armazéns', href: WarehouseController.index.url() },
            { title: 'Detalhes', href: '#' },
        ],
    },
});

const page = usePage<{ auth: { user: { role: string | null } | null } }>();
const isAdmin = computed(() => page.props.auth.user?.role === 'administrador');
</script>

<template>
    <Head :title="props.warehouse.name" />
    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">{{ props.warehouse.name }}</h1>
            <div class="flex items-center gap-2">
                <Link
                    v-if="isAdmin"
                    :href="WarehouseController.edit.url({ warehouse: props.warehouse.id })"
                    class="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:opacity-90"
                >
                    Editar
                </Link>
                <Link
                    :href="WarehouseController.index.url()"
                    class="rounded-md border border-input px-3 py-1.5 text-sm hover:bg-muted"
                >
                    Voltar
                </Link>
            </div>
        </div>

        <div
            class="flex flex-col gap-4 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <div>
                <p class="text-xs font-medium text-muted-foreground">Nome</p>
                <p class="text-sm">{{ props.warehouse.name }}</p>
            </div>
        </div>
    </div>
</template>
