<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import * as ProductController from '@/actions/App/Http/Controllers/Products/ProductController';

interface ItemCategory {
    id: number;
    name: string;
}

interface Unit {
    id: number;
    name: string;
    abbreviation: string;
}

interface ProductValues {
    name: string;
    item_category_id: number | null;
    unit_id: number | null;
}

const props = defineProps<{
    product?: Partial<ProductValues> & { id?: number };
    itemCategories: ItemCategory[];
    units: Unit[];
    submitAction: { url: string; method: 'post' | 'put' };
    submitLabel: string;
}>();

const form = useForm({
    name: props.product?.name ?? '',
    item_category_id: props.product?.item_category_id ?? null,
    unit_id: props.product?.unit_id ?? null,
});

function submit() {
    form[props.submitAction.method](props.submitAction.url);
}
</script>

<template>
    <form class="flex max-w-2xl flex-col gap-4" @submit.prevent="submit">
        <div class="flex flex-col gap-1">
            <label class="text-sm font-medium" for="name">Nome</label>
            <input
                id="name"
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
            <label class="text-sm font-medium" for="item_category_id">Categoria</label>
            <select
                id="item_category_id"
                v-model="form.item_category_id"
                class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                required
            >
                <option :value="null" disabled>Selecione uma categoria</option>
                <option
                    v-for="category in itemCategories"
                    :key="category.id"
                    :value="category.id"
                >
                    {{ category.name }}
                </option>
            </select>
            <p v-if="form.errors.item_category_id" class="text-xs text-destructive">
                {{ form.errors.item_category_id }}
            </p>
        </div>

        <div class="flex flex-col gap-1">
            <label class="text-sm font-medium" for="unit_id">Unidade</label>
            <select
                id="unit_id"
                v-model="form.unit_id"
                class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                required
            >
                <option :value="null" disabled>Selecione uma unidade</option>
                <option
                    v-for="unit in units"
                    :key="unit.id"
                    :value="unit.id"
                >
                    {{ unit.name }} ({{ unit.abbreviation }})
                </option>
            </select>
            <p v-if="form.errors.unit_id" class="text-xs text-destructive">
                {{ form.errors.unit_id }}
            </p>
        </div>

        <div class="flex items-center gap-3">
            <button
                type="submit"
                :disabled="form.processing"
                class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
            >
                {{ submitLabel }}
            </button>
            <Link
                :href="ProductController.index.url()"
                class="text-sm text-muted-foreground hover:underline"
            >
                Cancelar
            </Link>
        </div>
    </form>
</template>
