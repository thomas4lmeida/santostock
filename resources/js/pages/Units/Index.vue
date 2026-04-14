<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import * as UnitController from '@/actions/App/Http/Controllers/Units/UnitController';
import type { Paginated } from '@/types/pagination';

interface Unit {
    id: number;
    name: string;
    abbreviation: string;
}

defineProps<{ units: Paginated<Unit> }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Unidades', href: UnitController.index.url() }],
    },
});

const page = usePage<{ errors: { delete?: string } }>();
const deleteError = computed(() => page.props.errors?.delete ?? null);

function destroy(unit: Unit) {
    if (!confirm('Excluir esta unidade?')) {
        return;
    }

    router.delete(UnitController.destroy.url({ unit: unit.id }));
}
</script>

<template>
    <Head title="Unidades" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Unidades</h1>
            <Link
                :href="UnitController.create.url()"
                class="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:opacity-90"
            >
                Nova unidade
            </Link>
        </div>

        <p v-if="deleteError" class="text-sm text-destructive">
            {{ deleteError }}
        </p>

        <div
            class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <table class="w-full text-left text-sm">
                <thead class="border-b border-sidebar-border/70 bg-muted/50">
                    <tr>
                        <th class="px-3 py-2">Nome</th>
                        <th class="px-3 py-2">Abreviação</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="unit in units.data"
                        :key="unit.id"
                        class="border-b border-sidebar-border/40 last:border-0"
                    >
                        <td class="px-3 py-2">{{ unit.name }}</td>
                        <td class="px-3 py-2">{{ unit.abbreviation }}</td>
                        <td class="px-3 py-2 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <Link
                                    :href="
                                        UnitController.show.url({
                                            unit: unit.id,
                                        })
                                    "
                                    class="text-primary hover:underline"
                                >
                                    Ver
                                </Link>
                                <Link
                                    :href="
                                        UnitController.edit.url({
                                            unit: unit.id,
                                        })
                                    "
                                    class="text-primary hover:underline"
                                >
                                    Editar
                                </Link>
                                <button
                                    type="button"
                                    class="text-destructive hover:underline"
                                    @click="destroy(unit)"
                                >
                                    Excluir
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="units.data.length === 0">
                        <td
                            colspan="3"
                            class="px-3 py-6 text-center text-sm text-muted-foreground"
                        >
                            Nenhuma unidade cadastrada.
                            <Link
                                :href="UnitController.create.url()"
                                class="text-primary hover:underline"
                            >
                                Criar unidade
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
