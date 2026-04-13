<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { onUnmounted, reactive, watch } from 'vue';
import * as EventController from '@/actions/App/Http/Controllers/EventController';
import { formatDateTimeBR } from '@/lib/format';

interface Event {
    id: number;
    name: string;
    venue: string;
    starts_at: string;
    ends_at: string;
}

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
}

const props = defineProps<{
    events: Paginated<Event>;
    filters: { status?: string; from?: string; to?: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Eventos', href: EventController.index.url() }],
    },
});

const form = reactive({
    status: props.filters.status ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});

let timer: ReturnType<typeof setTimeout> | null = null;
watch(form, () => {
    if (timer) {
        clearTimeout(timer);
    }

    timer = setTimeout(() => {
        router.get(EventController.index.url(), form, {
            preserveState: true,
            replace: true,
        });
    }, 200);
});
onUnmounted(() => {
    if (timer) {
        clearTimeout(timer);
    }
});
</script>

<template>
    <Head title="Eventos" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Eventos</h1>
            <Link
                :href="EventController.create.url()"
                class="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:opacity-90"
            >
                Novo evento
            </Link>
        </div>

        <div class="flex flex-wrap gap-2">
            <select
                v-model="form.status"
                class="rounded-md border border-input bg-background px-2 py-1 text-sm"
            >
                <option value="">Todos</option>
                <option value="upcoming">Próximos</option>
                <option value="ongoing">Em andamento</option>
                <option value="past">Passados</option>
            </select>
            <input
                v-model="form.from"
                type="date"
                class="rounded-md border border-input bg-background px-2 py-1 text-sm"
            />
            <input
                v-model="form.to"
                type="date"
                class="rounded-md border border-input bg-background px-2 py-1 text-sm"
            />
        </div>

        <div
            class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <table class="w-full text-left text-sm">
                <thead class="border-b border-sidebar-border/70 bg-muted/50">
                    <tr>
                        <th class="px-3 py-2">Nome</th>
                        <th class="px-3 py-2">Local</th>
                        <th class="px-3 py-2">Início</th>
                        <th class="px-3 py-2">Fim</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="event in events.data"
                        :key="event.id"
                        class="border-b border-sidebar-border/40 last:border-0"
                    >
                        <td class="px-3 py-2">{{ event.name }}</td>
                        <td class="px-3 py-2">{{ event.venue }}</td>
                        <td class="px-3 py-2">
                            {{ formatDateTimeBR(event.starts_at) }}
                        </td>
                        <td class="px-3 py-2">
                            {{ formatDateTimeBR(event.ends_at) }}
                        </td>
                        <td class="px-3 py-2 text-right">
                            <Link
                                :href="
                                    EventController.show.url({
                                        event: event.id,
                                    })
                                "
                                class="text-primary hover:underline"
                                >Ver</Link
                            >
                            <Link
                                :href="
                                    EventController.edit.url({
                                        event: event.id,
                                    })
                                "
                                class="ml-2 text-primary hover:underline"
                                >Editar</Link
                            >
                        </td>
                    </tr>
                    <tr v-if="events.data.length === 0">
                        <td
                            colspan="5"
                            class="px-3 py-6 text-center text-sm text-muted-foreground"
                        >
                            Nenhum evento encontrado.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
