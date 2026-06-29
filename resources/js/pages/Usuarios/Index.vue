<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import CrmAlert from '@/components/crm/CrmAlert.vue';
import CrmListCard from '@/components/crm/CrmListCard.vue';
import CrmAnimatedSection from '@/components/crm/CrmAnimatedSection.vue';
import CrmPageHero from '@/components/crm/CrmPageHero.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { UserCog, Plus, Edit, Trash2, Key, Loader2, Shield, User } from 'lucide-vue-next';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';

interface UserItem {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'trabajador';
    role_label: string;
    created_at: string;
}

const props = defineProps<{
    users: UserItem[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Sistema', href: '#' },
    { title: 'Usuarios', href: '/usuarios' },
];

const page = usePage();
const currentUser = computed(() => page.props.auth.user as { id: number } | null);

// State for Create Modal
const isCreateOpen = ref(false);
const createForm = useForm({
    name: '',
    email: '',
    password: '',
    role: 'trabajador',
});

const openCreateModal = () => {
    createForm.reset();
    createForm.clearErrors();
    isCreateOpen.value = true;
};

const submitCreate = () => {
    createForm.post('/usuarios', {
        onSuccess: () => {
            isCreateOpen.value = false;
        },
    });
};

// State for Edit Modal
const isEditOpen = ref(false);
const selectedUser = ref<UserItem | null>(null);
const editForm = useForm({
    name: '',
    email: '',
    role: 'trabajador' as 'admin' | 'trabajador',
});

const openEditModal = (user: UserItem) => {
    selectedUser.value = user;
    editForm.name = user.name;
    editForm.email = user.email;
    editForm.role = user.role;
    editForm.clearErrors();
    isEditOpen.value = true;
};

const submitEdit = () => {
    if (!selectedUser.value) return;
    editForm.put(`/usuarios/${selectedUser.value.id}`, {
        onSuccess: () => {
            isEditOpen.value = false;
        },
    });
};

// State for Password Change Modal
const isPasswordOpen = ref(false);
const passwordForm = useForm({
    password: '',
});

const openPasswordModal = (user: UserItem) => {
    selectedUser.value = user;
    passwordForm.reset();
    passwordForm.clearErrors();
    isPasswordOpen.value = true;
};

const submitPassword = () => {
    if (!selectedUser.value) return;
    passwordForm.put(`/usuarios/${selectedUser.value.id}/password`, {
        onSuccess: () => {
            isPasswordOpen.value = false;
        },
    });
};

// Delete user flow
const deleteForm = useForm({});
const deleteUser = (user: UserItem) => {
    if (user.id === currentUser.value?.id) {
        alert('No puedes eliminar tu propia cuenta de usuario.');
        return;
    }

    if (confirm(`¿Estás seguro de que deseas eliminar al usuario "${user.name}"? Esta acción no se puede deshacer.`)) {
        deleteForm.delete(`/usuarios/${user.id}`);
    }
};

const formatDate = (dateStr: string): string => {
    try {
        return format(new Date(dateStr), 'dd MMM yyyy, HH:mm', { locale: es });
    } catch (e) {
        return '—';
    }
};
</script>

<template>
    <Head title="Gestión de Usuarios" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="crm-page">
            <CrmPageHero
                title="Gestión de Usuarios"
                description="Administra los accesos de administradores y trabajadores al panel del CRM."
                :icon="UserCog"
                variant="violet"
                :stats="[{ label: 'Registrados', value: users.length }]"
            >
                <template #actions>
                    <Button @click="openCreateModal" class="gap-2 bg-violet-600 hover:bg-violet-700 text-white">
                        <Plus class="h-4 w-4" />
                        Nuevo Usuario
                    </Button>
                </template>
            </CrmPageHero>

            <CrmAlert v-if="page.props.flash?.success" variant="success" class="mb-4">
                {{ page.props.flash?.success }}
            </CrmAlert>
            <CrmAlert v-if="page.props.flash?.error" variant="destructive" class="mb-4">
                {{ page.props.flash?.error }}
            </CrmAlert>

            <CrmAnimatedSection :delay="80">
                <CrmListCard>
                    <div class="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow class="hover:bg-transparent">
                                    <TableHead class="w-[200px]">Nombre</TableHead>
                                    <TableHead>Email</TableHead>
                                    <TableHead class="w-[150px]">Rol</TableHead>
                                    <TableHead class="w-[200px]">Fecha Registro</TableHead>
                                    <TableHead class="w-[150px] text-right">Acciones</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="u in users" :key="u.id" class="crm-table-row-action text-left">
                                    <TableCell>
                                        <div class="flex items-center gap-2.5">
                                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-violet-100 text-violet-700 dark:bg-violet-950/40 dark:text-violet-300">
                                                <User class="h-4 w-4" />
                                            </div>
                                            <span class="font-medium text-foreground">{{ u.name }}</span>
                                            <span v-if="u.id === currentUser?.id" class="text-[10px] bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded font-medium dark:bg-slate-800 dark:text-slate-400">Tú</span>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <span class="text-sm font-mono text-muted-foreground">{{ u.email }}</span>
                                    </TableCell>
                                    <TableCell>
                                        <Badge v-if="u.role === 'admin'" variant="default" class="bg-violet-100 text-violet-700 border-violet-200 hover:bg-violet-100 dark:bg-violet-950/60 dark:text-violet-300 dark:border-violet-800 gap-1 rounded-md">
                                            <Shield class="h-3 w-3" />
                                            {{ u.role_label }}
                                        </Badge>
                                        <Badge v-else variant="secondary" class="gap-1 rounded-md">
                                            <User class="h-3 w-3" />
                                            {{ u.role_label }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <span class="text-xs text-muted-foreground">{{ formatDate(u.created_at) }}</span>
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <div class="flex justify-end gap-1.5">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="h-8 w-8 text-muted-foreground hover:text-foreground"
                                                title="Cambiar contraseña"
                                                @click="openPasswordModal(u)"
                                            >
                                                <Key class="h-4 w-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="h-8 w-8 text-muted-foreground hover:text-foreground"
                                                title="Editar datos"
                                                @click="openEditModal(u)"
                                            >
                                                <Edit class="h-4 w-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="h-8 w-8 text-muted-foreground hover:text-red-600 disabled:opacity-30"
                                                title="Eliminar usuario"
                                                :disabled="u.id === currentUser?.id"
                                                @click="deleteUser(u)"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                                <TableRow v-if="users.length === 0">
                                    <TableCell colspan="5" class="py-10 text-center text-sm text-muted-foreground italic">
                                        No hay usuarios registrados.
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </CrmListCard>
            </CrmAnimatedSection>
        </div>

        <!-- CREATE DIALOG -->
        <Dialog v-model:open="isCreateOpen">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Nuevo Usuario</DialogTitle>
                    <DialogDescription>Registra un nuevo usuario para otorgarle acceso al sistema.</DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitCreate" class="space-y-4 py-2">
                    <div class="space-y-1.5">
                        <Label for="create-name">Nombre</Label>
                        <Input id="create-name" v-model="createForm.name" placeholder="Ej. Juan Pérez" required />
                        <span v-if="createForm.errors.name" class="text-xs text-destructive">{{ createForm.errors.name }}</span>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="create-email">Correo Electrónico</Label>
                        <Input id="create-email" type="email" v-model="createForm.email" placeholder="juan@ejemplo.com" required />
                        <span v-if="createForm.errors.email" class="text-xs text-destructive">{{ createForm.errors.email }}</span>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="create-password">Contraseña Temporal</Label>
                        <Input id="create-password" type="password" v-model="createForm.password" placeholder="Mínimo 8 caracteres" required />
                        <span v-if="createForm.errors.password" class="text-xs text-destructive">{{ createForm.errors.password }}</span>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="create-role">Rol del Sistema</Label>
                        <select
                            id="create-role"
                            v-model="createForm.role"
                            class="flex h-9 w-full rounded-md border border-input bg-background text-foreground px-3 py-1 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option value="trabajador" class="bg-background text-foreground">Trabajador (Taller/Asesor)</option>
                            <option value="admin" class="bg-background text-foreground">Administrador (Acceso Total)</option>
                        </select>
                        <span v-if="createForm.errors.role" class="text-xs text-destructive">{{ createForm.errors.role }}</span>
                    </div>

                    <DialogFooter class="pt-3">
                        <Button type="button" variant="outline" @click="isCreateOpen = false">Cancelar</Button>
                        <Button type="submit" :disabled="createForm.processing" class="bg-violet-600 hover:bg-violet-700 text-white gap-2">
                            <Loader2 v-if="createForm.processing" class="h-4 w-4 animate-spin" />
                            Crear Cuenta
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- EDIT DIALOG -->
        <Dialog v-model:open="isEditOpen">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Editar Usuario</DialogTitle>
                    <DialogDescription>Modifica los datos personales y privilegios del usuario.</DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitEdit" class="space-y-4 py-2">
                    <div class="space-y-1.5">
                        <Label for="edit-name">Nombre</Label>
                        <Input id="edit-name" v-model="editForm.name" required />
                        <span v-if="editForm.errors.name" class="text-xs text-destructive">{{ editForm.errors.name }}</span>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="edit-email">Correo Electrónico</Label>
                        <Input id="edit-email" type="email" v-model="editForm.email" required />
                        <span v-if="editForm.errors.email" class="text-xs text-destructive">{{ editForm.errors.email }}</span>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="edit-role">Rol del Sistema</Label>
                        <select
                            id="edit-role"
                            v-model="editForm.role"
                            :disabled="selectedUser?.id === currentUser?.id"
                            class="flex h-9 w-full rounded-md border border-input bg-background text-foreground px-3 py-1 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option value="trabajador" class="bg-background text-foreground">Trabajador (Taller/Asesor)</option>
                            <option value="admin" class="bg-background text-foreground">Administrador (Acceso Total)</option>
                        </select>
                        <span v-if="editForm.errors.role" class="text-xs text-destructive">{{ editForm.errors.role }}</span>
                        <p v-if="selectedUser?.id === currentUser?.id" class="text-[10px] text-muted-foreground italic mt-1">No puedes cambiar tu propio rol.</p>
                    </div>

                    <DialogFooter class="pt-3">
                        <Button type="button" variant="outline" @click="isEditOpen = false">Cancelar</Button>
                        <Button type="submit" :disabled="editForm.processing" class="bg-violet-600 hover:bg-violet-700 text-white gap-2">
                            <Loader2 v-if="editForm.processing" class="h-4 w-4 animate-spin" />
                            Guardar Cambios
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- PASSWORD DIALOG -->
        <Dialog v-model:open="isPasswordOpen">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Cambiar Contraseña</DialogTitle>
                    <DialogDescription>Asigna una nueva contraseña al usuario <strong>{{ selectedUser?.name }}</strong>.</DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitPassword" class="space-y-4 py-2">
                    <div class="space-y-1.5">
                        <Label for="change-password">Nueva Contraseña</Label>
                        <Input id="change-password" type="password" v-model="passwordForm.password" placeholder="Mínimo 8 caracteres" required />
                        <span v-if="passwordForm.errors.password" class="text-xs text-destructive">{{ passwordForm.errors.password }}</span>
                    </div>

                    <DialogFooter class="pt-3">
                        <Button type="button" variant="outline" @click="isPasswordOpen = false">Cancelar</Button>
                        <Button type="submit" :disabled="passwordForm.processing" class="bg-violet-600 hover:bg-violet-700 text-white gap-2">
                            <Loader2 v-if="passwordForm.processing" class="h-4 w-4 animate-spin" />
                            Cambiar Contraseña
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
