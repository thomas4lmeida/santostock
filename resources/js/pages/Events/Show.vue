<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import * as EventController from '@/actions/App/Http/Controllers/EventController';
import * as EventItemController from '@/actions/App/Http/Controllers/EventItems/EventItemController';
import { formatBRL, formatDateTimeBR } from '@/lib/format';

interface Event {
    id: number;
    name: string;
    description: string | null;
    venue: string;
    starts_at: string;
    ends_at: string;
}

interface Category {
    id: number;
    name: string;
}

interface Supplier {
    id: number;
    name: string;
}

interface Item {
    id: number;
    name: string;
    quantity: number;
    rental_cost_cents: number;
    condition: 'available' | 'in_use' | 'returned';
    supplier: Supplier | null;
}

interface ItemGroup {
    category: Category;
    items: Item[];
}

const props = defineProps<{
    event: Event;
    itemGroups: ItemGroup[];
    categories: Category[];
    suppliers: Supplier[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Eventos', href: EventController.index.url() },
            { title: 'Detalhes', href: '#' },
        ],
    },
});

const form = useForm({
    name: '',
    item_category_id: '' as number | '',
    supplier_id: '' as number | '',
    quantity: 1,
    rental_cost_cents: 0,
});

function submitItem() {
    form.post(EventItemController.store.url({ event: props.event.id }), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function destroyItem(item: Item) {
    if (!confirm(`Excluir "${item.name}"?`)) {
        return;
    }

    router.delete(
        EventItemController.destroy.url({
            event: props.event.id,
            item: item.id,
        }),
        { preserveScroll: true },
    );
}

function destroyEvent() {
    if (!confirm('Excluir este evento?')) {
        return;
    }

    router.delete(EventController.destroy.url({ event: props.event.id }));
}

const conditionLabel: Record<Item['condition'], string> = {
    available: 'Disponível',
    in_use: 'Em uso',
    returned: 'Devolvido',
};
</script>

<template>
    <Head :title="props.event.name" />
    <div class="flex flex-col gap-6 p-4">
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
                    @click="destroyEvent"
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

        <section class="flex flex-col gap-3">
            <h2 class="text-lg font-semibold">Itens do evento</h2>

            <div
                v-if="itemGroups.length === 0"
                class="rounded-md border border-dashed border-sidebar-border/70 p-6 text-center text-sm text-muted-foreground"
            >
                Nenhum item adicionado.
            </div>

            <div
                v-for="group in itemGroups"
                :key="group.category.id"
                class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <div
                    class="border-b border-sidebar-border/70 bg-muted/50 px-3 py-2 text-sm font-medium"
                >
                    {{ group.category.name }}
                </div>
                <table class="w-full text-left text-sm">
                    <thead class="text-xs text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2">Item</th>
                            <th class="px-3 py-2">Qtd</th>
                            <th class="px-3 py-2">Custo unit.</th>
                            <th class="px-3 py-2">Fornecedor</th>
                            <th class="px-3 py-2">Condição</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in group.items"
                            :key="item.id"
                            class="border-t border-sidebar-border/40"
                        >
                            <td class="px-3 py-2">{{ item.name }}</td>
                            <td class="px-3 py-2">{{ item.quantity }}</td>
                            <td class="px-3 py-2">
                                {{ formatBRL(item.rental_cost_cents) }}
                            </td>
                            <td class="px-3 py-2">
                                {{ item.supplier?.name ?? '—' }}
                            </td>
                            <td class="px-3 py-2">
                                {{ conditionLabel[item.condition] }}
                            </td>
                            <td class="px-3 py-2 text-right">
                                <button
                                    type="button"
                                    class="text-xs text-destructive hover:underline"
                                    @click="destroyItem(item)"
                                >
                                    Remover
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section
            class="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <h2 class="text-base font-semibold">Adicionar item</h2>
            <p
                v-if="categories.length === 0"
                class="text-xs text-muted-foreground"
            >
                Cadastre uma categoria primeiro em Categorias.
            </p>
            <form
                class="grid gap-3 sm:grid-cols-2"
                @submit.prevent="submitItem"
            >
                <div class="flex flex-col gap-1">
                    <label class="text-sm font-medium" for="item-name"
                        >Nome</label
                    >
                    <input
                        id="item-name"
                        v-model="form.name"
                        type="text"
                        class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                        required
                    />
                    <p v-if="form.errors.name" class="text-xs text-destructive">
                        {{ form.errors.name }}
                    </p>
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-sm font-medium" for="item-category"
                        >Categoria</label
                    >
                    <select
                        id="item-category"
                        v-model="form.item_category_id"
                        class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                        required
                    >
                        <option value="" disabled>Selecione…</option>
                        <option
                            v-for="category in categories"
                            :key="category.id"
                            :value="category.id"
                        >
                            {{ category.name }}
                        </option>
                    </select>
                    <p
                        v-if="form.errors.item_category_id"
                        class="text-xs text-destructive"
                    >
                        {{ form.errors.item_category_id }}
                    </p>
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-sm font-medium" for="item-supplier"
                        >Fornecedor</label
                    >
                    <select
                        id="item-supplier"
                        v-model="form.supplier_id"
                        class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                    >
                        <option value="">Sem fornecedor</option>
                        <option
                            v-for="supplier in suppliers"
                            :key="supplier.id"
                            :value="supplier.id"
                        >
                            {{ supplier.name }}
                        </option>
                    </select>
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-sm font-medium" for="item-quantity"
                        >Quantidade</label
                    >
                    <input
                        id="item-quantity"
                        v-model.number="form.quantity"
                        type="number"
                        min="1"
                        class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                        required
                    />
                    <p
                        v-if="form.errors.quantity"
                        class="text-xs text-destructive"
                    >
                        {{ form.errors.quantity }}
                    </p>
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-sm font-medium" for="item-cost"
                        >Custo unitário (centavos)</label
                    >
                    <input
                        id="item-cost"
                        v-model.number="form.rental_cost_cents"
                        type="number"
                        min="0"
                        class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                        required
                    />
                    <p
                        v-if="form.errors.rental_cost_cents"
                        class="text-xs text-destructive"
                    >
                        {{ form.errors.rental_cost_cents }}
                    </p>
                </div>
                <div class="sm:col-span-2">
                    <button
                        type="submit"
                        :disabled="form.processing || categories.length === 0"
                        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
                    >
                        Adicionar item
                    </button>
                </div>
            </form>
        </section>
    </div>
</template>
