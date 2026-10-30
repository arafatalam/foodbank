<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed, onUnmounted, reactive, ref, watch } from 'vue';
import ProductController from '@/actions/App/Http/Controllers/ProductController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

const props = defineProps<{
    departments: { id: number; name: string }[];
    categories: { id: number; name: string; department_id: number }[];
    subcategories: { id: number; name: string; category_id: number }[];
    tags: { id: number; name: string }[];
}>();

// Keep unsaved values until Laravel accepts the product and its tag links.
const form = reactive({
    department_id: '',
    category_id: '',
    subcategory_id: '',
    name: '',
    brand: '',
    is_active: true,
    tag_ids: [] as number[],
});

// Limit each dropdown to children of the selected parent.
const availableCategories = computed(() =>
    props.categories.filter(
        (category) => String(category.department_id) === form.department_id,
    ),
);
const availableSubcategories = computed(() =>
    props.subcategories.filter(
        (subcategory) => String(subcategory.category_id) === form.category_id,
    ),
);

// A new Department clears both old child selections.
watch(
    () => form.department_id,
    () => {
        form.category_id = '';
        form.subcategory_id = '';
    },
);

// A new Category clears its old Subcategory selection.
watch(
    () => form.category_id,
    () => {
        form.subcategory_id = '';
    },
);

// Keep typing separate from the search currently applied to the list.
const tagSearch = ref('');
const appliedTagSearch = ref('');
let tagSearchTimer: number | undefined;

const filteredTags = computed(() => {
    const search = appliedTagSearch.value.trim().toLowerCase();

    return props.tags.filter((tag) => tag.name.toLowerCase().includes(search));
});

function searchTags() {
    // Enter applies the current text immediately and cancels a pending search.
    window.clearTimeout(tagSearchTimer);
    appliedTagSearch.value = tagSearch.value;
}

watch(tagSearch, () => {
    // Wait until typing stops for 300 milliseconds, like the catalogue searches.
    window.clearTimeout(tagSearchTimer);
    tagSearchTimer = window.setTimeout(searchTags, 300);
});

onUnmounted(() => {
    // Cancel a waiting search when leaving the page.
    window.clearTimeout(tagSearchTimer);
});

function updateTag(id: number, checked: boolean) {
    // Store each selected tag ID once; remove it when unchecked.
    if (checked && !form.tag_ids.includes(id)) {
        form.tag_ids.push(id);
    } else if (!checked) {
        form.tag_ids = form.tag_ids.filter((tagId) => tagId !== id);
    }
}

const resetKey = ref(0);

function resetForm() {
    // Reset controlled inputs only after a successful save, retaining them on errors.
    Object.assign(form, {
        department_id: '',
        category_id: '',
        subcategory_id: '',
        name: '',
        brand: '',
        is_active: true,
        tag_ids: [],
    });
    tagSearch.value = '';
    searchTags();
    resetKey.value++;
}

