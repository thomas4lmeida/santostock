<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import * as UnitController from '@/actions/App/Http/Controllers/Units/UnitController';
import UnitForm from './UnitForm.vue';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Unidades', href: UnitController.index.url() },
            { title: 'Nova unidade', href: UnitController.create.url() },
        ],
    },
});
</script>

<template>
    <Head title="Nova unidade" />
    <div class="flex flex-col gap-4 p-4">
        <h1 class="text-xl font-semibold">Nova unidade</h1>
        <UnitForm
            :submit-url="UnitController.store.url()"
            method="post"
            submit-label="Criar unidade"
        />
    </div>
</template>
