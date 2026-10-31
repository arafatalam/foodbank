<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
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
    product?: {
        id: number;
        name: string;
        department_id: number;
        category_id: number;
    };
}>();
// Status is supplied by the server, so it is not part of the editable form.
// Both pages share the same fields and duplicate-confirmation behaviour.
const form = useForm({
    department_id: String(props.product?.department_id ?? ''),
    category_id: String(props.product?.category_id ?? ''),
    name: props.product?.name ?? '',
    confirm_duplicate_name: false,
});
const availableCategories = computed(() =>
    props.categories.filter(
        (category) => String(category.department_id) === form.department_id,
    ),
);
watch(
    () => form.department_id,
    () => {
        form.category_id = '';
    },
);
// An acknowledgement applies only to the name/category the user just reviewed.
watch(
    () => [form.name, form.category_id, form.department_id],
    () => {
        form.confirm_duplicate_name = false;
        form.clearErrors('confirm_duplicate_name');
    },
    { flush: 'sync' },
);
function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            if (!props.product) form.reset();
        },
    };
    if (props.product) form.patch(`/products/${props.product.id}`, options);
    else form.post('/products', options);
}
</script>
<template>
    <form @submit.prevent="submit" novalidate class="grid gap-6">
        <!-- The parent grid stacks choices on phones and aligns them on wider screens. -->
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid min-w-0 gap-2">
                <Label for="department_id">Department</Label>
                <Select v-model="form.department_id">
                    <SelectTrigger id="department_id" class="w-full"
                        ><SelectValue placeholder="Select a department"
                    /></SelectTrigger>
                    <SelectContent
                        ><SelectItem
                            v-for="department in departments"
                            :key="department.id"
                            :value="String(department.id)"
                            >{{ department.name }}</SelectItem
                        ></SelectContent
                    >
                </Select>
                <InputError :message="form.errors.department_id" />
            </div>
            <div class="grid min-w-0 gap-2">
                <Label for="category_id">Category</Label>
                <Select
                    :key="form.department_id"
                    v-model="form.category_id"
                    :disabled="
                        !form.department_id || !availableCategories.length
                    "
                >
                    <SelectTrigger id="category_id" class="w-full"
                        ><SelectValue
                            :placeholder="
                                !form.department_id
                                    ? 'Select a department first'
                                    : !availableCategories.length
                                      ? 'No categories available'
                                      : 'Select a category'
                            "
                    /></SelectTrigger>
                    <SelectContent
                        ><SelectItem
                            v-for="category in availableCategories"
                            :key="category.id"
                            :value="String(category.id)"
                            >{{ category.name }}</SelectItem
                        ></SelectContent
                    >
                </Select>
                <InputError :message="form.errors.category_id" />
            </div>
        </div>
        <div class="grid gap-2">
            <Label for="name">Product name</Label>
            <Input
                id="name"
                v-model="form.name"
                name="name"
                maxlength="255"
                autocomplete="off"
            />
            <InputError :message="form.errors.name" />
        </div>
        <!-- Laravel returns matching paths before saving anything. The user may cancel or confirm. -->
        <div
            v-if="form.errors.confirm_duplicate_name"
            role="alert"
            class="grid gap-3 rounded-lg border border-border p-4"
        >
            <p class="text-sm break-words">
                {{ form.errors.confirm_duplicate_name }}
            </p>
            <label class="flex items-start gap-2"
                ><input
                    v-model="form.confirm_duplicate_name"
                    type="checkbox"
                    class="mt-1"
                /><span
                    >I have reviewed the existing products and want to
                    continue.</span
                ></label
            >
        </div>
        <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
            <Button as-child variant="outline"
                ><Link href="/products">Cancel</Link></Button
            >
            <Button
                type="submit"
                :disabled="
                    form.processing ||
                    (!!form.errors.confirm_duplicate_name &&
                        !form.confirm_duplicate_name)
                "
                >{{
                    form.processing
                        ? 'Saving...'
                        : form.confirm_duplicate_name
                          ? 'Confirm and save'
                          : product
                            ? 'Save changes'
                            : 'Create product'
                }}</Button
            >
        </div>
    </form>
</template>
