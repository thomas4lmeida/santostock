<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

interface EventValues {
    name: string;
    description: string | null;
    venue: string;
    starts_at: string;
    ends_at: string;
}

const props = defineProps<{
    initial?: Partial<EventValues> & { id?: number };
    submitUrl: string;
    method: 'post' | 'put';
    submitLabel: string;
}>();

function toInput(value?: string | null): string {
    if (!value) {
        return '';
    }

    return value.slice(0, 16).replace(/ /g, 'T');
}

const form = useForm({
    name: props.initial?.name ?? '',
    description: props.initial?.description ?? '',
    venue: props.initial?.venue ?? '',
    starts_at: toInput(props.initial?.starts_at),
    ends_at: toInput(props.initial?.ends_at),
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

        <div class="flex flex-col gap-1">
            <label class="text-sm font-medium" for="venue">Local</label>
            <input
                id="venue"
                v-model="form.venue"
                type="text"
                class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                required
            />
            <p v-if="form.errors.venue" class="text-xs text-destructive">
                {{ form.errors.venue }}
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium" for="starts_at"
                    >Início</label
                >
                <input
                    id="starts_at"
                    v-model="form.starts_at"
                    type="datetime-local"
                    class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                    required
                />
                <p
                    v-if="form.errors.starts_at"
                    class="text-xs text-destructive"
                >
                    {{ form.errors.starts_at }}
                </p>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium" for="ends_at">Fim</label>
                <input
                    id="ends_at"
                    v-model="form.ends_at"
                    type="datetime-local"
                    class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                    required
                />
                <p v-if="form.errors.ends_at" class="text-xs text-destructive">
                    {{ form.errors.ends_at }}
                </p>
            </div>
        </div>

        <div class="flex flex-col gap-1">
            <label class="text-sm font-medium" for="description"
                >Descrição</label
            >
            <textarea
                id="description"
                v-model="form.description"
                rows="4"
                class="rounded-md border border-input bg-background px-3 py-2 text-sm"
            />
            <p v-if="form.errors.description" class="text-xs text-destructive">
                {{ form.errors.description }}
            </p>
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
