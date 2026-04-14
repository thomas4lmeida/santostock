<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import * as WarehouseController from '@/actions/App/Http/Controllers/Warehouses/WarehouseController';

interface WarehouseValues {
    name: string;
}

const props = defineProps<{
    warehouse?: Partial<WarehouseValues> & { id?: number };
    submitUrl: string;
    method: 'post' | 'put';
    submitLabel: string;
}>();

const form = useForm({
    name: props.warehouse?.name ?? '',
});

function submit() {
    form[props.method](props.submitUrl);
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

        <div class="flex items-center gap-3">
            <button
                type="submit"
                :disabled="form.processing"
                class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
            >
                {{ submitLabel }}
            </button>
            <Link
                :href="WarehouseController.index.url()"
                class="text-sm text-muted-foreground hover:underline"
            >
                Cancelar
            </Link>
        </div>
    </form>
</template>