// Tell the shared layout where this page belongs.
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
    <Head title="Create Product" />

    <!-- Match the catalogue pages' spacing and Card layout. -->
    <main class="p-4">
        <Card class="w-full max-w-5xl">
            <CardHeader><CardTitle>Create Product</CardTitle></CardHeader>
            <CardContent>
                <div class="mt-4 rounded-xl border border-border p-4">
                    <!-- Follow Poker's Inertia Form binding; send all controlled values, including hidden tags. -->
                    <Form
                        v-bind="ProductController.store.form()"
                        :transform="() => ({ ...form })"
                        novalidate
                        resetOnSuccess
                        @success="resetForm"
                        v-slot="{ errors, processing }"
                        class="grid gap-6"
                    >
                        <!-- Stack on phones; put the hierarchy on one row on wider screens. -->
                        <div class="grid gap-4 lg:grid-cols-3">
                            <div class="grid min-w-0 gap-2">
                                <Label for="department_id">Department</Label>
                                <Select
                                    :key="resetKey"
                                    v-model="form.department_id"
                                >
                                    <SelectTrigger
                                        id="department_id"
                                        class="w-full"
                                    >
                                        <SelectValue
                                            placeholder="Select a department"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="department in departments"
                                            :key="department.id"
                                            :value="String(department.id)"
                                        >
                                            {{ department.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="errors.department_id" />
                            </div>

                            <div class="grid min-w-0 gap-2">
                                <Label for="category_id">Category</Label>
                                <!-- Recreate the Select after a parent change to clear its displayed choice. -->
                                <Select
                                    :key="form.department_id"
                                    v-model="form.category_id"
                                    :disabled="
                                        !form.department_id ||
                                        availableCategories.length === 0
                                    "
                                >
                                    <SelectTrigger
                                        id="category_id"
                                        class="w-full"
                                    >
                                        <SelectValue
                                            :placeholder="
                                                !form.department_id
                                                    ? 'Select a department first'
                                                    : availableCategories.length ===
                                                        0
                                                      ? 'No categories available'
                                                      : 'Select a category'
                                            "
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="category in availableCategories"
                                            :key="category.id"
                                            :value="String(category.id)"
                                        >
                                            {{ category.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="errors.category_id" />
                            </div>

                            <div class="grid min-w-0 gap-2">
                                <Label for="subcategory_id">Subcategory</Label>
                                <Select
                                    :key="`${form.department_id}-${form.category_id}`"
                                    v-model="form.subcategory_id"
                                    :disabled="
                                        !form.category_id ||
                                        availableSubcategories.length === 0
                                    "
                                >
                                    <SelectTrigger
                                        id="subcategory_id"
                                        class="w-full"
                                    >
                                        <SelectValue
                                            :placeholder="
                                                !form.category_id
                                                    ? 'Select a category first'
                                                    : availableSubcategories.length ===
                                                        0
                                                      ? 'No subcategories available'
                                                      : 'Select a subcategory'
                                            "
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="subcategory in availableSubcategories"
                                            :key="subcategory.id"
                                            :value="String(subcategory.id)"
                                        >
                                            {{ subcategory.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="errors.subcategory_id" />
                            </div>
                        </div>

                        <!-- Give name and brand equal space on wider screens. -->
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="grid min-w-0 gap-2">
                                <Label for="name">Product name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    v-model="form.name"
                                    type="text"
                                    maxlength="255"
                                />
                                <InputError :message="errors.name" />
                            </div>
                            <div class="grid min-w-0 gap-2">
                                <Label for="brand">Brand (optional)</Label>
                                <Input
                                    id="brand"
                                    name="brand"
                                    v-model="form.brand"
                                    type="text"
                                    maxlength="255"
                                />
                                <InputError :message="errors.brand" />
                            </div>
                        </div>

                        <!-- Share one status value so only one checkbox stays checked. -->
                        <fieldset class="grid gap-2">
                            <legend class="mb-2 text-sm font-medium">
                                Status
                            </legend>
                            <div class="flex flex-wrap gap-6">
                                <label class="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        name="active"
                                        v-model="form.is_active"
                                        :true-value="true"
                                        :false-value="false"
                                    />
                                    Active
                                </label>
                                <label class="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        name="inactive"
                                        v-model="form.is_active"
                                        :true-value="false"
                                        :false-value="true"
                                    />
                                    Inactive
                                </label>
                            </div>
                            <InputError :message="errors.is_active" />
                        </fieldset>

                        <fieldset class="grid min-w-0 gap-2">
                            <legend class="mb-2 text-sm font-medium">
                                Tags (optional)
                            </legend>
                            <!-- Match one column of the Department row on wider screens. -->
                            <div
                                v-if="tags.length > 0"
                                class="grid gap-4 lg:grid-cols-3"
                            >
                                <div class="grid min-w-0 gap-2">
                                    <Label for="tag-search">Search tags</Label>
                                    <!-- Enter filters tags without submitting the product form. -->
                                    <Input
                                        id="tag-search"
                                        v-model="tagSearch"
                                        type="search"
                                        placeholder="Search tags..."
                                        @keydown.enter.prevent="searchTags"
                                    />
                                </div>
                            </div>
                            <p
                                v-if="tags.length === 0"
                                class="text-sm text-muted-foreground"
                            >
                                No tags available.
                            </p>
                            <p
                                v-else-if="filteredTags.length === 0"
                                class="text-sm text-muted-foreground"
                                role="status"
                            >
                                No matching tags found.
                            </p>
                            <!-- Keep long tag lists inside their own scrollable area. -->
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
                                            :id="`tag-${tag.id}`"
                                            class="mt-0.5"
                                            :model-value="
                                                form.tag_ids.includes(tag.id)
                                            "
                                            @update:model-value="
                                                updateTag(
                                                    tag.id,
                                                    $event === true,
                                                )
                                            "
                                        />
                                        <Label
                                            :for="`tag-${tag.id}`"
                                            class="min-w-0 leading-5 break-words"
                                            >{{ tag.name }}</Label
                                        >
                                    </div>
                                </div>
                            </div>
                            <InputError :message="errors.tag_ids" />
                            <InputError
                                v-for="(message, field) in errors"
                                :key="field"
                                :message="
                                    String(field).startsWith('tag_ids.')
                                        ? message
                                        : undefined
                                "
                            />
                        </fieldset>

                        <!-- Prevent another click while the save request is in progress. -->
                        <div class="flex justify-end">
                            <Button
                                type="submit"
                                :disabled="processing"
                                class="w-full sm:w-auto"
                            >
                                {{
                                    processing ? 'Saving...' : 'Create product'
                                }}
                            </Button>
                        </div>
                    </Form>
                </div>
            </CardContent>
        </Card>
    </main>
</template>
