<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    Boxes,
    CircleUserRound,
    FolderTree,
    LayoutGrid,
    Layers,
    Package,
    Tags,
    Users,
    UserPlus,
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as categoriesIndex } from '@/routes/categories';
import { index as departmentsIndex } from '@/routes/departments';
import { index as productsIndex } from '@/routes/products';
import { index as subcategoriesIndex } from '@/routes/subcategories';
import { index as tagsIndex } from '@/routes/tags';
import { create, index, show } from '@/routes/users';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

// Access the current Inertia page and its shared information.
const page = usePage();
const { currentUrl } = useCurrentUrl();

const overviewItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

// Match a section's list and its child pages, without matching similar names.
function inSection(href: NavItem['href']) {
    const path = toUrl(href);
    return currentUrl.value === path || currentUrl.value.startsWith(`${path}/`);
}

// Recalculate links when Inertia updates permissions or the current page.
const userItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [];
    const ownDetails = toUrl(show(page.props.auth.user.id));
    if (page.props.auth.navigation.users) {
        items.push({
            title: 'Users',
            href: index(),
            icon: Users,
            isActive:
                inSection(index()) &&
                currentUrl.value !== toUrl(create()) &&
                currentUrl.value !== ownDetails,
        });
    }
    if (page.props.auth.navigation.createUser) {
        items.push({ title: 'Create user', href: create(), icon: UserPlus });
    }
    return items;
});

const catalogueItems = computed<NavItem[]>(() => {
    const permissions = page.props.auth.navigation;
    // Products are the everyday destination; classification tools follow them.
    const entries = [
        {
            title: 'Products',
            href: productsIndex(),
            icon: Package,
            allowed: permissions.products,
        },
        {
            title: 'Departments',
            href: departmentsIndex(),
            icon: Boxes,
            allowed: permissions.departments,
        },
        {
            title: 'Categories',
            href: categoriesIndex(),
            icon: FolderTree,
            allowed: permissions.categories,
        },
        {
            title: 'Subcategories',
            href: subcategoriesIndex(),
            icon: Layers,
            allowed: permissions.subcategories,
        },
        {
            title: 'Tags',
            href: tagsIndex(),
            icon: Tags,
            allowed: permissions.tags,
        },
    ];
    return entries
        .filter((entry) => entry.allowed)
        .map((item) => ({
            title: item.title,
            href: item.href,
            icon: item.icon,
            isActive: inSection(item.href),
        }));
});

const accountItems = computed<NavItem[]>(() => [
    {
        title: 'My details',
        href: show(page.props.auth.user.id),
        icon: CircleUserRound,
    },
]);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="gap-4">
            <NavMain label="Overview" :items="overviewItems" />
            <NavMain
                v-if="userItems.length"
                label="User Management"
                :items="userItems"
            />
            <NavMain
                v-if="catalogueItems.length"
                label="Product Catalogue"
                :items="catalogueItems"
            />
            <NavMain label="My Account" :items="accountItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
