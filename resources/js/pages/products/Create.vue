<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ProductForm from '@/components/ProductForm.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
const props = defineProps<{
    departments: { id: number; name: string }[];
    categories: { id: number; name: string; department_id: number }[];
}>();
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Products', href: '/products' },
            { title: 'Create product', href: '#' },
        ],
    },
});
</script>
<template>
    <Head title="Create product" />
    <main class="p-4">
        <Card class="w-full max-w-3xl">
            <CardHeader><CardTitle>Create product</CardTitle></CardHeader>
            <CardContent
                ><div class="mt-4 rounded-xl border border-border p-4">
                    <ProductForm v-bind="props" /></div
            ></CardContent>
        </Card>
    </main>
</template>
