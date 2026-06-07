<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
</script>

<template>
    <div class="rounded-md border">
        <Table>
            <TableHeader>
                <TableRow class="hover:bg-transparent">
                    <TableHead class="w-[400px]">Nombre</TableHead>
                    <TableHead>Slug</TableHead>
                    <TableHead class="w-[100px] text-right">Acciones</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="i in 5" :key="i" class="hover:bg-transparent">
                    <TableCell>
                        <div class="flex items-center gap-3">
                            <Skeleton class="h-8 w-8 rounded-md" />
                            <Skeleton class="h-4 w-32" />
                        </div>
                    </TableCell>
                    <TableCell><Skeleton class="h-4 w-24" /></TableCell>
                    <TableCell class="text-right">
                        <Skeleton class="h-8 w-8 rounded-md ml-auto" />
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
