<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import * as EventController from '@/actions/App/Http/Controllers/EventController';
import EventForm from './EventForm.vue';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Eventos', href: EventController.index.url() },
            { title: 'Novo', href: EventController.create.url() },
        ],
    },
});
</script>

<template>
    <Head title="Novo evento" />
    <div class="flex flex-col gap-4 p-4">
        <h1 class="text-xl font-semibold">Novo evento</h1>
        <EventForm
            :submit-url="EventController.store.url()"
            method="post"
            submit-label="Criar evento"
        />
    </div>
</template>
