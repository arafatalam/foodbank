<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';

// Starting values for a new account.
const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    role_id: '',
    is_active: true,
});

function submit() {
    form.post('/users');
}

defineProps<{
    roles: {
        id: number;
        label: string;
    }[];
}>();
</script>

<template>
    <main class="p-4">
        <Card class="w-full max-w-3xl">
            <CardHeader>
                <CardTitle>Create User</CardTitle>
            </CardHeader>

            <CardContent>
                <form
                    @submit.prevent="submit"
                    class="grid grid-cols-1 gap-4 md:grid-cols-2"
                >
                    <div class="flex flex-col gap-2">
                        <Label for="first_name">First name</Label>
                        <Input
                            id="first_name"
                            name="first_name"
                            type="text"
                            v-model="form.first_name"
                        />
                        <!-- Show Laravel's first-name error when one exists. -->
                        <InputError :message="form.errors.first_name" />
                    </div>

                    <div class="flex flex-col gap-2">
                        <Label for="last_name">Last name</Label>
                        <Input
                            id="last_name"
                            name="last_name"
                            type="text"
                            v-model="form.last_name"
                        />
                        <InputError :message="form.errors.last_name" />
                    </div>

                    <div class="flex flex-col gap-2">
                        <Label for="email">Email</Label>
                        <Input
                            id="email"
                            name="email"
                            type="email"
                            v-model="form.email"
                        />
                        <InputError :message="form.errors.email" />
                    </div>

                    <div class="flex flex-col gap-2">
                        <Label for="phone">Phone</Label>
                        <Input
                            id="phone"
                            name="phone"
                            type="tel"
                            placeholder="+1 506-555-0123"
                            v-model="form.phone"
                        />
                        <InputError :message="form.errors.phone" />
                    </div>

                    <div class="flex flex-col gap-2">
                        <Label for="role">Role</Label>

                        <Select v-model="form.role_id">
                            <SelectTrigger id="role" class="w-full">
                                <SelectValue placeholder="Select a role" />
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

                    <!-- Group the two choices under one accessible Status heading. -->
                    <fieldset class="flex flex-col gap-2">
                        <legend>Status</legend>

                        <!-- Arrange the choices beside each other. -->
                        <div class="flex gap-20">
                            <label class="flex items-center gap-2">
                                <!-- Selecting this option sets isActive to true. -->
                                <input
                                    type="radio"
                                    name="status"
                                    v-model="form.is_active"
                                    :value="true"
                                />
                                Active
                            </label>

                            <label class="flex items-center gap-2">
                                <!-- Selecting this option sets isActive to false. -->
                                <input
                                    type="radio"
                                    name="status"
                                    v-model="form.is_active"
                                    :value="false"
                                />
                                Inactive
                            </label>
                        </div>
                        <InputError :message="form.errors.is_active" />
                    </fieldset>

                    <div class="flex justify-end md:col-span-2">
                        <Button type="submit" :disabled="form.processing">
                            {{ form.processing ? 'Saving...' : 'Create user' }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </main>
</template>

<style scoped></style>
