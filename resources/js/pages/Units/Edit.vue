<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import * as UnitController from '@/actions/App/Http/Controllers/Units/UnitController';
import UnitForm from './UnitForm.vue';

interface Unit {
    id: number;
    name: string;
    abbreviation: string;
}

const props = defineProps<{ unit: Unit }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Unidades', href: UnitController.index.url() },
            { title: 'Editar', href: '#' },
        ],
    },
});
</script>

<template>
    <Head :title="`Editar ${props.unit.name}`" />
    <div class="flex flex-col gap-4 p-4">
        <h1 class="text-xl font-semibold">Editar unidade</h1>
        <UnitForm
            :unit="props.unit"
            :submit-url="UnitController.update.url({ unit: props.unit.id })"
            method="put"
            submit-label="Salvar alterações"
        />
    </div>
</template>
