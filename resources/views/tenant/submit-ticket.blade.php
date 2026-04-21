<x-client-portal-layout :tenant="$tenant" :hide-nav="true">
    {{-- Hero Section --}}
    <div class="-mt-8 pb-12 pt-16" style="background-color: var(--portal-primary);">
        <div class="mx-auto max-w-full px-4 sm:px-4 lg:px-6 text-center">
            <h1 class="text-3xl font-bold text-white sm:text-4xl">{{ $tenant->displayName() }} {{ __('Support') }}</h1>
            <p class="mt-3 text-lg text-white/70">{{ __('Submit a ticket and we\'ll get back to you as soon as possible.') }}</p>
        </div>
    </div>

    {{-- Main Content --}}
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 -mt-6">
        <div class="rounded-xl bg-white p-8 shadow-sm">
            <div class="flex items-center gap-3 mb-6">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg" style="background-color: color-mix(in srgb, var(--portal-primary) 15%, white);">
                    <svg class="h-6 w-6" style="color: var(--portal-primary);" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">{{ __('Submit a Ticket') }}</h2>
                    <p class="text-sm text-gray-500">{{ __('Describe your issue and we\'ll get back to you.') }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('tenant.submit-ticket.store', ['slug' => $tenant->slug]) }}" class="space-y-5" enctype="multipart/form-data">
                @csrf

                {{-- Name & Email --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">{{ __('Your Name') }} <span class="text-red-500">*</span></label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">{{ __('Email Address') }} <span class="text-red-500">*</span></label>
                        <input type="email" name="email" id="email" value="{{ old('email') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Subject with KB Suggestions --}}
                <div x-data="kbSuggestions()">
                    <label for="subject" class="block text-sm font-medium text-gray-700">{{ __('Subject') }} <span class="text-red-500">*</span></label>
                    <input type="text" name="subject" id="subject" value="{{ old('subject') }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="{{ __('Brief summary of your issue') }}" x-on:input.debounce.400ms="search($event.target.value)">
                    @error('subject') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror

                    <div x-show="articles.length > 0" x-cloak class="mt-2 rounded-lg border border-indigo-200 bg-indigo-50 p-3">
                        <p class="text-xs font-medium text-indigo-700 mb-2">{{ __('These articles may answer your question:') }}</p>
                        <template x-for="article in articles" :key="article.id">
                            <a :href="article.url" target="_blank" class="block text-sm text-indigo-600 hover:text-indigo-800 py-1">
                                <span x-text="article.title"></span>
                            </a>
                        </template>
                    </div>
                </div>

                {{-- Department + Category (Cascading) --}}
                <div x-data="cascadingSelects()" x-init="init()">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="department_id" class="block text-sm font-medium text-gray-700">{{ __('Department') }} <span class="text-red-500">*</span></label>
                            <select name="department_id" id="department_id" required x-model="departmentId" @change="onDepartmentChange()" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
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

                    {{-- Products / Services (Multi-select) --}}
                    <div class="mt-4 relative" x-show="products.length > 0" x-cloak>
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

                {{-- Description --}}
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">{{ __('Description') }} <span class="text-red-500">*</span></label>
                    <textarea name="description" id="description" rows="6" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="{{ __('Please provide as much detail as possible...') }}">{{ old('description') }}</textarea>
                    @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Incident Date --}}
                <div>
                    <label for="incident_date" class="block text-sm font-medium text-gray-700">{{ __('Incident Date/Time') }} <span class="text-gray-400 font-normal">({{ __('optional') }})</span></label>
                    <input type="datetime-local" name="incident_date" id="incident_date" value="{{ old('incident_date') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('incident_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                @if ($allowAttachments)
                    {{-- Attachments (Business+) --}}
                    <div x-data="attachmentValidator()">
                        <label for="attachments" class="block text-sm font-medium text-gray-700">{{ __('Attachments') }} <span class="text-gray-400 font-normal">({{ __('optional') }})</span></label>
                        <input type="file" name="attachments[]" id="attachments" multiple accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.txt" @change="validate($event)" class="mt-1 block w-full text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                        <p class="mt-1 text-xs text-gray-500">{{ __('Up to 3 files, 10MB each. Allowed: jpg, png, gif, pdf, doc, docx, txt.') }}</p>
                        <template x-if="error">
                            <p class="mt-1 text-sm text-red-600" x-text="error"></p>
                        </template>
                        @error('attachments') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        @error('attachments.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <script>
                        function attachmentValidator() {
                            const MAX_SIZE = 10 * 1024 * 1024;
                            const MAX_COUNT = 3;
                            const ALLOWED = ['jpg','jpeg','png','gif','pdf','doc','docx','txt'];
                            return {
                                error: '',
                                validate(e) {
                                    this.error = '';
                                    const files = Array.from(e.target.files || []);
                                    if (files.length > MAX_COUNT) {
                                        this.error = 'You can upload at most ' + MAX_COUNT + ' files.';
                                        e.target.value = '';
                                        return;
                                    }
                                    for (const f of files) {
                                        const ext = (f.name.split('.').pop() || '').toLowerCase();
                                        if (!ALLOWED.includes(ext)) {
                                            this.error = f.name + ': file type not allowed.';
                                            e.target.value = '';
                                            return;
                                        }
                                        if (f.size > MAX_SIZE) {
                                            const mb = (f.size / 1024 / 1024).toFixed(1);
                                            this.error = f.name + ' is ' + mb + ' MB — max is 10 MB.';
                                            e.target.value = '';
                                            return;
                                        }
                                    }
                                },
                            };
                        }
                    </script>
                @endif

                {{-- Info Box --}}
                <div class="rounded-lg border border-indigo-200 bg-indigo-50 p-4">
                    <div class="flex items-start gap-3">
                        <svg class="h-5 w-5 text-indigo-600 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                        </svg>
                        <div class="text-sm text-indigo-800">
                            <p class="font-medium mb-1">{{ __('What happens next?') }}</p>
                            <ul class="space-y-1 text-indigo-700">
                                <li>{{ __('You\'ll receive a ticket number and tracking link') }}</li>
                                <li>{{ __('Our support team will review your request and respond accordingly') }}</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <a href="{{ route('tenant.landing', ['slug' => $tenant->slug]) }}" class="text-sm" style="color: var(--portal-primary);">
                        {{ __('Back to portal') }}
                    </a>
                    <button type="submit" class="rounded-md px-6 py-2.5 text-sm font-semibold text-white shadow-sm" style="background-color: var(--portal-primary);">
                        {{ __('Submit Ticket') }}
                    </button>
                </div>
            </form>
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
                async init() {
                    if (this.departmentId) {
                        await this.fetchCategories();
                        await this.fetchProducts();
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
                    var res = await fetch('/{{ $tenant->slug }}/api/public/categories' + params);
                    this.categories = await res.json();
                },
                async fetchProducts() {
                    var params = [];
                    if (this.categoryId) params.push('category_id=' + this.categoryId);
                    else if (this.departmentId) params.push('department_id=' + this.departmentId);
                    var res = await fetch('/{{ $tenant->slug }}/api/public/products' + (params.length ? '?' + params.join('&') : ''));
                    this.products = await res.json();
                },
                toggleProduct(id) {
                    var idx = this.selectedProductIds.indexOf(id);
                    if (idx === -1) this.selectedProductIds.push(id);
                    else this.selectedProductIds.splice(idx, 1);
                }
            };
        }
    </script>

    @if($kbSearchUrl)
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
                        const response = await fetch(`{{ $kbSearchUrl }}?q=${encodeURIComponent(query)}`);
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
</x-client-portal-layout>
