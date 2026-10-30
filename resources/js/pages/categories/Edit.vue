<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
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

// Receive the saved category from the controller.
const props = defineProps<{
    departments: { id: number; name: string }[];
    category: {
        id: number;
        name: string;
        department: { id: number; name: string };
    };
}>();

// Keep unsaved changes separate from the saved category details.
const form = useForm({
    name: props.category.name,
    department_id: String(props.category.department.id),
});

function submit() {
    // Ask before saving a move, following the Edit User confirmation pattern.
    if (form.department_id !== String(props.category.department.id)) {
        const selectedDepartment = props.departments.find(
            (department) => String(department.id) === form.department_id,
        );

        if (
            !window.confirm(
                `You are changing this category's department from "${props.category.department.name}" to "${selectedDepartment?.name ?? ''}". Save this change?`,
            )
        ) {
            return;
        }
    }

    // Update this existing category through its protected PATCH route.
    form.patch(`/categories/${props.category.id}`);
}

// Tell the shared layout where this page belongs.
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Categories', href: '/categories' },
            { title: 'Edit category', href: '#' },
        ],
    },
});
</script>

<template>
    <Head title="Edit Category" />

    <!-- Use the same outer spacing and card width as Edit User. -->
    <main class="p-4">
        <Card class="w-full max-w-xl">
            <CardHeader>
                <CardTitle>Edit Category</CardTitle>
            </CardHeader>

            <CardContent>
                <!-- Match Edit User's bordered form and stacked layout. -->
                <div
                    class="mt-4 overflow-hidden rounded-xl border border-border p-4"
                >
                    <form @submit.prevent="submit" class="flex flex-col gap-4">
                        <!-- Use one full-width column on every screen size. -->
                        <div class="grid grid-cols-1 gap-4">
                            <!-- Start with the saved department selected and allow another choice. -->
                            <div class="flex flex-col gap-2">
                                <Label for="department">Department</Label>
                                <Select v-model="form.department_id">
                                    <SelectTrigger
                                        id="department"
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
                                <Label for="name">Category name</Label>
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
                                    <Link href="/categories">Cancel</Link>
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
