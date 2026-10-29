<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { onUnmounted, ref, watch } from 'vue';

// Receive the category records and page information from Laravel.
const props = defineProps<{
    search: string;
    categories: {
        data: {
            id: number;
            name: string;
            department: { id: number; name: string };
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

function searchCategories() {
    // Cancel any waiting search when submitting immediately.
    window.clearTimeout(searchTimer);

    // Request matching categories while keeping the input's local state.
    router.get(
        '/categories',
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
        searchCategories();
    }, 300);
});

// Cancel a waiting search when leaving this page.
onUnmounted(() => {
    window.clearTimeout(searchTimer);
});
</script>

<template>
    <Head title="Categories" />

    <!-- Match the spacing and card width of the user list. -->
    <main class="p-4">
        <Card class="w-full max-w-3xl">
            <CardHeader>
                <CardTitle>Categories</CardTitle>
            </CardHeader>

            <CardContent>
                <!-- Stack the label above the search input. -->
                <form
                    @submit.prevent="searchCategories"
                    class="mt-4 flex flex-col gap-2"
                >
                    <Label for="category-search"
                        >Search categories or departments</Label
                    >

                    <Input
                        id="category-search"
                        v-model="search"
                        type="search"
                        maxlength="100"
                        placeholder="Search category or department names..."
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
                                    Category name
                                </th>
                                <th scope="col" class="px-4 py-3">
                                    Department name
                                </th>
                                <th scope="col" class="px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Use all three columns for the empty-list message. -->
                            <tr v-if="categories.data.length === 0">
                                <td colspan="3" class="px-4 py-3">
                                    No categories found.
                                </td>
                            </tr>

                            <!-- Keep each row connected to its category ID. -->
                            <tr
                                v-for="category in categories.data"
                                :key="category.id"
                            >
                                <td class="px-4 py-3">{{ category.name }}</td>
                                <td class="px-4 py-3">
                                    {{ category.department.name }}
                                </td>
                                <td class="px-4 py-3">
                                    <!-- Open the protected edit form for this row. -->
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        as-child
                                    >
                                        <Link
                                            :href="`/categories/${category.id}/edit`"
                                            :aria-label="`Edit ${category.name}`"
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
                        Page: {{ categories.current_page }} of
                        {{ categories.last_page }}
                    </p>

                    <div class="flex gap-2">
                        <Button v-if="categories.prev_page_url" as-child>
                            <Link :href="categories.prev_page_url">
                                Previous
                            </Link>
                        </Button>

                        <Button v-if="categories.next_page_url" as-child>
                            <Link :href="categories.next_page_url">Next</Link>
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </main>
</template>
