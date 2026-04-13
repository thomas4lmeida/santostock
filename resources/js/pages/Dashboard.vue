<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Painel',
                href: dashboard(),
            },
        ],
    },
});

const page = usePage<{
    auth: { user: { name: string; role: string | null } };
}>();
const role = computed(() => page.props.auth.user?.role ?? null);
</script>

<template>
    <Head title="Painel" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <section
            data-testid="role-section"
            :data-role="role"
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <template v-if="role === 'coordinator'">
                <h2 class="text-lg font-semibold">Coordenador</h2>
                <p class="text-sm text-muted-foreground">
                    Gerencie eventos, fornecedores e atribua tarefas à sua
                    equipe.
                </p>
            </template>
            <template v-else-if="role === 'staff'">
                <h2 class="text-lg font-semibold">Equipe</h2>
                <p class="text-sm text-muted-foreground">
                    Suas tarefas atribuídas e o pool de tarefas em aberto.
                </p>
            </template>
            <template v-else-if="role === 'client'">
                <h2 class="text-lg font-semibold">Cliente</h2>
                <p class="text-sm text-muted-foreground">
                    Acompanhe o status de entrega dos itens dos seus eventos.
                </p>
            </template>
            <template v-else>
                <h2 class="text-lg font-semibold">Bem-vindo</h2>
                <p class="text-sm text-muted-foreground">
                    Nenhum perfil atribuído. Fale com o coordenador.
                </p>
            </template>
        </section>

        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <div
                class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <PlaceholderPattern />
            </div>
            <div
                class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <PlaceholderPattern />
            </div>
            <div
                class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <PlaceholderPattern />
            </div>
        </div>
        <div
            class="relative min-h-[100vh] flex-1 rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border"
        >
            <PlaceholderPattern />
        </div>
    </div>
</template>
