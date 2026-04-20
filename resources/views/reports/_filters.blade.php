{{-- Reusable report filters partial --}}
{{-- Required variables: $filters, $departments, $categories, $agents, $clients, $products --}}
{{-- Optional: $action (route name), $exclude (array of filter names to hide) --}}
@php $exclude = $exclude ?? []; @endphp

<div class="mb-6 rounded-xl bg-white p-4 shadow-sm">
    <form method="GET" action="{{ $action ?? '' }}">
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <div>
                <label for="from" class="block text-xs font-medium text-gray-500">{{ __('From') }}</label>
                <input type="date" name="from" id="from" value="{{ $filters['from'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label for="to" class="block text-xs font-medium text-gray-500">{{ __('To') }}</label>
                <input type="date" name="to" id="to" value="{{ $filters['to'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            @unless(in_array('status', $exclude))
            <div>
                <label for="status" class="block text-xs font-medium text-gray-500">{{ __('Status') }}</label>
                <select name="status" id="status" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">{{ __('All Statuses') }}</option>
                    @foreach(['open', 'assigned', 'in_progress', 'on_hold', 'closed', 'cancelled'] as $s)
                        <option value="{{ $s }}" {{ ($filters['status'] ?? '') === $s ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                    @endforeach
                </select>
            </div>
            @endunless
            @unless(in_array('priority', $exclude))
            <div>
                <label for="priority" class="block text-xs font-medium text-gray-500">{{ __('Priority') }}</label>
                <select name="priority" id="priority" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">{{ __('All Priorities') }}</option>
                    @foreach(['low', 'medium', 'high', 'critical'] as $p)
                        <option value="{{ $p }}" {{ ($filters['priority'] ?? '') === $p ? 'selected' : '' }}>{{ ucfirst($p) }}</option>
                    @endforeach
                </select>
            </div>
            @endunless
            @unless(in_array('department_id', $exclude))
            <div>
                <label for="department_id" class="block text-xs font-medium text-gray-500">{{ __('Department') }}</label>
                <select name="department_id" id="department_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">{{ __('All Departments') }}</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}" {{ ($filters['department_id'] ?? '') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                    @endforeach
                </select>
            </div>
            @endunless
            @unless(in_array('category_id', $exclude))
            <div>
                <label for="category_id" class="block text-xs font-medium text-gray-500">{{ __('Category') }}</label>
                <select name="category_id" id="category_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">{{ __('All Categories') }}</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ ($filters['category_id'] ?? '') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            @endunless
            @unless(in_array('client_id', $exclude))
            <div>
                <label for="client_id" class="block text-xs font-medium text-gray-500">{{ __('Client') }}</label>
                <select name="client_id" id="client_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">{{ __('All Clients') }}</option>
                    @foreach($clients as $client)
                        <option value="{{ $client->id }}" {{ ($filters['client_id'] ?? '') == $client->id ? 'selected' : '' }}>{{ $client->name }}</option>
                    @endforeach
                </select>
            </div>
            @endunless
            @unless(in_array('assigned_to', $exclude))
            <div>
                <label for="assigned_to" class="block text-xs font-medium text-gray-500">{{ __('Agent') }}</label>
                <select name="assigned_to" id="assigned_to" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">{{ __('All Agents') }}</option>
                    @foreach($agents as $agent)
                        <option value="{{ $agent->id }}" {{ ($filters['assigned_to'] ?? '') == $agent->id ? 'selected' : '' }}>{{ $agent->name }}</option>
                    @endforeach
                </select>
            </div>
            @endunless
            @unless(in_array('product_id', $exclude))
            <div>
                <label for="product_id" class="block text-xs font-medium text-gray-500">{{ __('Product') }}</label>
                <select name="product_id" id="product_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">{{ __('All Products') }}</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}" {{ ($filters['product_id'] ?? '') == $product->id ? 'selected' : '' }}>{{ $product->name }}</option>
                    @endforeach
                </select>
            </div>
            @endunless
        </div>
        <div class="mt-3 flex items-center gap-2">
            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">{{ __('Apply Filters') }}</button>
            <a href="{{ $action ?? '' }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">{{ __('Reset') }}</a>
            <span class="ml-auto text-xs text-gray-400">{{ __('Trend:') }}</span>
            @foreach(['daily', 'weekly', 'monthly', 'yearly'] as $tg)
                <button type="submit" name="trend_group" value="{{ $tg }}" class="rounded-md px-3 py-1.5 text-xs font-medium {{ ($filters['trend_group'] ?? 'daily') === $tg ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">{{ ucfirst($tg) }}</button>
            @endforeach
        </div>
    </form>
</div>
