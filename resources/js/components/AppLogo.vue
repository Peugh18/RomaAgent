<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';

interface Props {
    class?: string;
}

defineProps<Props>();
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-lg bg-gradient-to-br from-emerald-500 to-emerald-700 text-white shadow-sm shadow-emerald-500/20"
    >
        <AppLogoIcon class="size-5 fill-current text-white" />
    </div>
    <div class="ml-2 grid flex-1 text-left leading-tight group-data-[collapsible=icon]:hidden">
        <span class="truncate text-sm font-semibold tracking-tight">RomaAgent</span>
        <span class="truncate text-[11px] text-sidebar-foreground/55">CRM WhatsApp</span>
    </div>
</template>
