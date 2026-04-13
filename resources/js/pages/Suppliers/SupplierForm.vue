<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

interface SupplierValues {
    name: string;
    contact_name: string | null;
    phone: string | null;
    email: string | null;
    notes: string | null;
}

const props = defineProps<{
    initial?: Partial<SupplierValues> & { id?: number };
    submitUrl: string;
    method: 'post' | 'put';
    submitLabel: string;
}>();

const form = useForm({
    name: props.initial?.name ?? '',
    contact_name: props.initial?.contact_name ?? '',
    phone: props.initial?.phone ?? '',
    email: props.initial?.email ?? '',
    notes: props.initial?.notes ?? '',
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

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium" for="contact_name"
                    >Contato</label
                >
                <input
                    id="contact_name"
                    v-model="form.contact_name"
                    type="text"
                    class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium" for="phone">Telefone</label>
                <input
                    id="phone"
                    v-model="form.phone"
                    type="text"
                    class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                />
            </div>
        </div>

        <div class="flex flex-col gap-1">
            <label class="text-sm font-medium" for="email">E-mail</label>
            <input
                id="email"
                v-model="form.email"
                type="email"
                class="rounded-md border border-input bg-background px-3 py-2 text-sm"
            />
            <p v-if="form.errors.email" class="text-xs text-destructive">
                {{ form.errors.email }}
            </p>
        </div>

        <div class="flex flex-col gap-1">
            <label class="text-sm font-medium" for="notes">Observações</label>
            <textarea
                id="notes"
                v-model="form.notes"
                rows="3"
                class="rounded-md border border-input bg-background px-3 py-2 text-sm"
            />
        </div>

        <div>
            <button
                type="submit"
                :disabled="form.processing"
                class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
            >
                {{ submitLabel }}
            </button>
        </div>
    </form>
</template>
