<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PackageSizeForm from '@/components/PackageSizeForm.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

const props = defineProps<{
    packageSize: {
        id: number;
        package_type: string;
        contents: string;
        contents_unit: string;
    };
}>();
// Keep both pages under the Package sizes list in the breadcrumb.
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Package sizes', href: '/package-sizes' },
            { title: 'Edit Package Size', href: '#' },
        ],
    },
});
</script>

<template>
    <Head title="Edit Package Size" />
    <main class="p-4">
        <Card class="w-full max-w-3xl">
            <CardHeader><CardTitle>Edit Package Size</CardTitle></CardHeader>
            <CardContent>
                <div class="mt-4 rounded-xl border border-border p-4">
                    <!-- Share field layout, validation errors and submission state. -->
                    <PackageSizeForm :package-size="props.packageSize" />
                </div>
            </CardContent>
        </Card>
    </main>
</template>
