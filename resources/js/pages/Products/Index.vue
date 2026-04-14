<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import * as ProductController from '@/actions/App/Http/Controllers/Products/ProductController';
import type { Paginated } from '@/types/pagination';

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

defineProps<{ products: Paginated<Product> }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Produtos', href: ProductController.index.url() }],
    },
});

const page = usePage<{ auth: { user: { role: string | null } | null }; errors: { delete?: string } }>();
const isAdmin = computed(() => page.props.auth.user?.role === 'administrador');
const deleteError = computed(() => page.props.errors?.delete ?? null);

function destroy(product: Product) {
    if (!confirm('Excluir este produto?')) {
        return;
    }

    router.delete(ProductController.destroy.url({ product: product.id }));
}
</script>

<template>
    <Head title="Produtos" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Produtos</h1>
            <Link
                v-if="isAdmin"
                :href="ProductController.create.url()"
                class="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:opacity-90"
            >
                Novo produto
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
                        <th class="px-3 py-2">Categoria</th>
                        <th class="px-3 py-2">Unidade</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="product in products.data"
                        :key="product.id"
                        class="border-b border-sidebar-border/40 last:border-0"
                    >
                        <td class="px-3 py-2">{{ product.name }}</td>
                        <td class="px-3 py-2">{{ product.item_category.name }}</td>
                        <td class="px-3 py-2">
                            {{ product.unit.name }} ({{ product.unit.abbreviation }})
                        </td>
                        <td class="px-3 py-2 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <Link
                                    :href="
                                        ProductController.show.url({
                                            product: product.id,
                                        })
                                    "
                                    class="text-primary hover:underline"
                                >
                                    Ver
                                </Link>
                                <Link
                                    v-if="isAdmin"
                                    :href="
                                        ProductController.edit.url({
                                            product: product.id,
                                        })
                                    "
                                    class="text-primary hover:underline"
                                >
                                    Editar
                                </Link>
                                <button
                                    v-if="isAdmin"
                                    type="button"
                                    class="text-destructive hover:underline"
                                    @click="destroy(product)"
                                >
                                    Excluir
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="products.data.length === 0">
                        <td
                            colspan="4"
                            class="px-3 py-6 text-center text-sm text-muted-foreground"
                        >
                            Nenhum produto cadastrado.
                            <Link
                                v-if="isAdmin"
                                :href="ProductController.create.url()"
                                class="text-primary hover:underline"
                            >
                                Criar produto
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
