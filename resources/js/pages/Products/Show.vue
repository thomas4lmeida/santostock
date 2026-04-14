<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
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

interface Product {
    id: number;
    name: string;
    item_category: ItemCategory;
    unit: Unit;
}

const props = defineProps<{ product: Product }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Produtos', href: ProductController.index.url() },
            { title: 'Detalhes', href: '#' },
        ],
    },
});

const page = usePage<{ auth: { user: { role: string | null } | null } }>();
const isAdmin = computed(() => page.props.auth.user?.role === 'administrador');
</script>

<template>
    <Head :title="props.product.name" />
    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">{{ props.product.name }}</h1>
            <div class="flex items-center gap-2">
                <Link
                    v-if="isAdmin"
                    :href="ProductController.edit.url({ product: props.product.id })"
                    class="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:opacity-90"
                >
                    Editar
                </Link>
                <Link
                    :href="ProductController.index.url()"
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
                <p class="text-sm">{{ props.product.name }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-muted-foreground">Categoria</p>
                <p class="text-sm">{{ props.product.item_category.name }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-muted-foreground">Unidade</p>
                <p class="text-sm">
                    {{ props.product.unit.name }} ({{ props.product.unit.abbreviation }})
                </p>
            </div>
        </div>
    </div>
</template>
