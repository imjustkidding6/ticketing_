<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ $client->name }}</h2>
            <a href="{{ route('clients.edit', $client) }}" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                {{ __('Edit Client') }}
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-full px-4 sm:px-4 lg:px-6">
            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Client Details -->
                <div class="lg:col-span-1">
                    <div class="rounded-xl bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900">{{ __('Client Details') }}</h3>
                        <dl class="mt-4 space-y-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">{{ __('Email') }}</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $client->email }}</dd>
                            </div>
                            @if($client->phone)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">{{ __('Phone') }}</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $client->phone }}</dd>
                            </div>
                            @endif
                            @if($client->contact_person)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">{{ __('Contact Person') }}</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $client->contact_person }}</dd>
                            </div>
                            @endif
                            @if($client->address)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">{{ __('Address') }}</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $client->address }}</dd>
                            </div>
                            @endif
                            <div>
                                <dt class="text-sm font-medium text-gray-500">{{ __('Tier') }}</dt>
                                <dd class="mt-1">
                                    <x-badge :type="$client->tier">{{ ucfirst($client->tier) }}</x-badge>
                                </dd>
                                @if(app(\App\Services\PlanService::class)->currentTenantHasFeature(\App\Enums\PlanFeature::SlaManagement) && $clientSlaPolicies->isNotEmpty())
                                    <dd class="mt-2">
                                        <table class="w-full text-xs">
                                            <thead>
                                                <tr class="text-gray-400">
                                                    <th class="text-left py-1">{{ __('Priority') }}</th>
                                                    <th class="text-right py-1">{{ __('Response') }}</th>
                                                    <th class="text-right py-1">{{ __('Resolution') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($clientSlaPolicies->sortBy('priority') as $policy)
                                                    <tr class="text-gray-600 border-t border-gray-200">
                                                        <td class="py-1 font-medium">{{ ucfirst($policy->priority ?? 'Any') }}</td>
                                                        <td class="py-1 text-right">{{ $policy->response_time_hours }}h</td>
                                                        <td class="py-1 text-right">{{ $policy->resolution_time_hours }}h</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </dd>
                                @endif
                            </div>
                            @if(app(\App\Services\PlanService::class)->currentTenantHasFeature(\App\Enums\PlanFeature::SlaManagement))
                            <div>
                                <dt class="text-sm font-medium text-gray-500">{{ __('Portal Access') }}</dt>
                                <dd class="mt-1">
                                    <x-badge :type="$client->hasPortalAccess() ? 'active' : 'inactive'">{{ $client->hasPortalAccess() ? __('Enabled') : __('No access') }}</x-badge>
                                </dd>
                            </div>
                            @endif
                        </dl>
                    </div>

                    @if($autofillData)
                    <!-- Autofill submit-ticket link -->
                    <div class="mt-6 rounded-xl bg-white p-6 shadow-sm" x-data="autofillBuilder(@js($autofillData))">
                        <h3 class="text-lg font-semibold text-gray-900">{{ __('Quick-submit link') }}</h3>
                        <p class="mt-1 text-sm text-gray-500">{{ __('Share this link or QR code with the client. It opens the public ticket form with their name and email filled in and locked. Optionally pre-select a department, category, and products below.') }}</p>

                        <div class="mt-4 space-y-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">{{ __('Department') }}</label>
                                <select x-model="departmentId" @change="onDepartmentChange()" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">{{ __('None') }}</option>
                                    <template x-for="d in departments" :key="d.id">
                                        <option :value="d.id" x-text="d.name"></option>
                                    </template>
                                </select>
                            </div>

                            <div x-show="departmentId">
                                <label class="block text-sm font-medium text-gray-700">{{ __('Category') }}</label>
                                <select x-model="categoryId" @change="onCategoryChange()" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">{{ __('None') }}</option>
                                    <template x-for="c in categories" :key="c.id">
                                        <option :value="c.id" x-text="c.name"></option>
                                    </template>
                                </select>
                            </div>

                            <div x-show="products.length > 0" x-cloak>
                                <label class="block text-sm font-medium text-gray-700">{{ __('Products / Services') }}</label>
                                <div class="mt-1 max-h-36 overflow-y-auto rounded-md border border-gray-300 p-2">
                                    <template x-for="p in products" :key="p.id">
                                        <label class="flex items-center gap-2 px-1 py-1 text-sm text-gray-700">
                                            <input type="checkbox" :value="p.id" :checked="selectedProductIds.includes(p.id)" @change="toggleProduct(p.id)" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                            <span x-text="p.name"></span>
                                        </label>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 flex items-stretch gap-2">
                            <input type="text" readonly :value="link()" @focus="$event.target.select()" class="block w-full rounded-md border-gray-300 bg-gray-50 text-sm text-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <button type="button" @click="copy()" class="shrink-0 inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                                <span x-show="!copied">{{ __('Copy') }}</span>
                                <span x-show="copied" x-cloak>{{ __('Copied!') }}</span>
                            </button>
                        </div>

                        <div class="mt-4 flex flex-col items-center gap-2">
                            <img :src="qrSrc()" alt="{{ __('Autofill link QR code') }}" width="200" height="200" class="rounded-lg ring-1 ring-gray-200">
                            <a :href="downloadHref()" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">{{ __('Download QR') }}</a>
                        </div>
                    </div>

                    <script>
                        function autofillBuilder(config) {
                            return {
                                departments: config.departments || [],
                                allCategories: config.categories || [],
                                allProducts: config.products || [],
                                base: config.base,
                                qrBase: config.qrBase,
                                name: config.name || '',
                                email: config.email || '',
                                departmentId: '',
                                categoryId: '',
                                selectedProductIds: [],
                                copied: false,
                                get categories() {
                                    if (!this.departmentId) return [];
                                    return this.allCategories.filter(c => String(c.department_id) === String(this.departmentId));
                                },
                                get products() {
                                    if (this.categoryId) {
                                        return this.allProducts.filter(p => String(p.category_id) === String(this.categoryId));
                                    }
                                    if (this.departmentId) {
                                        const catIds = this.categories.map(c => String(c.id));
                                        return this.allProducts.filter(p => catIds.includes(String(p.category_id)));
                                    }
                                    return [];
                                },
                                nameOf(list, id) {
                                    const item = list.find(x => String(x.id) === String(id));
                                    return item ? item.name : '';
                                },
                                selectedParams(includeIdentity) {
                                    const p = new URLSearchParams();
                                    if (includeIdentity) {
                                        p.set('name', this.name);
                                        p.set('email', this.email);
                                    }
                                    if (this.departmentId) p.set('department', this.nameOf(this.departments, this.departmentId));
                                    if (this.categoryId) p.set('category', this.nameOf(this.allCategories, this.categoryId));
                                    const names = this.selectedProductIds.map(id => this.nameOf(this.allProducts, id)).filter(Boolean);
                                    if (names.length) p.set('products', names.join(','));
                                    return p;
                                },
                                link() { return this.base + '?' + this.selectedParams(true).toString(); },
                                qrSrc() {
                                    const q = this.selectedParams(false).toString();
                                    return this.qrBase + (q ? '?' + q : '');
                                },
                                downloadHref() {
                                    const p = this.selectedParams(false);
                                    p.set('download', '1');
                                    return this.qrBase + '?' + p.toString();
                                },
                                onDepartmentChange() { this.categoryId = ''; this.selectedProductIds = []; },
                                onCategoryChange() { this.selectedProductIds = []; },
                                toggleProduct(id) {
                                    const i = this.selectedProductIds.indexOf(id);
                                    if (i === -1) this.selectedProductIds.push(id);
                                    else this.selectedProductIds.splice(i, 1);
                                },
                                copy() {
                                    navigator.clipboard.writeText(this.link()).then(() => {
                                        this.copied = true;
                                        setTimeout(() => this.copied = false, 1500);
                                    });
                                },
                            };
                        }
                    </script>
                    @endif
                </div>

                <!-- Client Tickets -->
                <div class="lg:col-span-2">
                    <div class="rounded-xl bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-gray-900">{{ __('Recent Tickets') }}</h3>
                            <a href="{{ route('tickets.create', ['client_id' => $client->id]) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">{{ __('Create Ticket') }}</a>
                        </div>
                        @php
                            $clientTickets = $client->tickets()->latest()->take(10)->get();
                        @endphp
                        @if($clientTickets->count() > 0)
                            <div class="mt-4 divide-y divide-gray-200">
                                @foreach($clientTickets as $ticket)
                                    <div class="py-3 flex items-center justify-between">
                                        <div>
                                            <a href="{{ route('tickets.show', $ticket) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-900">{{ $ticket->ticket_number }}</a>
                                            <p class="text-sm text-gray-900">{{ Str::limit($ticket->subject, 60) }}</p>
                                        </div>
                                        <x-badge :type="$ticket->status">{{ ucfirst(str_replace('_', ' ', $ticket->status)) }}</x-badge>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="mt-4 text-sm text-gray-500">{{ __('No tickets yet for this client.') }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
