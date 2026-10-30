<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

// Receive the saved subcategory from the controller.
const props = defineProps<{
    departments: { id: number; name: string }[];
    categories: { id: number; name: string; department_id: number }[];
    subcategory: {
        id: number;
        name: string;
        category: {
            id: number;
            name: string;
            department: { id: number; name: string };
        };
    };
}>();

// Keep unsaved changes separate from the saved subcategory details.
const form = useForm({
    name: props.subcategory.name,
    department_id: String(props.subcategory.category.department.id),
    category_id: String(props.subcategory.category.id),
});

// Use the saved department immediately, without clearing the saved category on load.
const availableCategories = computed(() =>
    props.categories.filter(
        (category) => String(category.department_id) === form.department_id,
    ),
);

// Only a later department change clears the category choice.
watch(
    () => form.department_id,
    () => {
        form.category_id = '';
        form.clearErrors('category_id');
    },
);

function submit() {
    const selectedCategory = availableCategories.value.find(
        (category) => String(category.id) === form.category_id,
    );

    // Confirm the move before sending any changes, following Edit Category.
    if (
        selectedCategory &&
        form.category_id !== String(props.subcategory.category.id)
    ) {
        const selectedDepartment = props.departments.find(
            (department) => String(department.id) === form.department_id,
        );
        const oldPath = `${props.subcategory.category.department.name} / ${props.subcategory.category.name}`;
        const newPath = `${selectedDepartment?.name ?? ''} / ${selectedCategory.name}`;
        if (
            !window.confirm(
                `You are moving this subcategory from "${oldPath}" to "${newPath}". Save this change?`,
            )
        ) {
            return;
        }
    }

    form.patch(`/subcategories/${props.subcategory.id}`);
}

// Tell the shared layout where this page belongs.
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Subcategories', href: '/subcategories' },
            { title: 'Edit subcategory', href: '#' },
        ],
    },
});
</script>

<template>
    <Head title="Edit Subcategory" />

    <!-- Use the same outer spacing and card width as Edit User. -->
    <main class="p-4">
        <Card class="w-full max-w-xl">
            <CardHeader>
                <CardTitle>Edit Subcategory</CardTitle>
            </CardHeader>

            <CardContent>
                <!-- Match Edit User's bordered form and stacked layout. -->
                <div
                    class="mt-4 overflow-hidden rounded-xl border border-border p-4"
                >
                    <form @submit.prevent="submit" class="flex flex-col gap-4">
                        <!-- Use one full-width column on every screen size. -->
                        <div class="grid grid-cols-1 gap-4">
                            <!-- Start with saved parent choices; allow a confirmed move. -->
                            <div class="flex flex-col gap-2">
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
                                                ? 'department-error'
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
                                    id="department-error"
                                    :message="form.errors.department_id"
                                />
                            </div>

                            <div class="flex flex-col gap-2">
                                <Label for="category_id">Category</Label>
                                <!-- Wait for a department with categories before enabling this choice. -->
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
                                                ? 'category-error'
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
                                    id="category-error"
                                    :message="form.errors.category_id"
                                />
                            </div>

                            <div class="flex flex-col gap-2">
                                <Label for="name">Subcategory name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    type="text"
                                    v-model="form.name"
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

                            <!-- Align the buttons with the input's right edge. -->
                            <div class="flex flex-wrap justify-end gap-2">
                                <Button variant="outline" as-child>
                                    <Link href="/subcategories">Cancel</Link>
                                </Button>
                                <!-- Block another save while this request is running. -->
                                <Button
                                    type="submit"
                                    :disabled="form.processing"
                                >
                                    {{
                                        form.processing
                                            ? 'Saving...'
                                            : 'Save changes'
                                    }}
                                </Button>
                            </div>
                        </div>
                    </form>
                </div>
            </CardContent>
        </Card>
    </main>
</template>
