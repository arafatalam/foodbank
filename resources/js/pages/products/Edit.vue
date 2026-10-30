<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onUnmounted, ref, watch } from 'vue';
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

// Receive the saved product and the available choices from Laravel.
const props = defineProps<{
    product: {
        id: number;
        name: string;
        brand: string | null;
        department_id: number;
        category_id: number;
        subcategory_id: number;
        is_active: boolean;
        tag_ids: number[];
    };
    departments: { id: number; name: string }[];
    categories: { id: number; name: string; department_id: number }[];
    subcategories: { id: number; name: string; category_id: number }[];
    tags: { id: number; name: string }[];
}>();

// Copy saved values into temporary browser state; editing does not save them.
const form = useForm({
    department_id: String(props.product.department_id),
    category_id: String(props.product.category_id),
    subcategory_id: String(props.product.subcategory_id),
    name: props.product.name,
    brand: props.product.brand ?? '',
    is_active: props.product.is_active,
    tag_ids: [...props.product.tag_ids],
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
// Send every controlled value, including selected tags hidden by the tag search.
function submit() {
    if (form.processing) {
        return;
    }

    // Inertia keeps the entered values when Laravel returns validation errors.
    form.patch(`/products/${props.product.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Edit Product" />

    <!-- Match the catalogue pages' spacing and Card layout. -->
    <main class="p-4">
        <Card class="w-full max-w-5xl">
            <CardHeader><CardTitle>Edit Product</CardTitle></CardHeader>
            <CardContent>
                <div class="mt-4 rounded-xl border border-border p-4">
                    <!-- Send the edit through Inertia instead of a normal browser form submission. -->
                    <form
                        @submit.prevent="submit"
                        novalidate
                        class="grid gap-6"
                        :aria-busy="form.processing"
                    >
                        <!-- Stack on phones; put the hierarchy on one row on wider screens. -->
                        <div class="grid gap-4 lg:grid-cols-3">
                            <div class="grid min-w-0 gap-2">
                                <Label for="department_id">Department</Label>
                                <Select v-model="form.department_id">
                                    <SelectTrigger
                                        id="department_id"
                                        class="w-full"
                                        :aria-invalid="
                                            !!form.errors.department_id
                                        "
                                        :aria-describedby="
                                            form.errors.department_id
                                                ? 'department_id-error'
                                                : undefined
                                        "
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
                                <InputError
                                    id="department_id-error"
                                    :message="form.errors.department_id"
                                />
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
                                        :aria-invalid="
                                            !!form.errors.category_id
                                        "
                                        :aria-describedby="
                                            form.errors.category_id
                                                ? 'category_id-error'
                                                : undefined
                                        "
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
                                <InputError
                                    id="category_id-error"
                                    :message="form.errors.category_id"
                                />
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
                                        :aria-invalid="
                                            !!form.errors.subcategory_id
                                        "
                                        :aria-describedby="
                                            form.errors.subcategory_id
                                                ? 'subcategory_id-error'
                                                : undefined
                                        "
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
                                <InputError
                                    id="subcategory_id-error"
                                    :message="form.errors.subcategory_id"
                                />
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
                                    :aria-invalid="!!form.errors.name"
                                    :aria-describedby="
                                        form.errors.name
                                            ? 'name-error'
                                            : undefined
                                    "
                                />
                                <InputError
                                    id="name-error"
                                    :message="form.errors.name"
                                />
                            </div>
                            <div class="grid min-w-0 gap-2">
                                <Label for="brand">Brand (optional)</Label>
                                <Input
                                    id="brand"
                                    name="brand"
                                    v-model="form.brand"
                                    type="text"
                                    maxlength="255"
                                    :aria-invalid="!!form.errors.brand"
                                    :aria-describedby="
                                        form.errors.brand
                                            ? 'brand-error'
                                            : undefined
                                    "
                                />
                                <InputError
                                    id="brand-error"
                                    :message="form.errors.brand"
                                />
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
                            <InputError :message="form.errors.is_active" />
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
                            <!-- Show both whole-list and individual tag validation errors. -->
                            <InputError :message="form.errors.tag_ids" />
                            <InputError
                                v-for="(message, field) in form.errors"
                                :key="field"
                                :message="
                                    String(field).startsWith('tag_ids.')
                                        ? message
                                        : undefined
                                "
                            />
                        </fieldset>

                        <!-- Stack buttons on phones and align them right on wider screens. -->
                        <div
                            class="flex flex-col gap-2 sm:flex-row sm:justify-end"
                        >
                            <Button variant="outline" as-child>
                                <Link href="/products">Cancel</Link>
                            </Button>
                            <!-- Block repeated saves while the request is running. -->
                            <Button type="submit" :disabled="form.processing">
                                {{
                                    form.processing
                                        ? 'Saving...'
                                        : 'Save changes'
                                }}
                            </Button>
                        </div>
                    </form>
                </div>
            </CardContent>
        </Card>
    </main>
</template>
