<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import StockItemForm from '@/components/StockItemForm.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

const props = defineProps<{
    products: { id: number; label: string }[];
    packageSizes: { id: number; label: string }[];
    stockItem: {
        id: number;
        product_id: number;
        package_size_id: number;
        brand: string | null;
    };
    identityLocked: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Stock items', href: '/stock-items' },
            { title: 'Edit Stock Item', href: '#' },
        ],
    },
});
</script>

<template>
    <Head title="Edit Stock Item" />
    <main class="p-4">
        <Card class="w-full max-w-3xl">
            <CardHeader><CardTitle>Edit Stock Item</CardTitle></CardHeader>
            <CardContent><StockItemForm v-bind="props" /></CardContent>
        </Card>
    </main>
</template>
