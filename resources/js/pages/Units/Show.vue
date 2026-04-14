<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import * as UnitController from '@/actions/App/Http/Controllers/Units/UnitController';

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
            { title: 'Detalhes', href: '#' },
        ],
    },
});
</script>

<template>
    <Head :title="props.unit.name" />
    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">{{ props.unit.name }}</h1>
            <div class="flex items-center gap-2">
                <Link
                    :href="UnitController.edit.url({ unit: props.unit.id })"
                    class="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:opacity-90"
                >
                    Editar
                </Link>
                <Link
                    :href="UnitController.index.url()"
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
                <p class="text-sm">{{ props.unit.name }}</p>
            </div>

            <div>
                <p class="text-xs font-medium text-muted-foreground">
                    Abreviação
                </p>
                <p class="text-sm">{{ props.unit.abbreviation }}</p>
            </div>
        </div>
    </div>
</template>
