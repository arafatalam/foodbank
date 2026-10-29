<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
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

// Describe the saved account Laravel will send to this page.
const props = defineProps<{
    user: {
        id: number;
        first_name: string;
        last_name: string;
        username: string;
        email: string;
        phone: string;
        role_id: number;
        is_active: boolean;
    };
    roles: { id: number; label: string }[];
}>();

// Start the editable form with the account's saved contact details.
const form = useForm({
    first_name: props.user.first_name,
    last_name: props.user.last_name,
    email: props.user.email,
    phone: props.user.phone,
    // Match the saved role ID to the dropdown's text values.
    role_id: String(props.user.role_id),
    // Start the status choices with the saved true/false value.
    is_active: props.user.is_active,
});

// Send all editable fields to the existing update route.
function submit() {
    // Describe only role and status values that differ from the saved account.
    const changes: string[] = [];

    if (form.role_id !== String(props.user.role_id)) {
        const currentRole = props.roles.find(
            (role) => role.id === props.user.role_id,
        )?.label;
        const selectedRole = props.roles.find(
            (role) => String(role.id) === form.role_id,
        )?.label;

        changes.push(`Change role from ${currentRole} to ${selectedRole}.`);
    }

    if (form.is_active !== props.user.is_active) {
        changes.push(
            form.is_active
                ? 'Activate this account? The user will be allowed to sign in again.'
                : 'Deactivate this account? The user will lose access and their existing sessions will end.',
        );
    }

    // Confirm both changes together; Cancel prevents the entire save request.
    if (changes.length > 0 && !window.confirm(changes.join('\n\n'))) {
        return;
    }

    form.patch(`/users/${props.user.id}`);
}

// Use the same Canadian phone formatting as the Create page.
function updatePhone(value: string | number) {
    // Convert the input to text and remove an existing country prefix.
    const text = String(value);
    const localNumber = text.startsWith('+1') ? text.slice(2) : text;

    // Keep only the ten local phone digits.
    const digits = localNumber.replace(/\D/g, '').slice(0, 10);

    // Build the first group of digits.
    let formatted = digits.slice(0, 3);

    // Add the second group when enough digits exist.
    if (digits.length > 3) {
        formatted += '-' + digits.slice(3, 6);
    }

    // Add the final group when enough digits exist.
    if (digits.length > 6) {
        formatted += '-' + digits.slice(6);
    }

    // Add the Canadian prefix, or leave an empty field.
    form.phone = digits ? '+1 ' + formatted : '';
}
</script>

<template>
    <!-- Match the Show page's surrounding space and card width. -->
    <main class="p-4">
        <Card class="w-full max-w-3xl">
            <CardHeader>
                <CardTitle>Edit User</CardTitle>
            </CardHeader>

            <CardContent>
                <!-- Match the Show page's inner border and rounded corners. -->
                <div
                    class="mt-4 overflow-hidden rounded-xl border border-border p-4"
                >
                    <!-- Stack the username row, details grid and buttons. -->
                    <form @submit.prevent="submit" class="flex flex-col gap-4">
                        <!-- Username occupies the first column of its own row. -->
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="flex flex-col gap-2">
                                <Label for="username">Username</Label>
                                <Input
                                    id="username"
                                    :model-value="user.username"
                                    readonly
                                />
                            </div>
                        </div>

                        <!-- Stack fields on narrow screens; use two columns at md. -->
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <!-- Bind this input to the editable first-name value. -->
                            <div class="flex flex-col gap-2">
                                <Label for="first_name">First name</Label>
                                <Input
                                    id="first_name"
                                    name="first_name"
                                    type="text"
                                    autocomplete="given-name"
                                    v-model="form.first_name"
                                />
                                <InputError :message="form.errors.first_name" />
                            </div>

                            <!-- Bind this input to the editable last-name value. -->
                            <div class="flex flex-col gap-2">
                                <Label for="last_name">Last name</Label>
                                <Input
                                    id="last_name"
                                    name="last_name"
                                    type="text"
                                    autocomplete="family-name"
                                    v-model="form.last_name"
                                />
                                <InputError :message="form.errors.last_name" />
                            </div>

                            <!-- Keep email changes in the local form until Save. -->
                            <div class="flex flex-col gap-2">
                                <Label for="email">Email</Label>
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    autocomplete="email"
                                    v-model="form.email"
                                />
                                <InputError :message="form.errors.email" />
                            </div>

                            <!-- Format the phone value as the user types. -->
                            <div class="flex flex-col gap-2">
                                <Label for="phone">Phone</Label>
                                <Input
                                    id="phone"
                                    name="phone"
                                    type="tel"
                                    autocomplete="tel"
                                    placeholder="+1 506-555-0123"
                                    maxlength="15"
                                    :model-value="form.phone"
                                    @update:model-value="updatePhone"
                                />
                                <InputError :message="form.errors.phone" />
                            </div>

                            <!-- Use the same role dropdown as Create User. -->
                            <div class="flex flex-col gap-2">
                                <Label for="role">Role</Label>
                                <Select v-model="form.role_id">
                                    <SelectTrigger id="role" class="w-full">
                                        <SelectValue
                                            placeholder="Select a role"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="option in roles"
                                            :key="option.id"
                                            :value="String(option.id)"
                                        >
                                            {{ option.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="form.errors.role_id" />
                            </div>

                            <!-- Match Create User's layout with linked status checkboxes. -->
                            <div class="flex flex-col gap-2">
                                <Label id="status-label">Status</Label>
                                <div
                                    class="flex gap-20"
                                    role="group"
                                    aria-labelledby="status-label"
                                >
                                    <label class="flex items-center gap-2">
                                        <!-- Check Active when the saved value is true. -->
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
                                        <!-- Check Inactive when the saved value is false. -->
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
                            </div>
                        </div>

                        <!-- Put the buttons on the right and allow wrapping. -->
                        <div class="flex flex-wrap justify-end gap-2">
                            <!-- Return to saved details without submitting changes. -->
                            <Button variant="outline" as-child>
                                <Link :href="`/users/${user.id}`">
                                    Cancel
                                </Link>
                            </Button>

                            <!-- Prevent another submission while saving. -->
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
