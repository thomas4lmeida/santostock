<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import * as WarehouseController from '@/actions/App/Http/Controllers/Warehouses/WarehouseController';
import WarehouseForm from './WarehouseForm.vue';

interface Warehouse {
    id: number;
    name: string;
}

const props = defineProps<{ warehouse: Warehouse }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Armazéns', href: WarehouseController.index.url() },
            { title: 'Editar', href: '#' },
        ],
    },
});
</script>

<template>
    <Head :title="`Editar ${props.warehouse.name}`" />
    <div class="flex flex-col gap-4 p-4">
        <h1 class="text-xl font-semibold">Editar armazém</h1>
        <WarehouseForm
            :warehouse="props.warehouse"
            :submit-url="WarehouseController.update.url({ warehouse: props.warehouse.id })"
            method="put"
            submit-label="Salvar alterações"
        />
    </div>
</template>
