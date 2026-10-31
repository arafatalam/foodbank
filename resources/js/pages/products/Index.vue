<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onUnmounted, reactive, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Choice = { id: number; name: string };
type Filters = {
    search: string;
    department_id: string;
    category_id: string;
};
const props = defineProps<{
    products: {
        data: {
            id: number;
            name: string;
            category: string;
            department: string;
            canUpdate: boolean;
        }[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: Partial<Record<keyof Filters, string | number | null>>;
    departments: Choice[];
    categories: (Choice & { department_id: number })[];
    canCreate: boolean;
}>();

function filterValues(): Filters {
    return {
        search: String(props.filters.search ?? ''),
        department_id: String(props.filters.department_id ?? 'all'),
        category_id: String(props.filters.category_id ?? 'all'),
    };
}
const filters = reactive(filterValues());

// Hide the Actions column when none of the displayed products can be edited.
const showEditActions = computed(() =>
    props.products.data.some((product) => product.canUpdate),
);

// Keep browser Back/Forward and pagination in step with the server's selections.
watch(
    () => props.filters,
    () => Object.assign(filters, filterValues()),
);
const availableCategories = computed(() =>
    props.categories.filter(
        (category) =>
            filters.department_id === 'all' ||
            String(category.department_id) === filters.department_id,
    ),
);
let searchTimer: number | undefined;
function applyFilters() {
    window.clearTimeout(searchTimer);
    // Omit unrestricted choices and page so each change starts on page one.
    const query = Object.fromEntries(
        Object.entries(filters).filter(
            ([, value]) =>
                value !== 'all' &&
                value !== '' &&
                (!Array.isArray(value) || value.length > 0),
        ),
    );
    router.get('/products', query, {
        preserveState: true,
        preserveScroll: true,
    });
}
function changeFilter(key: Exclude<keyof Filters, 'search'>, value: unknown) {
    filters[key] = String(value);
    // Clear dependent choices before sending a single request.
    if (key === 'department_id') {
        filters.category_id = 'all';
    }
    applyFilters();
}
watch(
    () => filters.search,
    (value) => {
        window.clearTimeout(searchTimer);
        // Prop synchronization should not launch a second request.
        if (value !== String(props.filters.search ?? '')) {
            searchTimer = window.setTimeout(applyFilters, 300);
        }
    },
);
onUnmounted(() => {
    window.clearTimeout(searchTimer);
});

// Tell the shared layout where this page belongs.
defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Products', href: '/products' }],
    },
});
</script>

<template>
    <Head title="Products" />
    <main class="p-4">
        <Card class="w-full">
            <CardHeader>
                <CardTitle>Products</CardTitle>
                <Button v-if="canCreate" as-child class="w-fit"
                    ><Link href="/products/create">Create product</Link></Button
                >
            </CardHeader>
            <CardContent>
                <form class="mt-4 grid gap-4" @submit.prevent="applyFilters">
                    <div class="grid gap-2">
                        <Label for="product-search">Search products</Label>
                        <Input
                            id="product-search"
                            v-model="filters.search"
                            type="search"
                            maxlength="100"
                            placeholder="Search product names..."
                        />
                    </div>
                    <!-- Stack on phones and use two equal columns on wider screens. -->
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid min-w-0 gap-2">
                            <Label for="product-department_id"
                                >Department</Label
                            >
                            <Select
                                :model-value="filters.department_id"
                                @update:model-value="
                                    changeFilter('department_id', $event)
                                "
                            >
                                <SelectTrigger
                                    id="product-department_id"
                                    class="w-full"
                                    ><SelectValue
                                /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all"
                                        >All departments</SelectItem
                                    >
                                    <SelectItem
                                        v-for="choice in departments"
                                        :key="choice.id"
                                        :value="String(choice.id)"
                                        >{{ choice.name }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid min-w-0 gap-2">
                            <Label for="product-category_id">Category</Label>
                            <Select
                                :model-value="filters.category_id"
                                @update:model-value="
                                    changeFilter('category_id', $event)
                                "
                            >
                                <SelectTrigger
                                    id="product-category_id"
                                    class="w-full"
                                    ><SelectValue
                                /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all"
                                        >All categories</SelectItem
                                    >
                                    <SelectItem
                                        v-for="choice in availableCategories"
                                        :key="choice.id"
                                        :value="String(choice.id)"
                                        >{{ choice.name }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </form>
                <!-- Keep a wide table inside its own scrolling area on phones. -->
                <div
                    class="mt-4 overflow-x-auto rounded-xl border border-border"
                >
                    <table class="w-full text-left">
                        <thead>
                            <tr>
                                <th scope="col" class="px-4 py-3">
                                    Product name
                                </th>
                                <th scope="col" class="px-4 py-3">Category</th>
                                <th scope="col" class="px-4 py-3">
                                    Department
                                </th>
                                <th
                                    v-if="showEditActions"
                                    scope="col"
                                    class="px-4 py-3"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="products.data.length === 0">
                                <td
                                    :colspan="showEditActions ? 4 : 3"
                                    class="px-4 py-3"
                                >
                                    No products found.
                                </td>
                            </tr>
                            <tr
                                v-for="product in products.data"
                                :key="product.id"
                            >
                                <td class="px-4 py-3 break-words">
                                    {{ product.name }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ product.category }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ product.department }}
                                </td>
                                <td v-if="showEditActions" class="px-4 py-3">
                                    <!-- Link to this row's saved product; the route checks permission again. -->
                                    <Button
                                        v-if="product.canUpdate"
                                        variant="outline"
                                        as-child
                                    >
                                        <Link
                                            :href="`/products/${product.id}/edit`"
                                            :aria-label="`Edit ${product.name}`"
                                            >Edit</Link
                                        >
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div
                    class="mt-4 flex flex-wrap items-center justify-between gap-3"
                >
                    <p class="pl-1">
                        Page: {{ products.current_page }} of
                        {{ products.last_page }}
                    </p>
                    <div class="flex gap-2">
                        <Button v-if="products.prev_page_url" as-child
                            ><Link :href="products.prev_page_url"
                                >Previous</Link
                            ></Button
                        >
                        <Button v-if="products.next_page_url" as-child
                            ><Link :href="products.next_page_url"
                                >Next</Link
                            ></Button
                        >
                    </div>
                </div>
            </CardContent>
        </Card>
    </main>
</template>
