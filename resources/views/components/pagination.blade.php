@props([
    'current' => 1,
    'total' => 1
])

<nav class="flex items-center justify-between border-t border-gray-200 px-4 sm:px-0">
    <div class="-mt-px flex w-0 flex-1">
        @if($current > 1)
            <a href="#" class="inline-flex items-center border-t-2 border-transparent pr-1 pt-4 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700">
                Previous
            </a>
        @else
            <span class="inline-flex items-center border-t-2 border-transparent pr-1 pt-4 text-sm font-medium text-gray-300 cursor-not-allowed disabled">
                Previous
            </span>
        @endif
    </div>
    <div class="hidden md:-mt-px md:flex">
        <span class="inline-flex items-center border-t-2 border-transparent px-4 pt-4 text-sm font-medium text-gray-500">
            Page {{ $current }} of {{ $total }}
        </span>
    </div>
    <div class="-mt-px flex w-0 flex-1 justify-end">
        @if($current < $total)
            <a href="#" class="inline-flex items-center border-t-2 border-transparent pl-1 pt-4 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700">
                Next
            </a>
        @else
            <span class="inline-flex items-center border-t-2 border-transparent pl-1 pt-4 text-sm font-medium text-gray-300 cursor-not-allowed disabled">
                Next
            </span>
        @endif
    </div>
</nav>
