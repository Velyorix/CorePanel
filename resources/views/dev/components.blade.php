<x-layout.app title="Component showcase">
    <x-slot:sidebar>
        <nav class="space-y-1 text-body-sm" aria-label="{{ __('Showcase sections') }}">
            <p class="px-3 pb-2 text-small font-semibold uppercase tracking-wide text-muted-foreground">
                {{ __('Sections') }}
            </p>
            @foreach ([
                'theme' => __('Theme'),
                'buttons' => __('Buttons'),
                'forms' => __('Forms'),
                'feedback' => __('Feedback'),
                'containers' => __('Containers'),
                'navigation' => __('Navigation'),
                'table' => __('Table'),
                'states' => __('States'),
            ] as $id => $label)
                <a
                    href="#{{ $id }}"
                    class="block rounded-md px-3 py-2 font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground"
                >
                    {{ $label }}
                </a>
            @endforeach
        </nav>
    </x-slot:sidebar>

    <x-slot:topbar>
        <div class="ml-auto flex items-center gap-3">
            <x-ui.badge variant="warning">{{ __('Dev only') }}</x-ui.badge>
            <x-ui.theme-toggle />
        </div>
    </x-slot:topbar>

    <div class="mx-auto max-w-5xl space-y-10">
        <div>
            <h1>{{ __('Component showcase') }}</h1>
            <p class="mt-2 text-muted-foreground">
                {{ __('Local gallery for CorePanel Blade UI components. Disabled outside local unless explicitly enabled.') }}
            </p>
        </div>

        <section id="theme" class="scroll-mt-20 space-y-4">
            <h2 class="text-h2">{{ __('Theme') }}</h2>
            <x-ui.card title="Theme toggle">
                <div class="flex flex-wrap items-center gap-4">
                    <x-ui.theme-toggle />
                    <x-ui.theme-toggle variant="cycle" />
                </div>
            </x-ui.card>
        </section>

        <section id="buttons" class="scroll-mt-20 space-y-4">
            <h2 class="text-h2">{{ __('Buttons') }}</h2>
            <x-ui.card>
                <div class="flex flex-wrap items-center gap-3">
                    <x-ui.button variant="primary">Primary</x-ui.button>
                    <x-ui.button variant="secondary">Secondary</x-ui.button>
                    <x-ui.button variant="danger">Danger</x-ui.button>
                    <x-ui.button variant="ghost">Ghost</x-ui.button>
                    <x-ui.button variant="primary" size="sm">Small</x-ui.button>
                    <x-ui.button variant="primary" size="lg">Large</x-ui.button>
                    <x-ui.button variant="secondary" disabled>Disabled</x-ui.button>
                    <x-ui.button href="#buttons" variant="primary">As link</x-ui.button>
                </div>
            </x-ui.card>
        </section>

        <section id="forms" class="scroll-mt-20 space-y-4">
            <h2 class="text-h2">{{ __('Forms') }}</h2>
            <x-ui.card>
                <div class="grid gap-4 md:grid-cols-2">
                    <x-ui.input name="showcase_email" label="Email" type="email" hint="Work email address" placeholder="you@example.com" />
                    <x-ui.input name="showcase_password" label="Password" type="password" error="Password is required." />
                    <x-ui.select name="showcase_role" label="Role">
                        <option value="">Choose a role</option>
                        <option value="admin">Admin</option>
                        <option value="support">Support</option>
                    </x-ui.select>
                    <div class="flex flex-wrap items-end gap-2">
                        <x-ui.badge>Neutral</x-ui.badge>
                        <x-ui.badge variant="primary">Primary</x-ui.badge>
                        <x-ui.badge variant="success">Success</x-ui.badge>
                        <x-ui.badge variant="warning">Warning</x-ui.badge>
                        <x-ui.badge variant="danger">Danger</x-ui.badge>
                    </div>
                </div>
            </x-ui.card>
        </section>

        <section id="feedback" class="scroll-mt-20 space-y-4">
            <h2 class="text-h2">{{ __('Feedback') }}</h2>
            <div class="space-y-3">
                <x-ui.alert variant="info" title="Info">Informational message for operators.</x-ui.alert>
                <x-ui.alert variant="success" title="Success">Changes were saved.</x-ui.alert>
                <x-ui.alert variant="warning" title="Warning">This action needs review.</x-ui.alert>
                <x-ui.alert variant="danger" title="Error">Unable to complete the request.</x-ui.alert>
            </div>
        </section>

        <section id="containers" class="scroll-mt-20 space-y-4">
            <h2 class="text-h2">{{ __('Containers') }}</h2>
            <x-ui.card title="Card title">
                <x-slot:actions>
                    <x-ui.button size="sm" variant="secondary">Action</x-ui.button>
                </x-slot:actions>
                Card body content with design tokens.
                <x-slot:footer>
                    <div class="flex justify-end">
                        <x-ui.button size="sm" variant="primary">Save</x-ui.button>
                    </div>
                </x-slot:footer>
            </x-ui.card>

            <div class="flex flex-wrap gap-3">
                <x-ui.button type="button" variant="primary" x-on:click="$dispatch('open-modal', 'showcase-modal')">
                    Open modal
                </x-ui.button>
            </div>
        </section>

        <section id="navigation" class="scroll-mt-20 space-y-4">
            <h2 class="text-h2">{{ __('Navigation') }}</h2>
            <x-ui.card>
                <div class="flex flex-wrap items-center gap-4">
                    <x-ui.dropdown>
                        <x-slot:trigger>
                            <x-ui.button type="button" variant="secondary" size="sm">Dropdown</x-ui.button>
                        </x-slot:trigger>
                        <x-ui.dropdown-item href="#navigation">Item one</x-ui.dropdown-item>
                        <x-ui.dropdown-item type="button">Item two</x-ui.dropdown-item>
                    </x-ui.dropdown>
                </div>

                <div class="mt-6">
                    <x-ui.tabs
                        :items="[
                            ['name' => 'overview', 'label' => 'Overview'],
                            ['name' => 'settings', 'label' => 'Settings'],
                        ]"
                        active="overview"
                    >
                        <x-slot:overview>
                            <p>Overview tab panel content.</p>
                        </x-slot:overview>
                        <x-slot:settings>
                            <p>Settings tab panel content.</p>
                        </x-slot:settings>
                    </x-ui.tabs>
                </div>
            </x-ui.card>
        </section>

        <section id="table" class="scroll-mt-20 space-y-4">
            <h2 class="text-h2">{{ __('Table') }}</h2>
            <x-ui.card :padding="false">
                <div class="p-5">
                    <x-ui.table :paginator="$paginator">
                        <x-slot:filters>
                            <x-ui.input name="q" label="Search" placeholder="Filter…" />
                        </x-slot:filters>
                        <x-slot:actions>
                            <x-ui.button size="sm" variant="danger">Bulk delete</x-ui.button>
                        </x-slot:actions>
                        <x-slot:head>
                            <tr>
                                <x-ui.table-heading sort="name">Name</x-ui.table-heading>
                                <x-ui.table-heading sort="status">Status</x-ui.table-heading>
                            </tr>
                        </x-slot:head>
                        @foreach ($paginator as $row)
                            <tr class="hover:bg-muted/40">
                                <td class="px-4 py-3 font-medium">{{ $row['name'] }}</td>
                                <td class="px-4 py-3">
                                    <x-ui.badge :variant="$row['status'] === 'active' ? 'success' : 'warning'">
                                        {{ ucfirst($row['status']) }}
                                    </x-ui.badge>
                                </td>
                            </tr>
                        @endforeach
                    </x-ui.table>
                </div>
            </x-ui.card>
        </section>

        <section id="states" class="scroll-mt-20 space-y-4">
            <h2 class="text-h2">{{ __('States') }}</h2>
            <div class="grid gap-4 md:grid-cols-2">
                <x-ui.card title="Skeleton">
                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <x-ui.skeleton variant="avatar" />
                            <div class="flex-1">
                                <x-ui.skeleton variant="line" :lines="2" />
                            </div>
                        </div>
                        <x-ui.skeleton variant="rect" class="h-20" />
                        <x-ui.skeleton variant="button" />
                    </div>
                </x-ui.card>

                <x-ui.card title="Empty" :padding="false">
                    <div class="p-5">
                        <x-ui.empty
                            title="No results"
                            description="Try adjusting filters or create a new resource."
                        >
                            <x-slot:actions>
                                <x-ui.button size="sm" variant="primary">Create</x-ui.button>
                            </x-slot:actions>
                        </x-ui.empty>
                    </div>
                </x-ui.card>

                <x-ui.card title="Error state" class="md:col-span-2" :padding="false">
                    <div class="p-5">
                        <x-ui.error-state
                            title="Unable to load resources"
                            description="Please try again in a moment."
                            code="503"
                        >
                            <x-slot:actions>
                                <x-ui.button size="sm" variant="secondary">Retry</x-ui.button>
                            </x-slot:actions>
                        </x-ui.error-state>
                    </div>
                </x-ui.card>
            </div>
        </section>
    </div>

    <x-ui.modal name="showcase-modal" title="Showcase modal" size="sm">
        <p>Modals open via Alpine <code class="text-small">open-modal</code> / <code class="text-small">close-modal</code> events.</p>
        <x-slot:footer>
            <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'showcase-modal')">
                Close
            </x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</x-layout.app>
