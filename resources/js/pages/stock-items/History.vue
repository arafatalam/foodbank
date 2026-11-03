<script setup lang="ts">
import PageHeading from '@/components/PageHeading.vue';
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    HandHeart,
    History,
    Package,
    PackageCheck,
    SlidersHorizontal,
} from '@lucide/vue';

// Describe one movement received from Laravel.
type Movement = {
    id: number;
    type: 'receive' | 'distribute' | 'adjust';
    direction: 'add' | 'remove';
    quantity: number;
    resulting_balance: number;
    recorded_at: string;
    recorder: string;
    notes: string | null;
    reason: string | null;
    household: string | null;
};

// Describe the selected stock item received from Laravel.
type StockItem = {
    id: number;
    product: string;
    path: string;
    brand: string;
    package: string;
    available_quantity: number;
};

// Use the named type to describe the stockItem page prop.
const props = defineProps<{
    stockItem: StockItem;
    backLink: { label: string; href: string };
    movements: {
        data: Movement[];
        // Laravel supplies the page numbers and links alongside the movements.
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();

// Display a saved timestamp in the viewer's local time zone.
function formatRecordedAt(value: string): string {
    return new Date(value).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

// Get the local calendar date used to group movements.
function movementDate(value: string): string {
    return new Date(value).toLocaleDateString(undefined, {
        dateStyle: 'long',
    });
}

// Turn the saved entry types into readable movement headings.
const movementLabels = {
    receive: 'Stock received',
    distribute: 'Stock distributed',
    adjust: 'Stock adjusted',
};

// Choose a distinct icon for each kind of movement, regardless of direction.
const movementIcons = {
    receive: PackageCheck,
    distribute: HandHeart,
    adjust: SlidersHorizontal,
};

// Supply breadcrumbs to the existing application layout.
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Stock items', href: '/stock-items' },
            { title: 'History', href: '#' },
        ],
    },
});
</script>

