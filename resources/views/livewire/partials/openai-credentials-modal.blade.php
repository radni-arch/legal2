{{-- OpenAI Session Credentials Modal --}}
<div
    x-data="{ open: @entangle('showCredentialsModal') }"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50 overflow-y-auto"
    aria-labelledby="modal-title"
    role="dialog"
    aria-modal="true"
>
    {{-- Background overlay --}}
    <div
        x-show="open"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
        @click="$wire.cancelCredentials()"
    ></div>

    {{-- Modal panel --}}
    <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <div
            x-show="open"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6"
        >
            {{-- Header --}}
            <div class="mb-4">
                <h3 class="text-lg font-semibold leading-6 text-gray-900" id="modal-title">
                    OpenAI Session Authentication
                </h3>
                <p class="mt-2 text-sm text-gray-500">
                    The OpenAI Responses API requires session-based authentication.
                    Enter your credentials from the OpenAI platform.
                </p>
            </div>

            {{-- Form --}}
            <div class="space-y-4">
                {{-- Session Token --}}
                <div>
                    <label for="sessionToken" class="block text-sm font-medium text-gray-700">
                        Session Token <span class="text-red-500">*</span>
                    </label>
                    <div class="mt-1">
                        <input
                            type="password"
                            id="sessionToken"
                            wire:model="sessionToken"
                            placeholder="sess-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm font-mono"
                        >
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        Found in browser DevTools → Network → Request Headers → Authorization: Bearer sess-...
                    </p>
                </div>

                {{-- Organization ID --}}
                <div>
                    <label for="organizationId" class="block text-sm font-medium text-gray-700">
                        Organization ID
                    </label>
                    <div class="mt-1">
                        <input
                            type="text"
                            id="organizationId"
                            wire:model="organizationId"
                            placeholder="org-xxxxxxxxxxxxxxxxxxxxxxxx"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm font-mono"
                        >
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        Header: OpenAI-Organization
                    </p>
                </div>

                {{-- Project ID --}}
                <div>
                    <label for="projectId" class="block text-sm font-medium text-gray-700">
                        Project ID
                    </label>
                    <div class="mt-1">
                        <input
                            type="text"
                            id="projectId"
                            wire:model="projectId"
                            placeholder="proj_xxxxxxxxxxxxxxxxxxxxxxxx"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm font-mono"
                        >
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        Header: OpenAI-Project
                    </p>
                </div>
            </div>

            {{-- Footer buttons --}}
            <div class="mt-6 flex justify-end space-x-3">
                <button
                    type="button"
                    wire:click="cancelCredentials"
                    class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    wire:click="saveCredentials"
                    class="inline-flex justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    Save & Retry
                </button>
            </div>
        </div>
    </div>
</div>
