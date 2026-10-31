<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { onUnmounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { packageTypes, contentsUnits } from '@/lib/package-sizes';

const props = defineProps<{
    canCreate: boolean;
    search: string;
    packageSizes: {
        data: {
            id: number;
            package_type: string;
            contents: string;
            contents_unit: string;
            canUpdate: boolean;
        }[];
        prev_page_url: string | null;
        next_page_url: string | null;
        current_page: number;
        last_page: number;
    };
}>();

const search = ref(props.search);
let searchTimer: number | undefined;

function searchPackageSizes() {
    window.clearTimeout(searchTimer);
    // Changing the search starts at page one; pagination retains the search on Laravel's links.
    router.get(
        '/package-sizes',
        { search: search.value },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

watch(search, () => {
    // Wait for a brief pause in typing rather than sending a request per letter.
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(searchPackageSizes, 300);
});

onUnmounted(() => window.clearTimeout(searchTimer));

// Format display text only; duplicate calculations remain exact on the server.
function displayContents(contents: string) {
    return Number(contents).toLocaleString(undefined, {
        maximumFractionDigits: 3,
    });
}

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Package sizes', href: '/package-sizes' }],
    },
});
</script>

<template>
    <Head title="Package Sizes" />
    <main class="p-4">
        <Card class="w-full max-w-3xl">
            <CardHeader>
                <CardTitle>Package sizes</CardTitle>
                <Button v-if="canCreate" as-child class="w-fit">
                    <Link href="/package-sizes/create"
                        >Create package size</Link
                    >
                </Button>
            </CardHeader>
            <CardContent>
                <form
                    @submit.prevent="searchPackageSizes"
                    class="mb-4 flex flex-col gap-2"
                >
                    <Label for="package-type-search"
                        >Search package types</Label
                    >
                    <Input
                        id="package-type-search"
                        v-model="search"
                        type="search"
                        maxlength="100"
                        placeholder="Search package types…"
                    />
                </form>
                <!-- Keep all table columns reachable on narrow screens. -->
                <div class="overflow-x-auto rounded-xl border border-border">
                    <table class="w-full text-left">
                        <thead>
                            <tr>
                                <th scope="col" class="px-4 py-3">
                                    Package type
                                </th>
                                <th scope="col" class="px-4 py-3">Contents</th>
                                <th scope="col" class="px-4 py-3">Unit</th>
                                <th scope="col" class="px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="packageSizes.data.length === 0">
                                <td colspan="4" class="px-4 py-3">
                                    No package sizes found.
                                </td>
                            </tr>
                            <tr
                                v-for="definition in packageSizes.data"
                                :key="definition.id"
                            >
                                <td class="px-4 py-3">
                                    {{
                                        packageTypes.find(
                                            (type) =>
                                                type.value ===
                                                definition.package_type,
                                        )?.label ?? definition.package_type
                                    }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ displayContents(definition.contents) }}
                                </td>
                                <td class="px-4 py-3">
                                    {{
                                        contentsUnits.find(
                                            (unit) =>
                                                unit.value ===
                                                definition.contents_unit,
                                        )?.label ?? definition.contents_unit
                                    }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <Button
                                            v-if="definition.canUpdate"
                                            variant="outline"
                                            size="sm"
                                            as-child
                                        >
                                            <Link
                                                :href="`/package-sizes/${definition.id}/edit`"
                                                :aria-label="`Edit ${definition.package_type} ${displayContents(definition.contents)} ${definition.contents_unit}`"
                                                >Edit</Link
                                            >
                                        </Button>
                                        <!-- Used definitions are also protected by server-side authorization. -->
                                        <span
                                            v-else
                                            class="text-sm text-muted-foreground"
                                            >In use · Read only</span
                                        >
                                        <!-- Keep deletion unavailable until stock-item dependencies and safeguards exist. -->
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            size="sm"
                                            disabled
                                        >
                                            Delete
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div
                    class="mt-4 flex flex-wrap items-center justify-between gap-3"
                >
                    <p>
                        Page: {{ packageSizes.current_page }} of
                        {{ packageSizes.last_page }}
                    </p>
                    <div class="flex gap-2">
                        <Button v-if="packageSizes.prev_page_url" as-child
                            ><Link :href="packageSizes.prev_page_url"
                                >Previous</Link
                            ></Button
                        >
                        <Button v-if="packageSizes.next_page_url" as-child
                            ><Link :href="packageSizes.next_page_url"
                                >Next</Link
                            ></Button
                        >
                    </div>
                </div>
            </CardContent>
        </Card>
    </main>
</template>
