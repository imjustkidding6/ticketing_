<div class="bg-white rounded-lg shadow-md p-6 max-w-sm mx-auto">
    <h2 class="text-xl font-semibold text-gray-800 mb-4 text-center">
        Livewire Counter
    </h2>

    <div class="flex items-center justify-center gap-4">
        <button
            wire:click="decrement"
            class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-md transition-colors"
        >
            -
        </button>

        <span class="text-3xl font-bold text-gray-700 w-16 text-center">
            {{ $count }}
        </span>

        <button
            wire:click="increment"
            class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-md transition-colors"
        >
            +
        </button>
    </div>

    <p class="text-sm text-gray-500 mt-4 text-center">
        Click the buttons to change the count
    </p>
</div>
