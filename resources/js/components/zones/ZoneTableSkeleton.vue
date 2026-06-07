<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
</script>

<template>
    <div class="rounded-md border">
        <Table>
            <TableHeader>
                <TableRow class="hover:bg-transparent">
                    <TableHead class="w-[250px]">Zona / Distrito</TableHead>
                    <TableHead class="w-[120px]">Tipo</TableHead>
                    <TableHead class="w-[140px]">Motorizado</TableHead>
                    <TableHead class="w-[120px]">Shalom</TableHead>
                    <TableHead class="w-[100px] text-right">Acciones</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="i in 6" :key="i" class="hover:bg-transparent">
                    <TableCell>
                        <div class="flex items-center gap-3">
                            <Skeleton class="h-4 w-4 rounded-full" />
                            <Skeleton class="h-4 w-28" />
                        </div>
                    </TableCell>
                    <TableCell><Skeleton class="h-5 w-20 rounded-full" /></TableCell>
                    <TableCell><Skeleton class="h-4 w-16" /></TableCell>
                    <TableCell><Skeleton class="h-4 w-14" /></TableCell>
                    <TableCell class="text-right">
                        <div class="flex justify-end gap-1">
                            <Skeleton class="h-8 w-8 rounded-md" />
                            <Skeleton class="h-8 w-8 rounded-md" />
                        </div>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
