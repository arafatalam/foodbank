<script setup lang="ts">
// Use the same shared card pieces as the create page.
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

// Describe the account details Laravel sends to this page.
defineProps<{
    user: {
        first_name: string;
        last_name: string;
        username: string;
        email: string;
        phone: string;
        role: string;
        status: string;
    };
}>();
</script>

<template>
    <!-- Padding around the whole page. -->
    <main class="p-4">
        <!-- Fill narrow screens, but limit the card width on wider screens. -->
        <Card class="w-full max-w-3xl">
            <CardHeader>
                <CardTitle>User Details</CardTitle>
            </CardHeader>

            <CardContent>
                <div
                    class="mt-4 overflow-hidden rounded-xl border border-border p-4"
                >
                    <!-- Stack the username row above the details grid. -->
                    <div class="flex flex-col gap-4">
                        <!-- This grid has two columns at md, but Username fills only the first. -->
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

                        <!-- The remaining fields start here: one column on narrow screens, two at md. -->
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <!-- First name and Last name go here first. -->
                            <div class="flex flex-col gap-2">
                                <Label for="first_name">First name</Label>
                                <Input
                                    id="first_name"
                                    :model-value="user.first_name"
                                    readonly
                                />
                            </div>

                            <div class="flex flex-col gap-2">
                                <Label for="last_name">Last name</Label>
                                <Input
                                    id="last_name"
                                    :model-value="user.last_name"
                                    readonly
                                />
                            </div>

                            <!-- Show the email sent by Laravel as read-only text. -->
                            <div class="flex flex-col gap-2">
                                <Label for="email">Email</Label>
                                <Input
                                    id="email"
                                    :model-value="user.email"
                                    readonly
                                />
                            </div>

                            <!-- Show the phone sent by Laravel as read-only text. -->
                            <div class="flex flex-col gap-2">
                                <Label for="phone">Phone</Label>
                                <Input
                                    id="phone"
                                    :model-value="user.phone"
                                    readonly
                                />
                            </div>

                            <!-- Show the readable role label sent by Laravel. -->
                            <div class="flex flex-col gap-2">
                                <Label for="role">Role</Label>
                                <Input
                                    id="role"
                                    :model-value="user.role"
                                    readonly
                                />
                            </div>

                            <!-- Show whether the account is Active or Inactive. -->
                            <div class="flex flex-col gap-2">
                                <Label for="status">Status</Label>
                                <Input
                                    id="status"
                                    :model-value="user.status"
                                    readonly
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </main>
</template>
