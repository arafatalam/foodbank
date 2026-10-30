<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { onUnmounted, ref, watch } from 'vue';

// Receive the subcategory records and page information from Laravel.
const props = defineProps<{
    search: string;
    subcategories: {
        data: {
            id: number;
            name: string;
            canEdit: boolean;
            category: {
                id: number;
                name: string;
                department: { id: number; name: string };
            };
        }[];
        prev_page_url: string | null;
        next_page_url: string | null;
        current_page: number;
        last_page: number;
    };
}>();

// Start the editable input with the search text supplied by Laravel.
const search = ref(props.search);

// Remember the waiting timer so we can cancel it.
let searchTimer: number | undefined;

function searchSubcategories() {
    // Cancel any waiting search when submitting immediately.
    window.clearTimeout(searchTimer);

    // Request matching subcategories while keeping the input's local state.
    router.get(
        '/subcategories',
        { search: search.value },
        { preserveState: true },
    );
}

// Notice when the input's value changes.
watch(search, () => {
    // Cancel the previous wait when another letter is typed.
    window.clearTimeout(searchTimer);

    // Search after typing has stopped for 300 milliseconds.
    searchTimer = window.setTimeout(() => {
        searchSubcategories();
    }, 300);
});

// Cancel a waiting search when leaving this page.
onUnmounted(() => {
    window.clearTimeout(searchTimer);
});
</script>

<template>
    <Head title="Subcategories" />

    <!-- Match the spacing and card width of the user list. -->
    <main class="p-4">
        <Card class="w-full max-w-5xl">
            <CardHeader>
                <CardTitle>Subcategories</CardTitle>
            </CardHeader>

            <CardContent>
                <!-- Stack the label above the search input. -->
                <form
                    @submit.prevent="searchSubcategories"
                    class="mt-4 flex flex-col gap-2"
                >
                    <Label for="subcategory-search"
                        >Search subcategories, categories or departments</Label
                    >

                    <Input
                        id="subcategory-search"
                        v-model="search"
                        type="search"
                        maxlength="100"
                        placeholder="Search department, category or subcategory names..."
                    />
                </form>
                <!-- Allow horizontal scrolling when the table needs more room. -->
                <div
                    class="mt-4 overflow-x-auto rounded-xl border border-border"
                >
                    <table class="w-full text-left">
                        <thead>
                            <tr>
                                <th scope="col" class="px-4 py-3">
                                    Department name
                                </th>
                                <th scope="col" class="px-4 py-3">
                                    Category name
                                </th>
                                <th scope="col" class="px-4 py-3">
                                    Subcategory name
                                </th>
                                <th scope="col" class="px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Use all four columns for the empty-list message. -->
                            <tr v-if="subcategories.data.length === 0">
                                <td colspan="4" class="px-4 py-3">
                                    No subcategories found.
                                </td>
                            </tr>

                            <!-- Keep each row connected to its subcategory ID. -->
                            <tr
                                v-for="subcategory in subcategories.data"
                                :key="subcategory.id"
                            >
                                <td class="px-4 py-3">
                                    {{ subcategory.category.department.name }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ subcategory.category.name }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ subcategory.name }}
                                </td>
                                <td class="px-4 py-3">
                                    <!-- Laravel supplies the permission; the route checks it again. -->
                                    <Button
                                        v-if="subcategory.canEdit"
                                        variant="outline"
                                        size="sm"
                                        as-child
                                    >
                                        <Link
                                            :href="`/subcategories/${subcategory.id}/edit`"
                                            :aria-label="`Edit ${subcategory.name}`"
                                        >
                                            Edit
                                        </Link>
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Let the page controls wrap on narrow screens. -->
                <div
                    class="mt-4 flex flex-wrap items-center justify-between gap-3"
                >
                    <p class="pl-1">
                        Page: {{ subcategories.current_page }} of
                        {{ subcategories.last_page }}
                    </p>

                    <div class="flex gap-2">
                        <Button v-if="subcategories.prev_page_url" as-child>
                            <Link :href="subcategories.prev_page_url">
                                Previous
                            </Link>
                        </Button>

                        <Button v-if="subcategories.next_page_url" as-child>
                            <Link :href="subcategories.next_page_url"
                                >Next</Link
                            >
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </main>
</template>
