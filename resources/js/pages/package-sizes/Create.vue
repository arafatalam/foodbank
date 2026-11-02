<script setup lang="ts">
import PageHeading from '@/components/PageHeading.vue';
import { Ruler } from '@lucide/vue';
import { Head } from '@inertiajs/vue3';
import PackageSizeForm from '@/components/PackageSizeForm.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

// Keep both pages under the Package sizes list in the breadcrumb.
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Package sizes', href: '/package-sizes' },
            { title: 'Create Package Size', href: '#' },
        ],
    },
});
</script>

<template>
    <Head title="Create Package Size" />
    <main class="space-y-6 p-4">
        <PageHeading
            title="Create package size"
            description="Define a package type, contents and unit for stock items."
            :icon="Ruler"
        />
        <Card class="w-full max-w-3xl">
            <CardHeader><CardTitle>Create Package Size</CardTitle></CardHeader>
            <CardContent>
                <div class="mt-4 rounded-xl border border-border p-4">
                    <!-- Share field layout, validation errors and submission state. -->
                    <PackageSizeForm />
                </div>
            </CardContent>
        </Card>
    </main>
</template>
