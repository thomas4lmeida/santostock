<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import * as WarehouseController from '@/actions/App/Http/Controllers/Warehouses/WarehouseController';
import WarehouseForm from './WarehouseForm.vue';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Armazéns', href: WarehouseController.index.url() },
            { title: 'Novo armazém', href: WarehouseController.create.url() },
        ],
    },
});
</script>

<template>
    <Head title="Novo armazém" />
    <div class="flex flex-col gap-4 p-4">
        <h1 class="text-xl font-semibold">Novo armazém</h1>
        <WarehouseForm
            :submit-url="WarehouseController.store.url()"
            method="post"
            submit-label="Criar armazém"
        />
    </div>
</template>
