<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    primaryNavGroups,
    systemNavGroup,
    toNavMainItems,
} from '@/config/appNavigation';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { Link } from '@inertiajs/vue3';
import AppLogo from './AppLogo.vue';
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="gap-0">
            <NavMain
                v-for="group in primaryNavGroups"
                :key="group.label"
                :items="toNavMainItems(group.items)"
                :group-label="group.label"
            />

            <div class="mx-3 my-3 border-t border-sidebar-border/80" role="separator" />

            <NavMain :items="toNavMainItems(systemNavGroup.items)" :group-label="systemNavGroup.label" />

        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
