<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    primaryNavGroups,
    systemNavGroup,
    toNavMainItems,
} from '@/config/appNavigation';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage();
const isTrabajador = computed(() => page.props.auth.user?.role === 'trabajador');

const filteredPrimaryGroups = computed(() => {
    return primaryNavGroups.map(group => {
        let filteredItems = group.items;
        if (isTrabajador.value) {
            filteredItems = group.items.filter(item => item.title !== 'Clientes');
        }
        return {
            ...group,
            items: filteredItems
        };
    }).filter(group => group.items.length > 0);
});

const filteredSystemGroup = computed(() => {
    let filteredItems = systemNavGroup.items;
    if (isTrabajador.value) {
        filteredItems = systemNavGroup.items.filter(
            item => item.title !== 'Configuración' && item.title !== 'Usuarios'
        );
    }
    return {
        ...systemNavGroup,
        items: filteredItems
    };
});
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
                v-for="group in filteredPrimaryGroups"
                :key="group.label"
                :items="toNavMainItems(group.items)"
                :group-label="group.label"
            />

            <div v-if="filteredSystemGroup.items.length > 0" class="mx-3 my-3 border-t border-sidebar-border/80" role="separator" />

            <NavMain
                v-if="filteredSystemGroup.items.length > 0"
                :items="toNavMainItems(filteredSystemGroup.items)"
                :group-label="filteredSystemGroup.label"
            />

        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
