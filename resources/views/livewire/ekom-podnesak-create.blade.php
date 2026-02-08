<div class="min-h-screen" style="background: var(--bg, #0b1220); color: var(--fg, #e5e7eb);" dusk="ekom-podnesak-create">
    {{-- Header Section --}}
    <header class="relative" style="background: linear-gradient(180deg, var(--surface, #0f172a), var(--bg, #0b1220)); border-bottom: 1px solid var(--border, #1f2937);">
        <div class="max-w-7xl mx-auto px-4 py-6">
            <div class="rounded-2xl p-4" style="background: rgba(17,24,39,0.65); border: 1px solid var(--border, #1f2937);">
                <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                    <div>
                        {{-- Breadcrumb --}}
                        <x-breadcrumbs :items="breadcrumbs('ekom.podnesci.create')" />
                        <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight" style="color: var(--fg)">
                            Create Submission
                        </h1>
                        <p class="mt-2" style="color: var(--muted, #94a3b8);">
                            Submit a new court document to E-Komunikacije
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <a href="{{ route('ekom.podnesci') }}" class="btn-secondary" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.9rem; background: var(--bg, #0b1220); color: var(--fg, #e5e7eb); font-weight: 500; font-size: 0.875rem; border: 1px solid var(--border, #1f2937); border-radius: 0.5rem;" dusk="cancel-link">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 py-8">
        {{-- Success Message --}}
        @if($successMessage)
            <div class="mb-6 p-4 rounded-lg" style="background: rgba(16,185,129,0.15); border: 1px solid rgba(16,185,129,0.3); color: #10b981;" dusk="success-message">
                <div class="flex items-center gap-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ $successMessage }}</span>
                </div>
            </div>
        @endif

        {{-- Error Message --}}
        @if($errorMessage)
            <div class="mb-6 p-4 rounded-lg" style="background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.3); color: #ef4444;" dusk="error-message">
                <div class="flex items-center gap-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ $errorMessage }}</span>
                </div>
            </div>
        @endif

        {{-- Form Card --}}
        <form wire:submit="submit" class="card" style="background: var(--card, #111827); border: 1px solid var(--border, #1f2937); border-radius: 1rem;">
            <div class="p-6 space-y-6">
                {{-- Case ID (Predmet ID) --}}
                <div>
                    <label for="predmetId" class="block text-sm font-medium mb-2" style="color: var(--fg);">
                        Case ID (Predmet ID) <span class="text-red-500">*</span>
                    </label>
                    <input
                        wire:model="predmetId"
                        type="text"
                        id="predmetId"
                        placeholder="Enter the case ID"
                        class="w-full px-4 py-3 rounded-lg text-sm"
                        style="background: var(--bg, #0b1220); border: 1px solid var(--border, #1f2937); color: var(--fg); outline: none;"
                        dusk="predmet-id-input"
                    />
                    @error('predmetId')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Submission Type (Vrsta Podneska) --}}
                <div>
                    <label for="vrstaPodneskaId" class="block text-sm font-medium mb-2" style="color: var(--fg);">
                        Submission Type <span class="text-red-500">*</span>
                    </label>
                    <input
                        wire:model="vrstaPodneskaId"
                        type="text"
                        id="vrstaPodneskaId"
                        placeholder="Enter the submission type ID"
                        class="w-full px-4 py-3 rounded-lg text-sm"
                        style="background: var(--bg, #0b1220); border: 1px solid var(--border, #1f2937); color: var(--fg); outline: none;"
                        dusk="vrsta-podneska-input"
                    />
                    @error('vrstaPodneskaId')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Title (Naziv) --}}
                <div>
                    <label for="naziv" class="block text-sm font-medium mb-2" style="color: var(--fg);">
                        Title <span class="text-red-500">*</span>
                    </label>
                    <input
                        wire:model="naziv"
                        type="text"
                        id="naziv"
                        placeholder="Enter the submission title"
                        class="w-full px-4 py-3 rounded-lg text-sm"
                        style="background: var(--bg, #0b1220); border: 1px solid var(--border, #1f2937); color: var(--fg); outline: none;"
                        dusk="naziv-input"
                    />
                    @error('naziv')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Description (Opis) --}}
                <div>
                    <label for="opis" class="block text-sm font-medium mb-2" style="color: var(--fg);">
                        Description <span style="color: var(--muted);">(optional)</span>
                    </label>
                    <textarea
                        wire:model="opis"
                        id="opis"
                        rows="4"
                        placeholder="Enter a description of the submission"
                        class="w-full px-4 py-3 rounded-lg text-sm resize-y"
                        style="background: var(--bg, #0b1220); border: 1px solid var(--border, #1f2937); color: var(--fg); outline: none;"
                        dusk="opis-input"
                    ></textarea>
                    @error('opis')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- File Attachments --}}
                <div>
                    <label class="block text-sm font-medium mb-2" style="color: var(--fg);">
                        Attachments <span style="color: var(--muted);">(optional)</span>
                    </label>

                    {{-- Dropzone --}}
                    <div
                        x-data="{ isDragging: false }"
                        x-on:dragover.prevent="isDragging = true"
                        x-on:dragleave.prevent="isDragging = false"
                        x-on:drop.prevent="isDragging = false"
                        class="relative rounded-lg p-6 text-center transition-colors cursor-pointer"
                        :style="isDragging ? 'background: rgba(59,130,246,0.15); border: 2px dashed #3b82f6;' : 'background: var(--bg, #0b1220); border: 2px dashed var(--border, #1f2937);'"
                        dusk="file-dropzone"
                    >
                        <input
                            wire:model="attachments"
                            type="file"
                            multiple
                            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                            dusk="file-input"
                        />
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 mx-auto mb-3" style="color: var(--muted);" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                        </svg>
                        <p class="text-sm" style="color: var(--muted);">
                            <span class="font-medium" style="color: var(--fg);">Click to upload</span> or drag and drop
                        </p>
                        <p class="text-xs mt-1" style="color: var(--muted);">
                            PDF, DOC, DOCX up to 10MB each
                        </p>
                    </div>

                    {{-- Loading indicator for file upload --}}
                    <div wire:loading wire:target="attachments" class="mt-3 flex items-center gap-2 text-sm" style="color: var(--muted);">
                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Uploading files...
                    </div>

                    {{-- File List --}}
                    @if(count($attachments) > 0)
                        <ul class="mt-4 space-y-2" dusk="attachment-list">
                            @foreach($attachments as $index => $file)
                                <li class="flex items-center justify-between p-3 rounded-lg" style="background: var(--bg, #0b1220); border: 1px solid var(--border, #1f2937);">
                                    <div class="flex items-center gap-3 overflow-hidden">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0" style="color: var(--muted);" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <span class="text-sm truncate" style="color: var(--fg);">{{ $file->getClientOriginalName() }}</span>
                                        <span class="text-xs flex-shrink-0" style="color: var(--muted);">{{ number_format($file->getSize() / 1024, 1) }} KB</span>
                                    </div>
                                    <button
                                        type="button"
                                        wire:click="removeAttachment({{ $index }})"
                                        class="p-1 rounded hover:bg-red-500/20 transition-colors"
                                        style="color: #ef4444;"
                                        dusk="remove-attachment-{{ $index }}"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @error('attachments.*')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Form Actions --}}
            <div class="px-6 py-4 flex items-center justify-end gap-3" style="border-top: 1px solid var(--border, #1f2937);">
                <a href="{{ route('ekom.podnesci') }}" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors" style="color: var(--muted); border: 1px solid var(--border, #1f2937);">
                    Cancel
                </a>
                <button
                    type="submit"
                    class="px-6 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2"
                    style="background: #3b82f6; color: #fff;"
                    wire:loading.attr="disabled"
                    wire:target="submit"
                    dusk="submit-button"
                >
                    <span wire:loading.remove wire:target="submit">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                        </svg>
                    </span>
                    <svg wire:loading wire:target="submit" class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <span wire:loading.remove wire:target="submit">Submit</span>
                    <span wire:loading wire:target="submit">Submitting...</span>
                </button>
            </div>
        </form>

        {{-- Help Text --}}
        <div class="mt-6 p-4 rounded-lg" style="background: rgba(59,130,246,0.1); border: 1px solid rgba(59,130,246,0.2);">
            <div class="flex items-start gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mt-0.5 flex-shrink-0" style="color: #3b82f6;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div class="text-sm" style="color: var(--muted);">
                    <p class="font-medium" style="color: var(--fg);">Submission Guidelines</p>
                    <ul class="mt-2 space-y-1 list-disc list-inside">
                        <li>Ensure you have the correct Case ID (Predmet ID) from the court system</li>
                        <li>Select the appropriate submission type for your document</li>
                        <li>Attachments should be in PDF or DOC format, max 10MB each</li>
                        <li>Review all information before submitting as submissions cannot be modified after submission</li>
                    </ul>
                </div>
            </div>
        </div>
    </main>
</div>
