<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import * as EventController from '@/actions/App/Http/Controllers/EventController';
import EventForm from './EventForm.vue';

interface Event {
    id: number;
    name: string;
    description: string | null;
    venue: string;
    starts_at: string;
    ends_at: string;
}

const props = defineProps<{ event: Event }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Eventos', href: EventController.index.url() },
            { title: 'Editar', href: '#' },
        ],
    },
});
</script>

<template>
    <Head :title="`Editar ${props.event.name}`" />
    <div class="flex flex-col gap-4 p-4">
        <h1 class="text-xl font-semibold">Editar evento</h1>
        <EventForm
            :initial="props.event"
            :submit-url="EventController.update.url({ event: props.event.id })"
            method="put"
            submit-label="Salvar alterações"
        />
    </div>
</template>
