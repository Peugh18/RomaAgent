<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
</script>

<template>
    <div class="rounded-md border">
        <Table>
            <TableHeader>
                <TableRow class="hover:bg-transparent">
                    <TableHead class="w-[300px]">Nombre</TableHead>
                    <TableHead>Categoría</TableHead>
                    <TableHead class="w-[100px]">Variantes</TableHead>
                    <TableHead class="w-[120px]">Precio</TableHead>
                    <TableHead class="w-[120px]">Promo</TableHead>
                    <TableHead class="w-[100px] text-right">Acciones</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="i in 5" :key="i" class="hover:bg-transparent">
                    <TableCell>
                        <div class="flex items-center gap-3">
                            <Skeleton class="h-8 w-8 rounded-md" />
                            <Skeleton class="h-4 w-40" />
                        </div>
                    </TableCell>
                    <TableCell><Skeleton class="h-4 w-24" /></TableCell>
                    <TableCell><Skeleton class="h-4 w-8" /></TableCell>
                    <TableCell><Skeleton class="h-4 w-20" /></TableCell>
                    <TableCell><Skeleton class="h-4 w-16" /></TableCell>
                    <TableCell class="text-right">
                        <div class="flex justify-end gap-2">
                            <Skeleton class="h-8 w-8 rounded-md" />
                            <Skeleton class="h-8 w-8 rounded-md" />
                        </div>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
