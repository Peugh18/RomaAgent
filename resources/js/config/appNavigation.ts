import {
    Brain,
    FolderTree,
    Kanban,
    LayoutGrid,
    MapPin,
    MessageSquare,
    Package,
    Sparkles,
    Settings2,
    Users,
} from 'lucide-vue-next';
import type { Component } from 'vue';

export interface AppNavItem {
    title: string;
    href: string;
    icon: Component;
}

export const mainNavItems: AppNavItem[] = [
    { title: 'Chat WhatsApp', href: '/chat', icon: MessageSquare },
    { title: 'Dashboard', href: '/dashboard', icon: LayoutGrid },
    { title: 'Pipeline de ventas', href: '/pipeline', icon: Kanban },
    { title: 'Clientes', href: '/clientes', icon: Users },
    { title: 'Productos', href: '/productos', icon: Package },
    { title: 'Categorías', href: '/categorias', icon: FolderTree },
    { title: 'Zonas de delivery', href: '/zonas-delivery', icon: MapPin },
    { title: 'Configuración', href: '/configuracion', icon: Settings2 },
];

export const trainingNavItems: AppNavItem[] = [
    { title: 'Entrenamiento visión', href: '/admin/vision/training', icon: Brain },
    { title: 'Embeddings', href: '/admin/vision/embeddings', icon: Sparkles },
];
