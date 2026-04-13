<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import * as SupplierController from '@/actions/App/Http/Controllers/Suppliers/SupplierController';
import SupplierForm from './SupplierForm.vue';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Fornecedores', href: SupplierController.index.url() },
            { title: 'Novo', href: SupplierController.create.url() },
        ],
    },
});
</script>

<template>
    <Head title="Novo fornecedor" />
    <div class="flex flex-col gap-4 p-4">
        <h1 class="text-xl font-semibold">Novo fornecedor</h1>
        <SupplierForm
            :submit-url="SupplierController.store.url()"
            method="post"
            submit-label="Criar fornecedor"
        />
    </div>
</template>
