<script setup lang="ts">
import AlertaCuotaGeminiBanner from '@/components/AlertaCuotaGeminiBanner.vue';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { setGlobalCurrency, type CurrencyCode } from '@/composables/useCurrency';
import type { BreadcrumbItemType } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const pageKey = computed(() => page.url.split('?')[0]);

watch(
    () => page.props.company as { moneda?: CurrencyCode } | undefined,
    (company) => {
        if (company?.moneda) {
            setGlobalCurrency(company.moneda);
        }
    },
    { immediate: true },
);
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar">
            <AlertaCuotaGeminiBanner />
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <div
                :key="pageKey"
                v-motion
                :initial="{ opacity: 0, y: 10 }"
                :enter="{ opacity: 1, y: 0, transition: { duration: 280, ease: 'easeOut' } }"
                class="flex flex-1 flex-col"
            >
                <slot />
            </div>
        </AppContent>
    </AppShell>
</template>
