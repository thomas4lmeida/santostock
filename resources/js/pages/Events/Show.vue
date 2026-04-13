<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import * as EventController from '@/actions/App/Http/Controllers/EventController';
import { formatDateTimeBR } from '@/lib/format';

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
            { title: 'Detalhes', href: '#' },
        ],
    },
});

function destroy() {
    if (!confirm('Excluir este evento?')) {
        return;
    }

    router.delete(EventController.destroy.url({ event: props.event.id }));
}
</script>

<template>
    <Head :title="props.event.name" />
    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">{{ props.event.name }}</h1>
            <div class="flex gap-2">
                <Link
                    :href="EventController.edit.url({ event: props.event.id })"
                    class="rounded-md border border-input px-3 py-1.5 text-sm hover:bg-muted"
                >
                    Editar
                </Link>
                <button
                    type="button"
                    class="rounded-md bg-destructive px-3 py-1.5 text-sm font-medium text-destructive-foreground hover:opacity-90"
                    @click="destroy"
                >
                    Excluir
                </button>
            </div>
        </div>

        <dl
            class="grid grid-cols-1 gap-3 rounded-xl border border-sidebar-border/70 p-4 sm:grid-cols-2 dark:border-sidebar-border"
        >
            <div>
                <dt class="text-xs font-medium text-muted-foreground">Local</dt>
                <dd class="text-sm">{{ props.event.venue }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-muted-foreground">
                    Início
                </dt>
                <dd class="text-sm">
                    {{ formatDateTimeBR(props.event.starts_at) }}
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-muted-foreground">Fim</dt>
                <dd class="text-sm">
                    {{ formatDateTimeBR(props.event.ends_at) }}
                </dd>
            </div>
            <div v-if="props.event.description" class="sm:col-span-2">
                <dt class="text-xs font-medium text-muted-foreground">
                    Descrição
                </dt>
                <dd class="text-sm whitespace-pre-line">
                    {{ props.event.description }}
                </dd>
            </div>
        </dl>
    </div>
</template>
