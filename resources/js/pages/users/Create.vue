<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ref } from 'vue';
import { Switch } from '@/components/ui/switch';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

const isActive = ref(true);

defineProps<{
    roles: {
        id: number;
        label: string;
    }[];
}>();

const role = ref('');
</script>

<template>
    <main class="p-4">
        <Card class="w-full max-w-3xl">
            <CardHeader>
                <CardTitle>Create User</CardTitle>
            </CardHeader>

            <CardContent>
                <form
                    @submit.prevent
                    class="grid grid-cols-1 gap-4 md:grid-cols-2"
                >
                    <div class="flex flex-col gap-2">
                        <Label for="first_name">First name</Label>
                        <Input id="first_name" name="first_name" type="text" />
                    </div>

                    <div class="flex flex-col gap-2">
                        <Label for="last_name">Last name</Label>
                        <Input id="last_name" name="last_name" type="text" />
                    </div>

                    <div class="flex flex-col gap-2">
                        <Label for="email">Email</Label>
                        <Input id="email" name="email" type="email" />
                    </div>

                    <div class="flex flex-col gap-2">
                        <Label for="phone">Phone</Label>
                        <Input
                            id="phone"
                            name="phone"
                            type="tel"
                            placeholder="+1 506-555-0123"
                        />
                    </div>

                    <div class="flex flex-col gap-2">
                        <Label for="role">Role</Label>

                        <Select v-model="role">
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
                    </div>

                    <div class="flex items-center gap-2 place-self-center">
                        <Switch id="is_active" v-model="isActive" />

                        <Label for="is_active">
                            {{ isActive ? 'Active' : 'Inactive' }}
                        </Label>
                    </div>
                </form>
            </CardContent>
        </Card>
    </main>
</template>

<style scoped></style>
