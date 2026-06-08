<script setup lang="ts">
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import type { Component } from 'vue';

interface NavItem {
    title: string;
    url: string;
    icon: Component;
}

defineProps<{
    items: NavItem[];
    groupLabel?: string;
}>();

const page = usePage<SharedData>();

const isActive = (url: string) => {
    const current = page.url.split('?')[0];

    return current === url || (url !== '/dashboard' && current.startsWith(url));
};
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel class="text-[10px] font-semibold uppercase tracking-widest text-sidebar-foreground/50">
            {{ groupLabel }}
        </SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="isActive(item.url)"
                    :tooltip="item.title"
                    :class="
                        cn(
                            'transition-all duration-200',
                            isActive(item.url) &&
                                'bg-sidebar-accent/90 font-medium shadow-[inset_3px_0_0_0_hsl(var(--sidebar-ring))] [&>svg]:text-sidebar-ring',
                        )
                    "
                >
                    <Link :href="item.url" prefetch>
                        <component :is="item.icon" class="shrink-0 opacity-80 transition-opacity group-hover/menu-button:opacity-100" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