<template>
    <Head title="Stock item history" />

    <main class="mx-auto w-full max-w-5xl space-y-6 p-4 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <PageHeading
                title="Stock item history"
                description="Every receipt, distribution and adjustment, in order."
                :icon="History"
            />
            <Button as-child variant="outline" size="sm">
                <Link :href="props.backLink.href"
                    ><ArrowLeft aria-hidden="true" />{{
                        props.backLink.label
                    }}</Link
                >
            </Button>
        </div>

        <!-- Stack identity and balance on phones; place them side by side on wider screens. -->
        <section
            aria-labelledby="item-heading"
            class="rounded-xl border bg-card text-card-foreground shadow-sm"
        >
            <div
                class="flex flex-col gap-6 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6"
            >
                <div class="flex min-w-0 items-start gap-3">
                    <div class="shrink-0 rounded-lg bg-muted p-3">
                        <Package class="size-5" aria-hidden="true" />
                    </div>
                    <div class="min-w-0 space-y-1 break-words">
                        <h2 id="item-heading" class="text-xl font-semibold">
                            <!-- Display the product name supplied by Laravel. -->
                            {{ props.stockItem.product }}
                        </h2>
                        <!-- Show the product's current department and category. -->
                        <p class="text-sm text-muted-foreground">
                            {{ props.stockItem.path }}
                        </p>
                        <!-- Show the brand and package together to identify this stock choice. -->
                        <p class="text-sm text-muted-foreground">
                            {{ props.stockItem.brand }} ·
                            {{ props.stockItem.package }}
                        </p>
                    </div>
                </div>
                <div
                    class="shrink-0 border-t pt-4 sm:border-t-0 sm:border-l sm:pt-0 sm:pl-8"
                >
                    <p class="text-xs font-medium text-muted-foreground">
                        Current balance
                    </p>
                    <p class="mt-1 text-3xl font-semibold tabular-nums">
                        {{ props.stockItem.available_quantity }}
                        <span class="text-sm font-normal text-muted-foreground"
                            >packages</span
                        >
                    </p>
                </div>
            </div>
        </section>

        <section
            aria-labelledby="timeline-heading"
            class="overflow-hidden rounded-xl border bg-card text-card-foreground shadow-sm"
        >
            <header
                class="flex flex-wrap items-center justify-between gap-2 border-b px-5 py-4 sm:px-6"
            >
                <h2 id="timeline-heading" class="font-semibold">
                    Movement history
                </h2>
                <p class="text-xs text-muted-foreground">Newest first</p>
            </header>

            <div class="p-5 sm:p-8">
                <!-- Explain why the list is empty for an item with no movements. -->
                <div
                    v-if="props.movements.data.length === 0"
                    class="flex flex-col items-center gap-3 px-4 py-10 text-center"
                >
                    <History
                        class="size-8 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <p class="font-medium">No movements yet</p>
                    <p class="max-w-sm text-sm text-muted-foreground">
                        Receipts, distributions and adjustments for this stock
                        item will appear here.
                    </p>
                </div>
                <!-- Otherwise, display the saved movements. -->
                <ul v-else>
                    <li
                        v-for="(movement, index) in props.movements.data"
                        :key="movement.id"
                    >
                        <!-- Show a heading for the first movement and each new calendar date. -->
                        <h3
                            v-if="
                                index === 0 ||
                                movementDate(movement.recorded_at) !==
                                    movementDate(
                                        props.movements.data[index - 1]
                                            .recorded_at,
                                    )
                            "
                            class="mb-5 text-sm font-semibold"
                        >
                            {{ movementDate(movement.recorded_at) }}
                        </h3>
                        <!-- Keep the line beside the details and stop it at the end of each date group. -->
                        <div
                            class="relative ml-4 border-l pb-8 pl-8 sm:pl-10"
                            :class="{
                                'border-transparent':
                                    index === props.movements.data.length - 1 ||
                                    movementDate(movement.recorded_at) !==
                                        movementDate(
                                            props.movements.data[index + 1]
                                                .recorded_at,
                                        ),
                            }"
                        >
                            <!-- Position each marker on the timeline relative to its movement container. -->
                            <span
                                aria-hidden="true"
                                class="absolute top-0 -left-4 flex size-8 items-center justify-center rounded-full border bg-card text-muted-foreground"
                            >
                                <component
                                    :is="movementIcons[movement.type]"
                                    class="size-4"
                                />
                            </span>
                            <!-- Stack on phones; give the balance its own column on wider screens. -->
                            <div
                                class="grid min-w-0 gap-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:gap-6"
                            >
                                <!-- Space the details apart and let long text wrap inside the timeline. -->
                                <div class="min-w-0 space-y-2 break-words">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <h4 class="font-medium">
                                            {{ movementLabels[movement.type] }}
                                        </h4>
                                        <span
                                            class="rounded-md bg-muted px-2 py-1 text-xs font-medium tabular-nums"
                                        >
                                            {{
                                                movement.direction === 'add'
                                                    ? '+'
                                                    : '−'
                                            }}{{ movement.quantity }}
                                            {{
                                                movement.quantity === 1
                                                    ? 'package'
                                                    : 'packages'
                                            }}
                                        </span>
                                    </div>
                                    <!-- Display the recorder's name supplied by Laravel. -->
                                    <p class="text-xs text-muted-foreground">
                                        Recorded by {{ movement.recorder }}
                                    </p>
                                    <!-- Turn the saved timestamp into a readable local date and time. -->
                                    <time
                                        :datetime="movement.recorded_at"
                                        class="block text-xs text-muted-foreground"
                                    >
                                        {{
                                            formatRecordedAt(
                                                movement.recorded_at,
                                            )
                                        }}
                                    </time>
                                    <!-- Show a reason only when this movement has one. -->
                                    <p
                                        v-if="movement.reason"
                                        class="text-sm whitespace-pre-line"
                                    >
                                        <span class="font-medium">Reason:</span>
                                        {{ movement.reason }}
                                    </p>
                                    <!-- Show the household only when this movement has one. -->
                                    <p
                                        v-if="movement.household"
                                        class="text-sm"
                                    >
                                        <span class="font-medium"
                                            >Household:</span
                                        >
                                        {{ movement.household }}
                                    </p>
                                    <!-- Show receipt notes only when notes were saved. -->
                                    <p
                                        v-if="movement.notes"
                                        class="text-sm whitespace-pre-line"
                                    >
                                        <span class="font-medium">Notes:</span>
                                        {{ movement.notes }}
                                    </p>
                                </div>
                                <!-- Show the balance saved after this movement. -->
                                <div
                                    class="self-start rounded-lg bg-muted/50 px-3 py-2 sm:min-w-32 sm:text-right"
                                >
                                    <p class="text-xs text-muted-foreground">
                                        Balance afterward
                                    </p>
                                    <p
                                        class="mt-1 text-lg font-semibold tabular-nums"
                                    >
                                        {{ movement.resulting_balance }}
                                        <span
                                            class="text-xs font-normal text-muted-foreground"
                                        >
                                            {{
                                                movement.resulting_balance === 1
                                                    ? 'package'
                                                    : 'packages'
                                            }}
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>
            <!-- Wrap the page controls when the screen is narrow. -->
            <nav
                aria-label="Movement history pages"
                class="flex flex-wrap items-center justify-between gap-3 border-t px-5 py-4 sm:px-6"
            >
                <p class="text-sm text-muted-foreground">
                    Page {{ props.movements.current_page }} of
                    {{ props.movements.last_page }}
                </p>
                <div class="flex gap-2">
                    <!-- Only show a link when Laravel provides a destination. -->
                    <Button
                        v-if="props.movements.prev_page_url"
                        as-child
                        variant="outline"
                        size="sm"
                    >
                        <Link :href="props.movements.prev_page_url"
                            >Previous</Link
                        >
                    </Button>
                    <Button
                        v-if="props.movements.next_page_url"
                        as-child
                        variant="outline"
                        size="sm"
                    >
                        <Link :href="props.movements.next_page_url">Next</Link>
                    </Button>
                </div>
            </nav>
        </section>
    </main>
</template>
