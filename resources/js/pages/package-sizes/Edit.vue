<script setup lang="ts">
import PageHeading from '@/components/PageHeading.vue';
import { Ruler } from '@lucide/vue';
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
    <main class="space-y-6 p-4">
        <PageHeading
            title="Edit package size"
            description="Update this package definition where changes are permitted."
            :icon="Ruler"
        />
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
