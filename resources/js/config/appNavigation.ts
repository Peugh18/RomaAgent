import {
    FolderTree,
    Kanban,
    LayoutGrid,
    MapPin,
    MessageSquare,
    Package,
    Settings2,
    Users,
    Tags,
} from 'lucide-vue-next';
import type { Component } from 'vue';

export interface AppNavItem {
    title: string;
    href: string;
    icon: Component;
}

export interface AppNavGroup {
    label: string;
    items: AppNavItem[];
}

/** Orden lógico: resumen → ventas → catálogo → operaciones */
export const primaryNavGroups: AppNavGroup[] = [
    {
        label: 'Principal',
        items: [
            { title: 'Dashboard', href: '/dashboard', icon: LayoutGrid },
            { title: 'Chat WhatsApp', href: '/chat', icon: MessageSquare },
            { title: 'Pipeline de ventas', href: '/pipeline', icon: Kanban },
        ],
    },
    {
        label: 'Catálogo',
        items: [
            { title: 'Productos', href: '/productos', icon: Package },
            { title: 'Categorías', href: '/categorias', icon: FolderTree },
        ],
    },
    {
        label: 'Operaciones',
        items: [
            { title: 'Clientes', href: '/clientes', icon: Users },
            { title: 'Etiquetas', href: '/etiquetas', icon: Tags },
            { title: 'Zonas de delivery', href: '/zonas-delivery', icon: MapPin },
        ],
    },
];

export const systemNavGroup: AppNavGroup = {
    label: 'Sistema',
    items: [{ title: 'Configuración', href: '/configuracion', icon: Settings2 }],
};


export function toNavMainItems(items: AppNavItem[]): { title: string; url: string; icon: Component }[] {
    return items.map((item) => ({
        title: item.title,
        url: item.href,
        icon: item.icon,
    }));
}
