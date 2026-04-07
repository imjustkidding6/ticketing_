<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Create Ticket') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-xl bg-white p-6 shadow-sm">
                <form method="POST" action="{{ route('tickets.store') }}" enctype="multipart/form-data">
                    @csrf

                    <div class="space-y-6">
                        {{-- Client --}}
                        <div>
                            <label for="client_id" class="block text-sm font-medium text-gray-700">{{ __('Client') }} <span class="text-red-500">*</span></label>
                            <select name="client_id" id="client_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">{{ __('Select client') }}</option>
                                @foreach($clients as $client)
                                    <option value="{{ $client->id }}" data-tier="{{ $client->tier }}" {{ old('client_id') == $client->id ? 'selected' : '' }}>{{ $client->name }}</option>
                                @endforeach
                            </select>
                            @error('client_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Department + Category + Products (Cascading) --}}
                        <div x-data="cascadingSelects()" x-init="init()">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="department_id" class="block text-sm font-medium text-gray-700">{{ __('Department') }}</label>
                                    <select name="department_id" id="department_id" x-model="departmentId" @change="onDepartmentChange()" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <option value="">{{ __('Select department') }}</option>
                                        @foreach($departments as $department)
                                            <option value="{{ $department->id }}">{{ $department->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('department_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="category_id" class="block text-sm font-medium text-gray-700">{{ __('Category') }}</label>
                                    <select name="category_id" id="category_id" x-model="categoryId" @change="onCategoryChange()" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <option value="">{{ __('Select category') }}</option>
                                        <template x-for="cat in categories" :key="cat.id">
                                            <option :value="cat.id" x-text="cat.name"></option>
                                        </template>
                                    </select>
                                    @error('category_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            {{-- Products / Services (Multi-select with checkboxes) --}}
                            <div class="mt-6 relative">
                                <label class="block text-sm font-medium text-gray-700">{{ __('Products / Services') }}</label>
                                <button type="button" @click="productOpen = !productOpen" class="mt-1 relative w-full cursor-pointer rounded-md border border-gray-300 bg-white py-2 pl-3 pr-10 text-left shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm">
                                    <span x-text="selectedProductIds.length > 0 ? selectedProductIds.length + ' selected' : '{{ __('Select products / services') }}'" class="block truncate" :class="selectedProductIds.length > 0 ? 'text-gray-900' : 'text-gray-500'"></span>
                                    <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
                                        <svg class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                        </svg>
                                    </span>
                                </button>
                                <div x-show="productOpen" x-cloak @click.outside="productOpen = false" class="absolute z-10 mt-1 w-full rounded-md bg-white shadow-lg ring-1 ring-black/5 max-h-60 overflow-y-auto">
                                    <template x-if="products.length === 0">
                                        <div class="px-3 py-2 text-sm text-gray-500">{{ __('No products available.') }}</div>
                                    </template>
                                    <template x-for="product in products" :key="product.id">
                                        <label class="flex items-center px-3 py-2 hover:bg-gray-50 cursor-pointer">
                                            <input type="checkbox" name="product_ids[]" :value="product.id" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" :checked="selectedProductIds.includes(product.id)" @change="toggleProduct(product.id)">
                                            <span class="ml-2 text-sm text-gray-700" x-text="product.name"></span>
                                        </label>
                                    </template>
                                </div>
                                @error('product_ids') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        {{-- Subject --}}
                        <div x-data="kbSuggestions()">
                            <label for="subject" class="block text-sm font-medium text-gray-700">{{ __('Subject') }} <span class="text-red-500">*</span></label>
                            <input type="text" name="subject" id="subject" value="{{ old('subject') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" x-on:input.debounce.400ms="search($event.target.value)">
                            @error('subject') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror

                            <div x-show="articles.length > 0" x-cloak class="mt-2 rounded-lg border border-indigo-200 bg-indigo-50 p-3">
                                <p class="text-xs font-medium text-indigo-700 mb-2">{{ __('Related KB articles that may help:') }}</p>
                                <template x-for="article in articles" :key="article.id">
                                    <a :href="article.url" target="_blank" class="block text-sm text-indigo-600 hover:text-indigo-800 py-1">
                                        <span x-text="article.title"></span>
                                    </a>
                                </template>
                            </div>
                        </div>

                        {{-- Description --}}
                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700">{{ __('Description') }} <span class="text-red-500">*</span></label>
                            <textarea name="description" id="description" rows="6" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">{{ old('description') }}</textarea>
                            @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Priority + Assign To --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4" x-data="prioritySla()">
                            <div>
                                <label for="priority" class="block text-sm font-medium text-gray-700">{{ __('Priority') }}</label>
                                <select name="priority" id="priority" required x-model="selectedPriority" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    @foreach(['low', 'medium', 'high', 'critical'] as $p)
                                        <option value="{{ $p }}" {{ old('priority', 'medium') === $p ? 'selected' : '' }}>{{ ucfirst($p) }}</option>
                                    @endforeach
                                </select>
                                @error('priority') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                <template x-if="slaInfo">
                                    <p class="mt-1 text-xs text-indigo-600">
                                        {{ __('Response:') }} <span x-text="slaInfo.response + 'h'"></span>
                                        &middot; {{ __('Resolution:') }} <span x-text="slaInfo.resolution + 'h'"></span>
                                    </p>
                                </template>
                            </div>
                            <div>
                                <label for="assigned_to" class="block text-sm font-medium text-gray-700">{{ __('Assign To') }}</label>
                                <select name="assigned_to" id="assigned_to" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">{{ __('Unassigned') }}</option>
                                    @foreach($agents as $agent)
                                        <option value="{{ $agent->id }}" {{ old('assigned_to') == $agent->id ? 'selected' : '' }}>{{ $agent->name }}</option>
                                    @endforeach
                                </select>
                                @error('assigned_to') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        {{-- Incident Date/Time --}}
                        <div>
                            <label for="incident_date" class="block text-sm font-medium text-gray-700">{{ __('Incident Date/Time') }} <span class="text-gray-400 font-normal">({{ __('optional') }})</span></label>
                            <input type="datetime-local" name="incident_date" id="incident_date" value="{{ old('incident_date') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            @error('incident_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Task Checklist --}}
                        <div x-data="taskChecklist()">
                            <label class="block text-sm font-medium text-gray-700">{{ __('Task Checklist') }} <span class="text-gray-400 font-normal">({{ __('optional') }})</span></label>
                            <template x-for="(task, index) in tasks" :key="index">
                                <div class="mt-2 flex items-center gap-2">
                                    <input type="text" :name="'tasks[' + index + ']'" x-model="tasks[index]" :placeholder="'{{ __('Task') }} ' + (index + 1)" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <button type="button" @click="tasks.splice(index, 1)" x-show="tasks.length > 1" class="flex-shrink-0 text-gray-400 hover:text-red-500">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </template>
                            <button type="button" @click="tasks.push('')" class="mt-2 inline-flex items-center text-sm text-indigo-600 hover:text-indigo-800">
                                <svg class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                                {{ __('Add Task') }}
                            </button>
                            @error('tasks') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        @if(app(\App\Services\PlanService::class)->currentTenantHasFeature(\App\Enums\PlanFeature::Attachments))
                        <div>
                            <label for="attachments" class="block text-sm font-medium text-gray-700">{{ __('Attachments') }}</label>
                            <input type="file" name="attachments[]" id="attachments" multiple class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:rounded-md file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">
                            <p class="mt-1 text-xs text-gray-500">{{ __('Max 5 files, 10MB each. PDF, images, docs, spreadsheets, text, zip.') }}</p>
                            @error('attachments.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        @endif
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-3">
                        <a href="{{ route('tickets.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">{{ __('Cancel') }}</a>
                        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">{{ __('Create Ticket') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function cascadingSelects() {
            return {
                departmentId: '{{ old('department_id', '') }}',
                categoryId: '{{ old('category_id', '') }}',
                categories: [],
                products: [],
                selectedProductIds: {!! json_encode(array_map('intval', old('product_ids', []))) !!},
                productOpen: false,
                categoriesUrl: '{{ route('api.categories') }}',
                productsUrl: '{{ route('api.products') }}',
                async init() {
                    if (this.departmentId) {
                        await this.fetchCategories();
                        if (this.categoryId) {
                            await this.fetchProducts();
                        }
                    }
                },
                async onDepartmentChange() {
                    this.categoryId = '';
                    this.categories = [];
                    this.products = [];
                    this.selectedProductIds = [];
                    if (this.departmentId) {
                        await this.fetchCategories();
                        await this.fetchProducts();
                    }
                },
                async onCategoryChange() {
                    this.products = [];
                    this.selectedProductIds = [];
                    await this.fetchProducts();
                },
                async fetchCategories() {
                    var params = this.departmentId ? '?department_id=' + this.departmentId : '';
                    var res = await fetch(this.categoriesUrl + params);
                    this.categories = await res.json();
                },
                async fetchProducts() {
                    var params = [];
                    if (this.categoryId) params.push('category_id=' + this.categoryId);
                    else if (this.departmentId) params.push('department_id=' + this.departmentId);
                    var res = await fetch(this.productsUrl + (params.length ? '?' + params.join('&') : ''));
                    this.products = await res.json();
                },
                toggleProduct(id) {
                    var idx = this.selectedProductIds.indexOf(id);
                    if (idx === -1) this.selectedProductIds.push(id);
                    else this.selectedProductIds.splice(idx, 1);
                }
            };
        }

        function taskChecklist() {
            return {
                tasks: {!! json_encode(old('tasks', [''])) !!}
            };
        }

        function prioritySla() {
            var slaLookup = @json($slaLookup);
            var clientTiers = @json($clients->pluck('tier', 'id'));
            return {
                selectedPriority: '{{ old('priority', 'medium') }}',
                clientId: '{{ old('client_id', '') }}',
                get slaInfo() {
                    var tier = clientTiers[this.clientId] || '';
                    if (!tier || !slaLookup[tier] || !slaLookup[tier][this.selectedPriority]) return null;
                    return slaLookup[tier][this.selectedPriority];
                },
                init() {
                    var self = this;
                    document.getElementById('client_id').addEventListener('change', function() {
                        self.clientId = this.value;
                    });
                }
            };
        }
    </script>

    @if(app(\App\Services\PlanService::class)->currentTenantHasFeature(\App\Enums\PlanFeature::KnowledgeBase))
    <script>
        function kbSuggestions() {
            return {
                articles: [],
                async search(query) {
                    if (query.length < 3) {
                        this.articles = [];
                        return;
                    }
                    try {
                        const response = await fetch(`{{ route('knowledge-base.articles.search') }}?q=${encodeURIComponent(query)}`);
                        this.articles = await response.json();
                    } catch (e) {
                        this.articles = [];
                    }
                }
            };
        }
    </script>
    @else
    <script>
        function kbSuggestions() {
            return { articles: [], search() {} };
        }
    </script>
    @endif
</x-app-layout>
