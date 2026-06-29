import {
    FolderTree,
    Kanban,
    LayoutGrid,
    MessageSquare,
    Package,
    Settings2,
    Users,
    Tags,
    MapPin,
    UserCog,
} from 'lucide-vue-next';
import type { Component } from 'vue';

export interface AppNavItem {
    title: string;
    href: string;
    icon: Component;
    exact?: boolean;
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
        ],
    },
];

export const systemNavGroup: AppNavGroup = {
    label: 'Sistema',
    items: [
        { title: 'Configuración', href: '/configuracion', icon: Settings2 },
        { title: 'Zonas de Envío', href: '/configuracion/zonas-envio', icon: MapPin },
        { title: 'Usuarios', href: '/usuarios', icon: UserCog },
    ],
};


export function toNavMainItems(items: AppNavItem[]): { title: string; url: string; icon: Component; exact?: boolean }[] {
    return items.map((item) => ({
        title: item.title,
        url: item.href,
        icon: item.icon,
        exact: item.exact,
    }));
}
