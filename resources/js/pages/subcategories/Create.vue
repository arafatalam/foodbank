<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
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

// Shared page data should not be copied onto HTML elements.
defineOptions({ inheritAttrs: false });

const props = defineProps<{
    departments: { id: number; name: string }[];
    categories: { id: number; name: string; department_id: number }[];
}>();

// These values remain temporary until Laravel accepts the form.
const form = useForm({ department_id: '', category_id: '', name: '' });

// Recalculate the choices whenever the selected department changes.
const availableCategories = computed(() =>
    props.categories.filter(
        (category) => String(category.department_id) === form.department_id,
    ),
);

// An old category must not remain selected under a new department.
watch(
    () => form.department_id,
    () => {
        form.category_id = '';
        form.clearErrors('category_id');
    },
);

function submit() {
    // Keep the current values on errors; clear the form only after success.
    form.post('/subcategories', { onSuccess: () => form.reset() });
}
</script>

<template>
    <Head title="Create Subcategory" />
    <main class="p-4">
        <Card class="w-full max-w-5xl">
            <CardHeader><CardTitle>Create Subcategory</CardTitle></CardHeader>
            <CardContent>
                <div class="mt-4 rounded-xl border border-border p-4">
                    <!-- Stack on smaller screens; align all fields on wider screens. -->
                    <form
                        @submit.prevent="submit"
                        class="flex flex-col gap-4 lg:flex-row lg:items-start"
                    >
                        <div class="flex min-w-0 flex-1 flex-col gap-2">
                            <Label for="department_id">Department</Label>
                            <Select v-model="form.department_id">
                                <SelectTrigger
                                    id="department_id"
                                    class="w-full"
                                    :aria-invalid="!!form.errors.department_id"
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

                        <div class="flex min-w-0 flex-1 flex-col gap-2">
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
                                    :aria-invalid="!!form.errors.category_id"
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

                        <div class="flex min-w-0 flex-1 flex-col gap-2">
                            <Label for="name">Subcategory name</Label>
                            <Input
                                id="name"
                                name="name"
                                type="text"
                                v-model="form.name"
                                maxlength="255"
                                :aria-invalid="!!form.errors.name"
                                :aria-describedby="
                                    form.errors.name ? 'name-error' : undefined
                                "
                            />
                            <InputError
                                id="name-error"
                                :message="form.errors.name"
                            />
                        </div>

                        <!-- Match the input height and use full width on smaller screens. -->
                        <div class="lg:pt-6">
                            <Button
                                type="submit"
                                class="w-full lg:w-auto"
                                :disabled="form.processing"
                            >
                                {{
                                    form.processing
                                        ? 'Saving...'
                                        : 'Create subcategory'
                                }}
                            </Button>
                        </div>
                    </form>
                </div>
            </CardContent>
        </Card>
    </main>
</template>
