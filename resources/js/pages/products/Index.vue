<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onUnmounted, reactive, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
    DialogClose,
} from '@/components/ui/dialog';
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
    subcategory_id: string;
    tag_ids: number[];
    status: string;
};
const props = defineProps<{
    products: {
        data: {
            id: number;
            name: string;
            brand: string | null;
            tags: Choice[];
            is_active: boolean;
            canUpdate: boolean;
        }[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: Partial<
        Record<Exclude<keyof Filters, 'tag_ids'>, string | number | null>
    > & { tag_ids?: number[] | null };
    departments: Choice[];
    categories: (Choice & { department_id: number })[];
    subcategories: (Choice & { category_id: number })[];
    tags: Choice[];
    canCreate: boolean;
}>();

function filterValues(): Filters {
    return {
        search: String(props.filters.search ?? ''),
        department_id: String(props.filters.department_id ?? 'all'),
        category_id: String(props.filters.category_id ?? 'all'),
        subcategory_id: String(props.filters.subcategory_id ?? 'all'),
        tag_ids: (props.filters.tag_ids ?? []).map(Number),
        status: String(props.filters.status ?? 'all'),
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
const availableSubcategories = computed(() =>
    props.subcategories.filter((subcategory) =>
        filters.category_id !== 'all'
            ? String(subcategory.category_id) === filters.category_id
            : availableCategories.value.some(
                  (category) => category.id === subcategory.category_id,
              ),
    ),
);
// Keep dialog choices separate until the user applies them.
const tagPanelOpen = ref(false);
const selectedTagIds = ref<number[]>([]);
const tagSearch = ref('');
const appliedTagSearch = ref('');
let tagSearchTimer: number | undefined;
const filteredTags = computed(() =>
    props.tags.filter((tag) =>
        tag.name
            .toLowerCase()
            .includes(appliedTagSearch.value.trim().toLowerCase()),
    ),
);
function searchTags() {
    window.clearTimeout(tagSearchTimer);
    appliedTagSearch.value = tagSearch.value;
}
watch(tagSearch, () => {
    window.clearTimeout(tagSearchTimer);
    tagSearchTimer = window.setTimeout(searchTags, 300);
});
watch(tagPanelOpen, (open) => {
    window.clearTimeout(tagSearchTimer);
    if (open) {
        selectedTagIds.value = [...filters.tag_ids];
        tagSearch.value = '';
        appliedTagSearch.value = '';
    }
});
function updateTag(id: number, checked: boolean) {
    if (checked && !selectedTagIds.value.includes(id)) {
        selectedTagIds.value.push(id);
    } else if (!checked) {
        selectedTagIds.value = selectedTagIds.value.filter(
            (tagId) => tagId !== id,
        );
    }
}
function applyTags() {
    filters.tag_ids = [...selectedTagIds.value];
    tagPanelOpen.value = false;
    applyFilters();
}
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
function changeFilter(
    key: Exclude<keyof Filters, 'search' | 'tag_ids'>,
    value: unknown,
) {
    filters[key] = String(value);
    // Clear dependent choices before sending a single request.
    if (key === 'department_id') {
        filters.category_id = 'all';
        filters.subcategory_id = 'all';
    } else if (key === 'category_id') {
        filters.subcategory_id = 'all';
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
    window.clearTimeout(tagSearchTimer);
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
                        <Label for="product-search"
                            >Search products or tags</Label
                        >
                        <Input
                            id="product-search"
                            v-model="filters.search"
                            type="search"
                            maxlength="100"
                            placeholder="Search product or tag names..."
                        />
                    </div>
                    <!-- Stack on phones and use three equal columns on wider screens. -->
                    <div class="grid gap-4 lg:grid-cols-3">
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
                        <div class="grid min-w-0 gap-2">
                            <Label for="product-subcategory_id"
                                >Subcategory</Label
                            >
                            <Select
                                :model-value="filters.subcategory_id"
                                @update:model-value="
                                    changeFilter('subcategory_id', $event)
                                "
                            >
                                <SelectTrigger
                                    id="product-subcategory_id"
                                    class="w-full"
                                    ><SelectValue
                                /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all"
                                        >All subcategories</SelectItem
                                    >
                                    <SelectItem
                                        v-for="choice in availableSubcategories"
                                        :key="choice.id"
                                        :value="String(choice.id)"
                                        >{{ choice.name }}</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid min-w-0 gap-2">
                            <Label for="product-tags">Tags</Label>
                            <Dialog v-model:open="tagPanelOpen">
                                <DialogTrigger as-child>
                                    <Button
                                        id="product-tags"
                                        type="button"
                                        variant="outline"
                                        class="w-full justify-start"
                                    >
                                        {{
                                            filters.tag_ids.length
                                                ? `Tags (${filters.tag_ids.length} selected)`
                                                : 'All tags'
                                        }}
                                    </Button>
                                </DialogTrigger>
                                <DialogContent class="sm:max-w-2xl">
                                    <DialogHeader>
                                        <DialogTitle
                                            >Filter by tags</DialogTitle
                                        >
                                        <DialogDescription
                                            >Select tags, then apply your
                                            selection to the product
                                            list.</DialogDescription
                                        >
                                    </DialogHeader>
                                    <div class="grid gap-2">
                                        <Label for="filter-tag-search"
                                            >Search tags</Label
                                        >
                                        <Input
                                            id="filter-tag-search"
                                            v-model="tagSearch"
                                            type="search"
                                            placeholder="Search tags..."
                                            @keydown.enter.prevent="searchTags"
                                        />
                                    </div>
                                    <p
                                        v-if="tags.length === 0"
                                        class="text-sm text-muted-foreground"
                                    >
                                        No tags available.
                                    </p>
                                    <p
                                        v-else-if="filteredTags.length === 0"
                                        role="status"
                                        class="text-sm text-muted-foreground"
                                    >
                                        No matching tags found.
                                    </p>
                                    <div
                                        v-else
                                        class="max-h-48 overflow-y-auto rounded-lg border border-border p-3"
                                    >
                                        <div
                                            class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
                                        >
                                            <div
                                                v-for="tag in filteredTags"
                                                :key="tag.id"
                                                class="flex min-w-0 items-start gap-2"
                                            >
                                                <Checkbox
                                                    :id="`filter-tag-${tag.id}`"
                                                    class="mt-0.5"
                                                    :model-value="
                                                        selectedTagIds.includes(
                                                            tag.id,
                                                        )
                                                    "
                                                    @update:model-value="
                                                        updateTag(
                                                            tag.id,
                                                            $event === true,
                                                        )
                                                    "
                                                />
                                                <Label
                                                    :for="`filter-tag-${tag.id}`"
                                                    class="min-w-0 leading-5 break-words"
                                                    >{{ tag.name }}</Label
                                                >
                                            </div>
                                        </div>
                                    </div>
                                    <p
                                        class="text-sm text-muted-foreground"
                                        role="status"
                                    >
                                        {{ selectedTagIds.length }} selected
                                    </p>
                                    <DialogFooter>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            @click="selectedTagIds = []"
                                            >Clear tags</Button
                                        >
                                        <DialogClose as-child
                                            ><Button
                                                type="button"
                                                variant="outline"
                                                >Cancel</Button
                                            ></DialogClose
                                        >
                                        <Button type="button" @click="applyTags"
                                            >Apply tags</Button
                                        >
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                        </div>
                        <div class="grid min-w-0 gap-2">
                            <Label for="product-status">Status</Label>
                            <Select
                                :model-value="filters.status"
                                @update:model-value="
                                    changeFilter('status', $event)
                                "
                            >
                                <SelectTrigger
                                    id="product-status"
                                    class="w-full"
                                    ><SelectValue
                                /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all"
                                        >All statuses</SelectItem
                                    >
                                    <SelectItem value="active"
                                        >Active</SelectItem
                                    >
                                    <SelectItem value="inactive"
                                        >Inactive</SelectItem
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
                                <th scope="col" class="px-4 py-3">Brand</th>
                                <th scope="col" class="px-4 py-3">Tags</th>
                                <th scope="col" class="px-4 py-3">Status</th>
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
                                    :colspan="showEditActions ? 5 : 4"
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
                                    {{ product.brand || '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    {{
                                        product.tags
                                            .map((tag) => tag.name)
                                            .join(', ') || '—'
                                    }}
                                </td>
                                <td class="px-4 py-3">
                                    {{
                                        product.is_active
                                            ? 'Active'
                                            : 'Inactive'
                                    }}
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
