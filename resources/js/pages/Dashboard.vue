<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Painel',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Painel" />

    <div class="flex h-full flex-1 flex-col items-center justify-center gap-2 p-8 text-center">
        <h1 class="text-2xl font-semibold">Bem-vindo ao Santostok</h1>
        <p class="text-sm text-muted-foreground">
            O painel será preenchido com indicadores de estoque em breve.
        </p>
    </div>
</template>
