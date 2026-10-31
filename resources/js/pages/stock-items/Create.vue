<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import StockItemForm from '@/components/StockItemForm.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

const props = defineProps<{
    products: { id: number; label: string }[];
    packageSizes: { id: number; label: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Stock items', href: '/stock-items' },
            { title: 'Create Stock Item', href: '#' },
        ],
    },
});
</script>

<template>
    <Head title="Create Stock Item" />
    <main class="p-4">
        <Card class="w-full max-w-3xl">
            <CardHeader><CardTitle>Create Stock Item</CardTitle></CardHeader>
            <CardContent><StockItemForm v-bind="props" /></CardContent>
        </Card>
    </main>
</template>
