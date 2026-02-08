<div dusk="laws-manager-container" class="ingested-laws-manager">
    {{-- Search and Create --}}
    <div class="controls-bar">
        <div class="ctrl search-control">
            <label class="search-label">Search</label>
            <div class="search-input-wrapper">
                <input dusk="law-search-input"
                       type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="Search by title, doc ID, law number..."
                       class="in search-input" />
                <div class="loading-spinner" wire:loading wire:target="search">
                    <svg class="spinner-icon" viewBox="0 0 24 24">
                        <circle class="spinner-circle" cx="12" cy="12" r="10" stroke-width="3" fill="none"/>
                    </svg>
                </div>
            </div>
        </div>
        <div class="action-buttons">
            <button dusk="scrape-laws-button"
                    wire:click="openScraper"
                    class="btn btn-success btn-action"
                    wire:loading.attr="disabled"
                    wire:target="openScraper">
                <span wire:loading.remove wire:target="openScraper">
                    <span class="btn-icon">🌐</span>
                    Scrape Laws
                </span>
                <span wire:loading wire:target="openScraper" class="btn-loading">
                    <svg class="btn-spinner" viewBox="0 0 24 24">
                        <circle class="spinner-circle" cx="12" cy="12" r="10" stroke-width="3" fill="none"/>
                    </svg>
                    Opening...
                </span>
            </button>
            <button dusk="create-ingested-button"
                    wire:click="createIngested"
                    class="btn btn-primary btn-action"
                    wire:loading.attr="disabled"
                    wire:target="createIngested">
                <span wire:loading.remove wire:target="createIngested">
                    <span class="btn-icon">➕</span>
                    New Ingested Law
                </span>
                <span wire:loading wire:target="createIngested" class="btn-loading">
                    <svg class="btn-spinner" viewBox="0 0 24 24">
                        <circle class="spinner-circle" cx="12" cy="12" r="10" stroke-width="3" fill="none"/>
                    </svg>
                    Loading...
                </span>
            </button>
            @if($search)
                <button dusk="clear-search-button"
                        wire:click="$set('search','')"
                        class="btn btn-secondary btn-action"
                        wire:loading.attr="disabled"
                        wire:target="search">
                    <span class="btn-icon">✕</span>
                    Clear Search
                </button>
            @endif
        </div>
        <div class="ctrl">
            <label>Law Type</label>
            <select dusk="law-type-filter" wire:model.live="lawTypeFilter" class="in" style="min-width:200px">
                <option value="">All Types</option>
                <option value="criminal_procedure">Criminal Procedure</option>
                <option value="constitution">Constitution</option>
                <option value="civil_law">Civil Law</option>
                <option value="administrative">Administrative</option>
            </select>
        </div>
        <button dusk="scrape-laws-button" wire:click="openScraper" class="btn success">
            🌐 Scrape Laws from zakon.hr
        </button>
        <button dusk="create-ingested-button" wire:click="createIngested" class="btn primary">
            ➕ New Ingested Law
        </button>
        @if($search)
            <button dusk="clear-search-button" wire:click="$set('search','')" class="btn">Clear Search</button>
        @endif

    </div>

    {{-- Main Grid --}}
    <div class="grid-2 main-grid">
        <!-- Left: IngestedLaws List -->
        <div class="ingested-laws-panel">
            <div class="panel-header seg">
                <div class="panel-header-content">
                    <div class="label panel-title">Ingested Laws Listing</div>
                    <div class="sort-controls">
                        <span class="sort-label">Sort:</span>
                        <button dusk="sort-ingested-at"
                                wire:click="sortBy('ingested_at')"
                                class="chip sort-chip {{ $sortField === 'ingested_at' ? 'active' : '' }}"
                                wire:loading.attr="disabled"
                                wire:target="sortBy">
                            <span wire:loading.remove wire:target="sortBy">
                                Ingested At {{ $sortField === 'ingested_at' ? ($sortDirection === 'asc' ? '↑' : '↓') : '' }}
                            </span>
                            <span wire:loading wire:target="sortBy" class="sort-loading">
                                <svg class="sort-spinner" viewBox="0 0 24 24">
                                    <circle class="spinner-circle" cx="12" cy="12" r="10" stroke-width="3" fill="none"/>
                                </svg>
                            </span>
                        </button>
                        <button dusk="sort-title"
                                wire:click="sortBy('title')"
                                class="chip sort-chip {{ $sortField === 'title' ? 'active' : '' }}"
                                wire:loading.attr="disabled"
                                wire:target="sortBy">
                            <span wire:loading.remove wire:target="sortBy">
                                Title {{ $sortField === 'title' ? ($sortDirection === 'asc' ? '↑' : '↓') : '' }}
                            </span>
                            <span wire:loading wire:target="sortBy" class="sort-loading">
                                <svg class="sort-spinner" viewBox="0 0 24 24">
                                    <circle class="spinner-circle" cx="12" cy="12" r="10" stroke-width="3" fill="none"/>
                                </svg>
                            </span>
                        </button>
                        <button dusk="sort-law-number"
                                wire:click="sortBy('law_number')"
                                class="chip sort-chip {{ $sortField === 'law_number' ? 'active' : '' }}"
                                wire:loading.attr="disabled"
                                wire:target="sortBy">
                            <span wire:loading.remove wire:target="sortBy">
                                Law # {{ $sortField === 'law_number' ? ($sortDirection === 'asc' ? '↑' : '↓') : '' }}
                            </span>
                            <span wire:loading wire:target="sortBy" class="sort-loading">
                                <svg class="sort-spinner" viewBox="0 0 24 24">
                                    <circle class="spinner-circle" cx="12" cy="12" r="10" stroke-width="3" fill="none"/>
                                </svg>
                            </span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="laws-list-wrapper" wire:loading.class="loading" wire:target="search,sortBy">
                <div class="loading-overlay" wire:loading wire:target="search,sortBy">
                    <svg class="loading-spinner-large" viewBox="0 0 24 24">
                        <circle class="spinner-circle" cx="12" cy="12" r="10" stroke-width="3" fill="none"/>
                    </svg>
                </div>
                <ul dusk="ingested-laws-list" class="seg-list laws-list">
                    @forelse($ingested as $index => $row)
                        <li dusk="ingested-law-{{ $index }}"
                            class="seg law-item {{ $selectedIngestedId === $row->id ? 'active' : '' }}"
                            wire:key="ingested-{{ $row->id }}">
                            <div class="law-item-content">
                                <button dusk="select-ingested-{{ $index }}"
                                        wire:click="selectIngested('{{ $row->id }}')"
                                        class="law-select-btn"
                                        wire:loading.attr="disabled"
                                        wire:target="selectIngested">
                                    <div dusk="ingested-title-{{ $index }}" class="law-title">
                                        {{ $row->title ?? 'Untitled' }}
                                    </div>
                                    <div class="law-meta">
                                        Doc: <span class="doc-id">{{ $row->doc_id }}</span>
                                        @if($row->law_number)
                                            <span class="meta-separator">·</span>
                                            <span class="law-number">#{{ $row->law_number }}</span>
                                        @endif
                                        @if($row->jurisdiction)
                                            <span class="meta-separator">·</span>
                                            <span class="jurisdiction">{{ $row->jurisdiction }}</span>
                                        @endif
                                    </div>
                                </button>
                                <div class="law-actions">
                                    <button dusk="edit-ingested-{{ $index }}"
                                            wire:click="editIngested('{{ $row->id }}')"
                                            class="btn btn-sm btn-edit"
                                            wire:loading.attr="disabled"
                                            wire:target="editIngested('{{ $row->id }}')">
                                        <span wire:loading.remove wire:target="editIngested('{{ $row->id }}')">
                                            <span class="btn-icon">✏️</span> Edit
                                        </span>
                                        <span wire:loading wire:target="editIngested('{{ $row->id }}')" class="btn-loading">
                                            <svg class="btn-spinner-sm" viewBox="0 0 24 24">
                                                <circle class="spinner-circle" cx="12" cy="12" r="10" stroke-width="3" fill="none"/>
                                            </svg>
                                        </span>
                                    </button>
                                    <button dusk="delete-ingested-{{ $index }}"
                                            wire:click="deleteIngested('{{ $row->id }}')"
                                            wire:confirm="Delete this ingested law?"
                                            class="btn btn-sm btn-delete"
                                            wire:loading.attr="disabled"
                                            wire:target="deleteIngested('{{ $row->id }}')">
                                        <span wire:loading.remove wire:target="deleteIngested('{{ $row->id }}')">
                                            <span class="btn-icon">🗑️</span> Delete
                                        </span>
                                        <span wire:loading wire:target="deleteIngested('{{ $row->id }}')" class="btn-loading">
                                            <svg class="btn-spinner-sm" viewBox="0 0 24 24">
                                                <circle class="spinner-circle" cx="12" cy="12" r="10" stroke-width="3" fill="none"/>
                                            </svg>
                                        </span>
                                    </button>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button dusk="view-law-details view-ingested-{{ $index }}" wire:click="viewIngestedDetails('{{ $row->id }}')" class="btn info" style="font-size:11px; padding:6px 10px">
                                    👁️ View
                                </button>
                                <button dusk="download-law-button download-ingested-{{ $index }}" wire:click="downloadLaw('{{ $row->id }}')" class="btn success" style="font-size:11px; padding:6px 10px">
                                    ⬇️ Download
                                </button>
                                <button dusk="edit-ingested-{{ $index }}" wire:click="editIngested('{{ $row->id }}')" class="btn" style="font-size:11px; padding:6px 10px">
                                    ✏️ Edit
                                </button>
                            </div>
                        </li>
                    @empty
                        <li dusk="no-laws-message" class="seg empty-state">
                            <div class="empty-icon">📭</div>
                            <div class="empty-text">No ingested laws found.</div>
                            @if($search)
                                <button wire:click="$set('search','')" class="btn btn-sm btn-secondary" style="margin-top: 12px;">
                                    Clear search to see all laws
                                </button>
                            @endif
                        </li>
                    @endforelse
                </ul>
            </div>

            <div class="pagination mt-4">
                {{ $ingested->links() }}
            </div>
        </div>

        <!-- Right: Details and Children -->
        <div class="details-panel">
            @if($selected)
                <div class="seg details-header">
                    <div class="details-header-content">
                        <div class="details-info">
                            <h2 class="details-title">
                                {{ $selected->title ?? 'Untitled' }}
                            </h2>
                            <div class="details-meta">
                                Doc: <span class="doc-id">{{ $selected->doc_id }}</span>
                                @if($selected->law_number)
                                    <span class="meta-separator">·</span>
                                    <span class="law-number">#{{ $selected->law_number }}</span>
                                @endif
                                @if($selected->jurisdiction)
                                    <span class="meta-separator">·</span>
                                    <span class="jurisdiction">{{ $selected->jurisdiction }}</span>
                                @endif
                                @if($selected->language)
                                    <span class="meta-separator">·</span>
                                    <span class="language">{{ strtoupper($selected->language) }}</span>
                                @endif
                            </div>
                        </div>
                        <button wire:click="editIngested('{{ $selected->id }}')"
                                class="btn btn-info btn-edit-selected"
                                wire:loading.attr="disabled"
                                wire:target="editIngested('{{ $selected->id }}')">
                            <span wire:loading.remove wire:target="editIngested('{{ $selected->id }}')">
                                <span class="btn-icon">✏️</span> Edit
                            </span>
                            <span wire:loading wire:target="editIngested('{{ $selected->id }}')" class="btn-loading">
                                <svg class="btn-spinner" viewBox="0 0 24 24">
                                    <circle class="spinner-circle" cx="12" cy="12" r="10" stroke-width="3" fill="none"/>
                                </svg>
                            </span>
                        </button>
                    </div>

                    {{-- Tabs --}}
                    <div class="tabs-container">
                        <button dusk="tab-laws"
                                wire:click="$set('tab','laws')"
                                class="tab {{ $tab === 'laws' ? 'active' : '' }}"
                                wire:loading.attr="disabled"
                                wire:target="$set('tab','laws')">
                            <span class="tab-icon">📜</span>
                            <span class="tab-label">Law Chunks</span>
                            <div class="tab-indicator"></div>
                        </button>
                        <button dusk="tab-uploads"
                                wire:click="$set('tab','uploads')"
                                class="tab {{ $tab === 'uploads' ? 'active' : '' }}"
                                wire:loading.attr="disabled"
                                wire:target="$set('tab','uploads')">
                            <span class="tab-icon">📎</span>
                            <span class="tab-label">Uploads</span>
                            <div class="tab-indicator"></div>
                        </button>
                    </div>

                    {{-- Laws Tab --}}
                    @if($tab === 'laws')
                        <div dusk="laws-tab-content" class="tab-content">
                            <div class="tab-content-header">
                                <div class="tab-description">Chunked content from this ingested law</div>
                                <button dusk="create-law-button"
                                        wire:click="createLaw"
                                        class="btn btn-success btn-create"
                                        wire:loading.attr="disabled"
                                        wire:target="createLaw">
                                    <span wire:loading.remove wire:target="createLaw">
                                        <span class="btn-icon">➕</span> New Chunk
                                    </span>
                                    <span wire:loading wire:target="createLaw" class="btn-loading">
                                        <svg class="btn-spinner" viewBox="0 0 24 24">
                                            <circle class="spinner-circle" cx="12" cy="12" r="10" stroke-width="3" fill="none"/>
                                        </svg>
                                        Loading...
                                    </span>
                                </button>
                            </div>

                            <div class="table-wrapper" wire:loading.class="loading" wire:target="tab">
                                <div class="loading-overlay" wire:loading wire:target="tab">
                                    <svg class="loading-spinner-large" viewBox="0 0 24 24">
                                        <circle class="spinner-circle" cx="12" cy="12" r="10" stroke-width="3" fill="none"/>
                                    </svg>
                                </div>
                                <div class="table-container">
                                    <table dusk="laws-table" class="data-table">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Title</th>
                                                <th>Article #</th>
                                                <th>Chunk</th>
                                                <th>Lang</th>
                                                <th>Updated</th>
                                                <th class="text-center">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($laws as $lawIndex => $law)
                                                <tr dusk="law-row-{{ $lawIndex }}" wire:key="law-{{ $law->id }}" class="table-row">
                                                    <td class="id-cell">
                                                        <span class="id-text">{{ \Illuminate\Support\Str::limit($law->id, 8, '') }}</span>
                                                    </td>
                                                    <td dusk="law-title-{{ $lawIndex }}" class="title-cell">
                                                        {{ $law->title ?? '—' }}
                                                    </td>
                                                    <td class="article-cell">
                                                        @if(isset($law->metadata['article_number']))
                                                            <span class="chip chip-info">Art. {{ $law->metadata['article_number'] }}</span>
                                                        @else
                                                            <span class="text-muted">—</span>
                                                        @endif
                                                    </td>
                                                    <td class="chunk-cell">
                                                        <span class="chip chip-default">#{{ $law->chunk_index }}</span>
                                                    </td>
                                                    <td class="lang-cell">{{ strtoupper($law->language ?? '—') }}</td>
                                                    <td class="date-cell">{{ $law->updated_at?->diffForHumans() }}</td>
                                                    <td class="actions-cell">
                                                        <div class="action-buttons-group">
                                                            <button dusk="view-law-{{ $lawIndex }}"
                                                                    wire:click="viewLaw('{{ $law->id }}')"
                                                                    class="btn btn-xs btn-info"
                                                                    wire:loading.attr="disabled"
                                                                    wire:target="viewLaw('{{ $law->id }}')">
                                                                <span wire:loading.remove wire:target="viewLaw('{{ $law->id }}')">
                                                                    <span class="btn-icon">👁️</span> View
                                                                </span>
                                                                <span wire:loading wire:target="viewLaw('{{ $law->id }}')" class="btn-loading">
                                                                    <svg class="btn-spinner-xs" viewBox="0 0 24 24">
                                                                        <circle class="spinner-circle" cx="12" cy="12" r="10" stroke-width="3" fill="none"/>
                                                                    </svg>
                                                                </span>
                                                            </button>
                                                            <button dusk="edit-law-{{ $lawIndex }}"
                                                                    wire:click="editLaw('{{ $law->id }}')"
                                                                    class="btn btn-xs btn-edit"
                                                                    wire:loading.attr="disabled"
                                                                    wire:target="editLaw('{{ $law->id }}')">
                                                                <span wire:loading.remove wire:target="editLaw('{{ $law->id }}')">
                                                                    <span class="btn-icon">✏️</span> Edit
                                                                </span>
                                                                <span wire:loading wire:target="editLaw('{{ $law->id }}')" class="btn-loading">
                                                                    <svg class="btn-spinner-xs" viewBox="0 0 24 24">
                                                                        <circle class="spinner-circle" cx="12" cy="12" r="10" stroke-width="3" fill="none"/>
                                                                    </svg>
                                                                </span>
                                                            </button>
                                                            <button dusk="delete-law-{{ $lawIndex }}"
                                                                    wire:click="deleteLaw('{{ $law->id }}')"
                                                                    wire:confirm="Delete this law chunk?"
                                                                    class="btn btn-xs btn-delete"
                                                                    wire:loading.attr="disabled"
                                                                    wire:target="deleteLaw('{{ $law->id }}')">
                                                                <span wire:loading.remove wire:target="deleteLaw('{{ $law->id }}')">
                                                                    <span class="btn-icon">🗑️</span> Delete
                                                                </span>
                                                                <span wire:loading wire:target="deleteLaw('{{ $law->id }}')" class="btn-loading">
                                                                    <svg class="btn-spinner-xs" viewBox="0 0 24 24">
                                                                        <circle class="spinner-circle" cx="12" cy="12" r="10" stroke-width="3" fill="none"/>
                                                                    </svg>
                                                                </span>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td dusk="no-laws-message" colspan="7" class="empty-state-cell">
                                                        <div class="empty-state-content">
                                                            <div class="empty-icon">📄</div>
                                                            <div class="empty-text">No law chunks yet.</div>
                                                            <button wire:click="createLaw" class="btn btn-sm btn-success" style="margin-top: 12px;">
                                                                <span class="btn-icon">➕</span> Create First Chunk
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="pagination mt-2">
                                {{ $laws->links(data: ['scrollTo' => false]) }}
                            </div>
                        </div>
                    @endif

                    {{-- Uploads Tab --}}
                    @if($tab === 'uploads')
                        <div dusk="uploads-tab-content" class="tab-content">
                            <div class="tab-content-header">
                                <div class="tab-description">Source files and related uploads</div>
                                <button dusk="create-upload-button"
                                        wire:click="createUpload"
                                        class="btn btn-success btn-create"
                                        wire:loading.attr="disabled"
                                        wire:target="createUpload">
                                    <span wire:loading.remove wire:target="createUpload">
                                        <span class="btn-icon">➕</span> New Upload
                                    </span>
                                    <span wire:loading wire:target="createUpload" class="btn-loading">
                                        <svg class="btn-spinner" viewBox="0 0 24 24">
                                            <circle class="spinner-circle" cx="12" cy="12" r="10" stroke-width="3" fill="none"/>
                                        </svg>
                                        Loading...
                                    </span>
                                </button>
                            </div>

                            <div class="table-wrapper" wire:loading.class="loading" wire:target="tab">
                                <div class="loading-overlay" wire:loading wire:target="tab">
                                    <svg class="loading-spinner-large" viewBox="0 0 24 24">
                                        <circle class="spinner-circle" cx="12" cy="12" r="10" stroke-width="3" fill="none"/>
                                    </svg>
                                </div>
                                <div class="table-container">
                                    <table dusk="uploads-table" class="data-table">
                                        <thead>
                                            <tr>
                                                <th>Path</th>
                                                <th>Disk</th>
                                                <th>Size</th>
                                                <th>SHA256</th>
                                                <th>Status</th>
                                                <th class="text-center">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($uploads as $uploadIndex => $u)
                                                <tr dusk="upload-row-{{ $uploadIndex }}" wire:key="upload-{{ $u->id }}" class="table-row">
                                                    <td dusk="upload-path-{{ $uploadIndex }}" class="path-cell" title="{{ $u->local_path }}">
                                                        {{ $u->local_path }}
                                                    </td>
                                                    <td class="disk-cell">
                                                        <span class="chip chip-default">{{ $u->disk }}</span>
                                                    </td>
                                                    <td class="size-cell">
                                                        {{ $u->file_size ? number_format($u->file_size / 1024, 1) . ' KB' : '—' }}
                                                    </td>
                                                    <td class="hash-cell">
                                                        <span class="hash-text">{{ \Illuminate\Support\Str::limit($u->sha256 ?? '—', 12, '…') }}</span>
                                                    </td>
                                                    <td dusk="upload-status-{{ $uploadIndex }}" class="status-cell">
                                                        @if($u->status === 'stored')
                                                            <span class="chip chip-success">
                                                                <span class="chip-icon">✅</span>
                                                                {{ ucfirst($u->status) }}
                                                            </span>
                                                        @elseif($u->status === 'error')
                                                            <span class="chip chip-error">
                                                                <span class="chip-icon">❌</span>
                                                                {{ ucfirst($u->status) }}
                                                            </span>
                                                        @else
                                                            <span class="chip chip-default">{{ ucfirst($u->status) }}</span>
                                                        @endif
                                                    </td>
                                                    <td class="actions-cell">
                                                        <div class="action-buttons-group">
                                                            <button dusk="edit-upload-{{ $uploadIndex }}"
                                                                    wire:click="editUpload('{{ $u->id }}')"
                                                                    class="btn btn-xs btn-edit"
                                                                    wire:loading.attr="disabled"
                                                                    wire:target="editUpload('{{ $u->id }}')">
                                                                <span wire:loading.remove wire:target="editUpload('{{ $u->id }}')">
                                                                    <span class="btn-icon">✏️</span> Edit
                                                                </span>
                                                                <span wire:loading wire:target="editUpload('{{ $u->id }}')" class="btn-loading">
                                                                    <svg class="btn-spinner-xs" viewBox="0 0 24 24">
                                                                        <circle class="spinner-circle" cx="12" cy="12" r="10" stroke-width="3" fill="none"/>
                                                                    </svg>
                                                                </span>
                                                            </button>
                                                            <button dusk="delete-upload-{{ $uploadIndex }}"
                                                                    wire:click="deleteUpload('{{ $u->id }}')"
                                                                    wire:confirm="Delete this upload?"
                                                                    class="btn btn-xs btn-delete"
                                                                    wire:loading.attr="disabled"
                                                                    wire:target="deleteUpload('{{ $u->id }}')">
                                                                <span wire:loading.remove wire:target="deleteUpload('{{ $u->id }}')">
                                                                    <span class="btn-icon">🗑️</span> Delete
                                                                </span>
                                                                <span wire:loading wire:target="deleteUpload('{{ $u->id }}')" class="btn-loading">
                                                                    <svg class="btn-spinner-xs" viewBox="0 0 24 24">
                                                                        <circle class="spinner-circle" cx="12" cy="12" r="10" stroke-width="3" fill="none"/>
                                                                    </svg>
                                                                </span>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td dusk="no-uploads-message" colspan="6" class="empty-state-cell">
                                                        <div class="empty-state-content">
                                                            <div class="empty-icon">📎</div>
                                                            <div class="empty-text">No uploads yet.</div>
                                                            <button wire:click="createUpload" class="btn btn-sm btn-success" style="margin-top: 12px;">
                                                                <span class="btn-icon">➕</span> Create First Upload
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="pagination mt-2">
                                {{ $uploads->links(data: ['scrollTo' => false]) }}
                            </div>
                        </div>
                    @endif
                </div>
            @else
                <div class="seg empty-selection">
                    <div class="empty-selection-icon">👈</div>
                    <div class="empty-selection-text">
                        Select an ingested law from the list to view and manage its law chunks and uploads.
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Modal: IngestedLaw --}}
    <div x-data="{ open: @entangle('showIngestedModal') }"
         x-cloak
         x-show="open"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="modal-backdrop"
         @click.self="open=false">
        <div dusk="ingested-modal"
             class="modal modern-modal"
             @click.stop
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform scale-95"
             x-transition:enter-end="opacity-100 transform scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 transform scale-100"
             x-transition:leave-end="opacity-0 transform scale-95">
            <div class="modal-header">
                <h2 dusk="ingested-modal-title" class="modal-title">
                    <span class="modal-icon">{{ isset($editingIngested['id']) && $editingIngested['id'] ? '✏️' : '➕' }}</span>
                    {{ isset($editingIngested['id']) && $editingIngested['id'] ? 'Edit Ingested Law' : 'New Ingested Law' }}
                </h2>
                <button dusk="close-ingested-modal" @click="open=false" class="btn btn-close">
                    <span class="btn-icon">✕</span>
                </button>
            </div>
            <form wire:submit.prevent="saveIngested" class="modal-body" wire:loading.class="form-loading">
                <div class="form-loading-overlay" wire:loading wire:target="saveIngested">
                    <svg class="loading-spinner-large" viewBox="0 0 24 24">
                        <circle class="spinner-circle" cx="12" cy="12" r="10" stroke-width="3" fill="none"/>
                    </svg>
                    <div class="loading-text">Saving...</div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Doc ID *</label>
                        <input dusk="ingested-doc-id-input" type="text" wire:model.defer="editingIngested.doc_id" required />
                        @error('editingIngested.doc_id') <div class="error-text">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-group">
                        <label>Law Number</label>
                        <input dusk="ingested-law-number-input" type="text" wire:model.defer="editingIngested.law_number" />
                    </div>
                    <div class="form-group span-2">
                        <label>Title</label>
                        <input dusk="ingested-title-input" type="text" wire:model.defer="editingIngested.title" />
                    </div>
                    <div class="form-group">
                        <label>Jurisdiction</label>
                        <input type="text" wire:model.defer="editingIngested.jurisdiction" />
                    </div>
                    <div class="form-group">
                        <label>Country</label>
                        <input type="text" wire:model.defer="editingIngested.country" />
                    </div>
                    <div class="form-group">
                        <label>Language</label>
                        <input type="text" wire:model.defer="editingIngested.language" placeholder="e.g., en, hr" maxlength="16" />
                    </div>
                    <div class="form-group">
                        <label>Source URL</label>
                        <input type="url" wire:model.defer="editingIngested.source_url" />
                    </div>
                    <div class="form-group span-2">
                        <label>Keywords (comma-separated)</label>
                        <input type="text" wire:model.defer="editingIngested.keywords_text" placeholder="law, regulation, policy" />
                    </div>
                    <div class="form-group span-2">
                        <label>Metadata (JSON)</label>
                        <textarea wire:model.defer="editingIngested.metadata" rows="6" placeholder='{"key": "value"}' style="font-family: monospace; font-size: 12px;"></textarea>
                        @error('editingIngested.metadata') <div class="error-text">{{ $message }}</div> @enderror
                        <div class="text-xs text-muted" style="margin-top:4px">
                            Enter valid JSON. Example: {"law_code": "ZKP", "aliases": ["Criminal Procedure Act"]}
                        </div>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" @click="open=false" class="btn btn-secondary" wire:loading.attr="disabled" wire:target="saveIngested">
                        Cancel
                    </button>
                    <button dusk="save-ingested-button"
                            type="submit"
                            class="btn btn-primary"
                            wire:loading.attr="disabled"
                            wire:target="saveIngested">
                        <span wire:loading.remove wire:target="saveIngested">
                            <span class="btn-icon">💾</span> Save
                        </span>
                        <span wire:loading wire:target="saveIngested" class="btn-loading">
                            <svg class="btn-spinner" viewBox="0 0 24 24">
                                <circle class="spinner-circle" cx="12" cy="12" r="10" stroke-width="3" fill="none"/>
                            </svg>
                            Saving...
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal: Law --}}
    <div x-data="{ open: @entangle('showLawModal') }"
         x-cloak
         x-show="open"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="modal-backdrop"
         @click.self="open=false">
        <div dusk="law-modal"
             class="modal modern-modal"
             @click.stop
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform scale-95"
             x-transition:enter-end="opacity-100 transform scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 transform scale-100"
             x-transition:leave-end="opacity-0 transform scale-95">
            <div class="modal-header">
                <h2 dusk="law-modal-title" class="modal-title">
                    <span class="modal-icon">{{ isset($editingLaw['id']) && $editingLaw['id'] ? '✏️' : '➕' }}</span>
                    {{ isset($editingLaw['id']) && $editingLaw['id'] ? 'Edit Law Chunk' : 'New Law Chunk' }}
                </h2>
                <button dusk="close-law-modal" @click="open=false" class="btn btn-close">
                    <span class="btn-icon">✕</span>
                </button>
            </div>
            <form wire:submit.prevent="saveLaw" class="modal-body" wire:loading.class="form-loading">
                <div class="form-loading-overlay" wire:loading wire:target="saveLaw">
                    <svg class="loading-spinner-large" viewBox="0 0 24 24">
                        <circle class="spinner-circle" cx="12" cy="12" r="10" stroke-width="3" fill="none"/>
                    </svg>
                    <div class="loading-text">Saving...</div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Doc ID *</label>
                        <input dusk="law-doc-id-input" type="text" wire:model.defer="editingLaw.doc_id" required />
                        @error('editingLaw.doc_id') <div class="error-text">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-group">
                        <label>Chunk Index *</label>
                        <input dusk="law-chunk-index-input" type="number" wire:model.defer="editingLaw.chunk_index" min="0" required />
                        @error('editingLaw.chunk_index') <div class="error-text">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-group span-2">
                        <label>Title</label>
                        <input dusk="law-title-input" type="text" wire:model.defer="editingLaw.title" />
                    </div>
                    <div class="form-group">
                        <label>Language</label>
                        <input type="text" wire:model.defer="editingLaw.language" placeholder="e.g., en, hr" maxlength="16" />
                    </div>
                    <div class="form-group">
                        <label>Source URL</label>
                        <input type="url" wire:model.defer="editingLaw.source_url" />
                    </div>
                    <div class="form-group span-2">
                        <label>Content *</label>
                        <textarea dusk="law-content-input" wire:model.defer="editingLaw.content" rows="8" required></textarea>
                        @error('editingLaw.content') <div class="error-text">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-group span-2">
                        <label>Metadata (JSON)</label>
                        <textarea wire:model.defer="editingLaw.metadata" rows="8" placeholder='{"key": "value"}' style="font-family: monospace; font-size: 12px;"></textarea>
                        @error('editingLaw.metadata') <div class="error-text">{{ $message }}</div> @enderror
                        <div class="text-xs text-muted" style="margin-top:4px">
                            Enter valid JSON. Includes: article_number, law_code, keywords, anchors, etc.
                        </div>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" @click="open=false" class="btn btn-secondary" wire:loading.attr="disabled" wire:target="saveLaw">
                        Cancel
                    </button>
                    <button dusk="save-law-button"
                            type="submit"
                            class="btn btn-primary"
                            wire:loading.attr="disabled"
                            wire:target="saveLaw">
                        <span wire:loading.remove wire:target="saveLaw">
                            <span class="btn-icon">💾</span> Save
                        </span>
                        <span wire:loading wire:target="saveLaw" class="btn-loading">
                            <svg class="btn-spinner" viewBox="0 0 24 24">
                                <circle class="spinner-circle" cx="12" cy="12" r="10" stroke-width="3" fill="none"/>
                            </svg>
                            Saving...
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal: View Law Chunk --}}
    <div x-data="{ open: @entangle('showLawViewModal') }"
         x-cloak
         x-show="open"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="modal-backdrop"
         @click.self="open=false">
        <div dusk="law-details-modal"
             class="modal modern-modal modal-wide"
             @click.stop
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform scale-95"
             x-transition:enter-end="opacity-100 transform scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 transform scale-100"
             x-transition:leave-end="opacity-0 transform scale-95">
            <div class="modal-header">
                <h2 class="modal-title">
                    <span class="modal-icon">👁️</span>
                    View Law Chunk
                </h2>
                <button @click="open=false" class="btn btn-close">
                    <span class="btn-icon">✕</span>
                </button>
            </div>
            <div class="modal-body">
                @if(!empty($viewingLaw))
                    <div class="seg" style="margin-bottom:16px">
                        <div style="display:grid; grid-template-columns: 150px 1fr; gap:12px; font-size:14px">
                            <div style="font-weight:600; color:var(--muted)">ID:</div>
                            <div style="font-family:monospace; font-size:12px">{{ $viewingLaw['id'] ?? '—' }}</div>

                            <div style="font-weight:600; color:var(--muted)">Doc ID:</div>
                            <div style="font-family:monospace; font-size:12px">{{ $viewingLaw['doc_id'] ?? '—' }}</div>

                            <div style="font-weight:600; color:var(--muted)">Title:</div>
                            <div>{{ $viewingLaw['title'] ?? '—' }}</div>

                            @if(isset($viewingLaw['metadata']['article_number']))
                                <div style="font-weight:600; color:var(--muted)">Article Number:</div>
                                <div><span class="chip info" style="padding:6px 12px">Article {{ $viewingLaw['metadata']['article_number'] }}</span></div>
                            @endif

                            <div style="font-weight:600; color:var(--muted)">Chunk Index:</div>
                            <div><span class="chip" style="padding:6px 12px">#{{ $viewingLaw['chunk_index'] ?? 0 }}</span></div>

                            <div style="font-weight:600; color:var(--muted)">Jurisdiction:</div>
                            <div>{{ $viewingLaw['jurisdiction'] ?? '—' }}</div>

                            <div style="font-weight:600; color:var(--muted)">Language:</div>
                            <div>{{ strtoupper($viewingLaw['language'] ?? '—') }}</div>

                            @if(!empty($viewingLaw['source_url']))
                                <div style="font-weight:600; color:var(--muted)">Source URL:</div>
                                <div><a href="{{ $viewingLaw['source_url'] }}" target="_blank" class="text-accent">{{ $viewingLaw['source_url'] }}</a></div>
                            @endif
                        </div>
                    </div>

                    <div class="seg" style="margin-bottom:16px">
                        <div style="font-weight:600; margin-bottom:8px; color:var(--muted)">Content:</div>
                        <div style="background:#0b1220; padding:16px; border-radius:8px; border:1px solid var(--border); max-height:400px; overflow-y:auto; white-space:pre-wrap; font-family:monospace; font-size:12px; line-height:1.6">{{ $viewingLaw['content'] ?? 'No content' }}</div>
                    </div>

                    @if(!empty($viewingLaw['metadata']) && is_array($viewingLaw['metadata']))
                        <div class="seg">
                            <div style="font-weight:600; margin-bottom:12px; color:var(--muted)">Metadata:</div>
                            <div style="background:#0b1220; padding:16px; border-radius:8px; border:1px solid var(--border); max-height:300px; overflow-y:auto">
                                <table style="width:100%; font-size:13px">
                                    <tbody>
                                        @foreach($viewingLaw['metadata'] as $key => $value)
                                            <tr style="border-bottom:1px solid rgba(255,255,255,0.05)">
                                                <td style="padding:8px; font-weight:600; color:var(--accent); vertical-align:top; width:200px">{{ $key }}</td>
                                                <td style="padding:8px; color:var(--text); font-family:monospace; font-size:11px">
                                                    @if(is_array($value))
                                                        <pre style="margin:0; white-space:pre-wrap">{{ json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                    @else
                                                        {{ $value ?? '—' }}
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>

    {{-- Modal: Upload --}}
    <div x-data="{ open: @entangle('showUploadModal') }"
         x-cloak
         x-show="open"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="modal-backdrop"
         @click.self="open=false">
        <div class="modal modern-modal"
             @click.stop
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform scale-95"
             x-transition:enter-end="opacity-100 transform scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 transform scale-100"
             x-transition:leave-end="opacity-0 transform scale-95">
            <div class="modal-header">
                <h2 class="modal-title">
                    <span class="modal-icon">{{ isset($editingUpload['id']) && $editingUpload['id'] ? '✏️' : '➕' }}</span>
                    {{ isset($editingUpload['id']) && $editingUpload['id'] ? 'Edit Upload' : 'New Upload' }}
                </h2>
                <button @click="open=false" class="btn btn-close">
                    <span class="btn-icon">✕</span>
                </button>
            </div>
            <form wire:submit.prevent="saveUpload" class="modal-body" wire:loading.class="form-loading">
                <div class="form-loading-overlay" wire:loading wire:target="saveUpload">
                    <svg class="loading-spinner-large" viewBox="0 0 24 24">
                        <circle class="spinner-circle" cx="12" cy="12" r="10" stroke-width="3" fill="none"/>
                    </svg>
                    <div class="loading-text">Saving...</div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Doc ID *</label>
                        <input type="text" wire:model.defer="editingUpload.doc_id" required />
                        @error('editingUpload.doc_id') <div class="error-text">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-group">
                        <label>Disk *</label>
                        <input type="text" wire:model.defer="editingUpload.disk" required />
                    </div>
                    <div class="form-group span-2">
                        <label>Local Path *</label>
                        <input type="text" wire:model.defer="editingUpload.local_path" required />
                        @error('editingUpload.local_path') <div class="error-text">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-group">
                        <label>Original Filename</label>
                        <input type="text" wire:model.defer="editingUpload.original_filename" />
                    </div>
                    <div class="form-group">
                        <label>MIME Type</label>
                        <input type="text" wire:model.defer="editingUpload.mime_type" placeholder="application/pdf" />
                    </div>
                    <div class="form-group">
                        <label>File Size (bytes)</label>
                        <input type="number" wire:model.defer="editingUpload.file_size" min="0" />
                    </div>
                    <div class="form-group">
                        <label>SHA256 Hash</label>
                        <input type="text" wire:model.defer="editingUpload.sha256" maxlength="64" placeholder="64-character hash" />
                    </div>
                    <div class="form-group span-2">
                        <label>Source URL</label>
                        <input type="url" wire:model.defer="editingUpload.source_url" />
                    </div>
                    <div class="form-group">
                        <label>Downloaded At</label>
                        <input type="datetime-local" wire:model.defer="editingUpload.downloaded_at" />
                    </div>
                    <div class="form-group">
                        <label>Status *</label>
                        <select wire:model.defer="editingUpload.status" required>
                            <option value="stored">Stored</option>
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="error">Error</option>
                        </select>
                    </div>
                    <div class="form-group span-2">
                        <label>Error Message</label>
                        <textarea wire:model.defer="editingUpload.error" rows="3"></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" @click="open=false" class="btn btn-secondary" wire:loading.attr="disabled" wire:target="saveUpload">
                        Cancel
                    </button>
                    <button type="submit"
                            class="btn btn-primary"
                            wire:loading.attr="disabled"
                            wire:target="saveUpload">
                        <span wire:loading.remove wire:target="saveUpload">
                            <span class="btn-icon">💾</span> Save
                        </span>
                        <span wire:loading wire:target="saveUpload" class="btn-loading">
                            <svg class="btn-spinner" viewBox="0 0 24 24">
                                <circle class="spinner-circle" cx="12" cy="12" r="10" stroke-width="3" fill="none"/>
                            </svg>
                            Saving...
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal: Law Scraper --}}
    <div x-data="{ open: @entangle('showScraperModal') }"
         x-cloak
         x-show="open"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="modal-backdrop"
         @click.self="open=false">
        <div dusk="scraper-modal"
             class="modal modern-modal modal-xlarge"
             @click.stop
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform scale-95"
             x-transition:enter-end="opacity-100 transform scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 transform scale-100"
             x-transition:leave-end="opacity-0 transform scale-95">
            <div class="modal-header">
                <h2 class="modal-title">
                    <span class="modal-icon">🌐</span>
                    Scrape Laws from zakon.hr
                </h2>
                <button dusk="close-scraper-modal" @click="open=false" class="btn btn-close">
                    <span class="btn-icon">✕</span>
                </button>
            </div>
            <div class="modal-body">
                {{-- Scraper Instructions --}}
                @if(empty($scrapedLaws))
                    <div class="seg" style="margin-bottom:16px">
                        <div class="text-sm" style="line-height:1.6">
                            <strong>📋 Step 1: Fetch Available Laws</strong>
                            <ul style="margin:8px 0 0 20px; padding:0">
                                <li>Fetches list of laws from zakon.hr categories (98, 99, 100, 101)</li>
                                <li>Categories: Domovinski rat, Kazneno i prekršajno</li>
                                <li>Displays available laws with titles and metadata</li>
                            </ul>
                            <div style="margin-top:12px; padding:10px; background:rgba(14,165,233,0.1); border:1px solid rgba(14,165,233,0.2); border-radius:8px">
                                <strong style="color:#7dd3fc">ℹ️ Note:</strong>
                                <span style="color:var(--muted)">After fetching the list, you can select which laws to actually scrape and import the full content.</span>
                            </div>
                        </div>
                    </div>

                    <div class="text-center" style="padding:20px">
                        <button dusk="fetch-laws-button"
                                wire:click="startScraping"
                                wire:loading.attr="disabled"
                                class="btn success"
                                style="padding:12px 24px; font-size:15px">
                            <span wire:loading.remove wire:target="startScraping">🚀 Fetch Available Laws</span>
                            <span wire:loading wire:target="startScraping">⏳ Fetching list...</span>
                        </button>
                    </div>
                @else
                    {{-- Scraped Laws List --}}
                    <div>
                        <div class="seg" style="margin-bottom:16px">
                            <div class="text-sm" style="line-height:1.6">
                                <strong>✅ Step 2: Select Laws to Import</strong>
                                <div style="color:var(--muted); margin-top:4px">
                                    Found <strong style="color:var(--accent)">{{ count($scrapedLaws) }}</strong> unique laws.
                                    Select the laws you want to scrape and import the full content.
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between mb-4">
                            <div class="text-sm">
                                @if(!empty($selectedLawsToImport))
                                    <strong class="text-sm" style="color:var(--accent)">{{ count($selectedLawsToImport) }}</strong> law(s) selected for import
                                @else
                                    <span class="text-muted">No laws selected yet</span>
                                @endif
                            </div>
                            <div class="flex gap-2">
                                <div class="ctrl" style="margin:0">
                                    <input dusk="scraper-filter-input"
                                           type="text"
                                           wire:model.live.debounce.300ms="scraperSearchFilter"
                                           placeholder="Filter laws..."
                                           class="in small"
                                           style="min-width:200px" />
                                </div>

                                <button dusk="select-all-button"
                                        wire:click="selectAllFilteredLaws"
                                        class="btn btn-sm btn-secondary"
                                        wire:loading.attr="disabled"
                                        wire:target="selectAllFilteredLaws">
                                    <span wire:loading.remove wire:target="selectAllFilteredLaws">
                                        <span class="btn-icon">✓</span> Select All
                                    </span>
                                    <span wire:loading wire:target="selectAllFilteredLaws" class="btn-loading">
                                        <svg class="btn-spinner-sm" viewBox="0 0 24 24">
                                            <circle class="spinner-circle" cx="12" cy="12" r="10" stroke-width="3" fill="none"/>
                                        </svg>
                                    </span>
                                </button>
                                <button dusk="deselect-all-button"
                                        wire:click="deselectAllLaws"
                                        class="btn btn-sm btn-secondary"
                                        wire:loading.attr="disabled"
                                        wire:target="deselectAllLaws">
                                    <span wire:loading.remove wire:target="deselectAllLaws">
                                        <span class="btn-icon">✗</span> Deselect All
                                    </span>
                                    <span wire:loading wire:target="deselectAllLaws" class="btn-loading">
                                        <svg class="btn-spinner-sm" viewBox="0 0 24 24">
                                            <circle class="spinner-circle" cx="12" cy="12" r="10" stroke-width="3" fill="none"/>
                                        </svg>
                                    </span>
                                </button>
                            </div>
                        </div>

                        <div class="table-container" style="max-height:500px; overflow-y:auto">
                            <table dusk="scraped-laws-table">
                                <thead style="position:sticky; top:0; z-index:1">
                                    <tr>
                                        <th style="width:40px">
                                            <input dusk="select-all-checkbox"
                                                   type="checkbox"
                                                   @if(count($selectedLawsToImport) > 0 && count($selectedLawsToImport) === count($this->getFilteredScrapedLaws()))
                                                       checked
                                                   @endif
                                                   wire:click="selectAllFilteredLaws"
                                                   style="cursor:pointer" />
                                        </th>
                                        <th>Title</th>
                                        <th style="width:100px">Law #</th>
                                        <th style="width:150px">Categories</th>
                                        <th style="width:100px">URL</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($this->getFilteredScrapedLaws() as $scrapedIndex => $law)
                                        <tr dusk="scraped-law-row-{{ $scrapedIndex }}">
                                            <td class="text-center">
                                                <input dusk="scraped-law-checkbox-{{ $scrapedIndex }}"
                                                       type="checkbox"
                                                       wire:click="toggleLawSelection('{{ $law['url'] }}')"
                                                       @if(in_array($law['url'], $selectedLawsToImport)) checked @endif
                                                       style="cursor:pointer" />
                                            </td>
                                            <td dusk="scraped-law-title-{{ $scrapedIndex }}">{{ $law['title'] }}</td>
                                            <td class="text-center">
                                                @if($law['law_number'])
                                                    <span class="chip" style="padding:4px 8px">#{{ $law['law_number'] }}</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if(isset($law['found_in_categories']) && is_array($law['found_in_categories']))
                                                    <span class="text-xs text-muted">{{ implode(', ', $law['found_in_categories']) }}</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <a href="{{ $law['url'] }}"
                                                   target="_blank"
                                                   class="chip info"
                                                   style="padding:4px 8px; text-decoration:none; cursor:pointer">
                                                    🔗 View
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td dusk="no-scraped-laws-message" colspan="5" class="text-center text-muted" style="padding:40px 20px">
                                                No laws match your filter.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        {{-- Import Progress --}}
                        @if($isImporting)
                            <div dusk="progress-bar" class="seg" style="margin-top:16px">
                                <div class="text-sm mb-2">
                                    <strong>Importing laws...</strong>
                                    <span dusk="progress-text" class="text-muted">{{ $importProgress }} / {{ $importTotal }}</span>
                                </div>
                                <div style="background:#0b1220; border:1px solid var(--border); border-radius:8px; overflow:hidden; height:24px; margin-bottom:8px">
                                    <div dusk="progress-bar-fill" style="background:linear-gradient(90deg, var(--success), #16a34a); height:100%; width:{{ $importTotal > 0 ? ($importProgress / $importTotal * 100) : 0 }}%; transition:width 0.3s ease"></div>
                                </div>
                                <div class="text-xs text-muted">
                                    Currently importing: <strong dusk="currently-importing">{{ $currentlyImporting }}</strong>
                                </div>
                            </div>
                        @endif

                        <div class="form-actions" style="border-top:none; margin-top:16px; padding-top:0">
                            <button dusk="cancel-import-button"
                                    @click="open=false"
                                    class="btn btn-secondary"
                                    wire:loading.attr="disabled"
                                    wire:target="importSelectedLaws"
                                    :disabled="$wire.isImporting">
                                Cancel
                            </button>

                            <button dusk="import-button"
                                    wire:click="importSelectedLaws"
                                    class="btn btn-success"
                                    wire:loading.attr="disabled"
                                    wire:target="importSelectedLaws"
                                    :disabled="@js(empty($selectedLawsToImport)) || $wire.isImporting">
                                <span wire:loading.remove wire:target="importSelectedLaws">
                                    <span class="btn-icon">📥</span>
                                    Import & Scrape {{ count($selectedLawsToImport) }} Selected Law(s)
                                </span>
                                <span wire:loading wire:target="importSelectedLaws" class="btn-loading">
                                    <svg class="btn-spinner" viewBox="0 0 24 24">
                                        <circle class="spinner-circle" cx="12" cy="12" r="10" stroke-width="3" fill="none"/>
                                    </svg>
                                    Importing...
                                </span>
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Toast Notifications --}}
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('scraping-complete', (event) => {
                alert('✅ ' + event.message);
            });
            Livewire.on('scraping-error', (event) => {
                alert('❌ ' + event.message);
            });
            Livewire.on('import-complete', (event) => {
                alert('✅ ' + event.message);
            });
            Livewire.on('import-error', (event) => {
                alert('❌ ' + event.message);
            });
        });
    </script>

    {{-- Real-time Progress Updates via Echo.js --}}
    <script>
        document.addEventListener('livewire:init', () => {
            // Listen for law import progress events on the law-imports channel
            if (window.Echo) {
                window.Echo.channel('law-imports')
                    .listen('.import.progress', (event) => {
                        console.log('Law import progress:', event);

                        // Dispatch Livewire event to update UI
                        Livewire.dispatch('import-progress-update', {
                            stage: event.stage,
                            current: event.current,
                            total: event.total,
                            data: event.data || {}
                        });

                        // Update progress UI elements directly for real-time feedback
                        if (event.stage === 'started') {
                            console.log('Import started:', event.total, 'items');
                        } else if (event.stage === 'processing_url') {
                            console.log('Processing URL:', event.data.url, `(${event.current}/${event.total})`);
                        } else if (event.stage === 'url_completed') {
                            console.log('Completed URL:', event.data.url, '- Articles:', event.data.articles);
                        } else if (event.stage === 'completed') {
                            console.log('Import completed:', event.data.processed, 'URLs,', event.data.articles, 'articles,', event.data.inserted, 'inserted');
                        }
                    });
            } else {
                console.warn('Echo is not available. Real-time progress updates disabled.');
            }
        });
    </script>

    {{-- Comprehensive Component Styling --}}
    <style>
        /* ==================== ROOT & CONTAINER ==================== */
        .ingested-laws-manager {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --success: #10b981;
            --success-dark: #059669;
            --error: #ef4444;
            --error-dark: #dc2626;
            --info: #0ea5e9;
            --info-dark: #0284c7;
            --accent: #7dd3fc;
            --muted: #64748b;
            --border: rgba(255, 255, 255, 0.1);
            --bg-overlay: rgba(15, 23, 42, 0.7);
        }

        /* ==================== PAGE HEADER ==================== */
        .page-header {
            margin-bottom: 24px;
        }

        .header-content {
            padding: 20px 0;
        }

        .page-title {
            font-size: 32px;
            font-weight: 700;
            color: #e2e8f0;
            margin: 0 0 8px 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .title-icon {
            font-size: 36px;
        }

        .page-subtitle {
            font-size: 15px;
            color: var(--muted);
        }

        /* ==================== CONTROLS BAR ==================== */
        .controls-bar {
            display: flex;
            align-items: flex-end;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 24px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 12px;
            border: 1px solid var(--border);
        }

        .search-control {
            flex: 1;
            min-width: 300px;
        }

        .search-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--muted);
            margin-bottom: 8px;
        }

        .search-input-wrapper {
            position: relative;
        }

        .search-input {
            width: 100%;
            padding-right: 40px !important;
        }

        .loading-spinner {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        /* ==================== SPINNERS & LOADING ==================== */
        .spinner-icon, .btn-spinner, .btn-spinner-sm, .btn-spinner-xs, .sort-spinner, .loading-spinner-large {
            animation: spin 1s linear infinite;
        }

        .spinner-icon {
            width: 20px;
            height: 20px;
        }

        .btn-spinner {
            width: 16px;
            height: 16px;
            margin-right: 6px;
        }

        .btn-spinner-sm {
            width: 14px;
            height: 14px;
        }

        .btn-spinner-xs {
            width: 12px;
            height: 12px;
        }

        .sort-spinner {
            width: 14px;
            height: 14px;
        }

        .loading-spinner-large {
            width: 48px;
            height: 48px;
        }

        .spinner-circle {
            stroke: currentColor;
            stroke-linecap: round;
            stroke-dasharray: 50;
            stroke-dashoffset: 25;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .btn-loading {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* ==================== BUTTONS ==================== */
        .btn-action {
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 600;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-color: var(--primary);
        }

        .btn-primary:hover:not(:disabled) {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success) 0%, var(--success-dark) 100%);
            border-color: var(--success);
        }

        .btn-success:hover:not(:disabled) {
            background: linear-gradient(135deg, var(--success-dark) 0%, var(--success) 100%);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-info {
            background: linear-gradient(135deg, var(--info) 0%, var(--info-dark) 100%);
            border-color: var(--info);
        }

        .btn-info:hover:not(:disabled) {
            background: linear-gradient(135deg, var(--info-dark) 0%, var(--info) 100%);
            box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);
        }

        .btn-secondary {
            background: rgba(100, 116, 139, 0.1);
            border-color: rgba(100, 116, 139, 0.3);
            color: #94a3b8;
        }

        .btn-secondary:hover:not(:disabled) {
            background: rgba(100, 116, 139, 0.2);
            border-color: rgba(100, 116, 139, 0.5);
        }

        .btn-sm {
            font-size: 12px;
            padding: 6px 12px;
        }

        .btn-xs {
            font-size: 11px;
            padding: 5px 10px;
        }

        .btn-edit {
            background: rgba(100, 116, 139, 0.1);
            border-color: rgba(100, 116, 139, 0.3);
        }

        .btn-edit:hover:not(:disabled) {
            background: rgba(59, 130, 246, 0.15);
            border-color: rgba(59, 130, 246, 0.3);
            color: #60a5fa;
        }

        .btn-delete {
            background: rgba(239, 68, 68, 0.1);
            border-color: rgba(239, 68, 68, 0.3);
            color: #f87171;
        }

        .btn-delete:hover:not(:disabled) {
            background: linear-gradient(135deg, var(--error) 0%, var(--error-dark) 100%);
            border-color: var(--error);
            color: #fff;
        }

        .btn-close {
            background: rgba(239, 68, 68, 0.1);
            border-color: rgba(239, 68, 68, 0.3);
            color: #f87171;
            padding: 8px 16px;
        }

        .btn-close:hover {
            background: var(--error);
            border-color: var(--error);
            color: #fff;
        }

        .btn-icon {
            margin-right: 6px;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* ==================== GRID & PANELS ==================== */
        .main-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-top: 24px;
        }

        @media (max-width: 1280px) {
            .main-grid {
                grid-template-columns: 1fr;
            }
        }

        .panel-header {
            margin-bottom: 16px;
        }

        .panel-header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .panel-title {
            font-size: 16px;
            font-weight: 600;
            color: #e2e8f0;
        }

        /* ==================== SORT CONTROLS ==================== */
        .sort-controls {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .sort-label {
            font-size: 12px;
            color: var(--muted);
            margin-right: 4px;
        }

        .sort-chip {
            padding: 6px 12px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .sort-chip.active {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-color: var(--primary);
            color: #fff;
        }

        .sort-chip:hover:not(:disabled) {
            background: rgba(59, 130, 246, 0.15);
            border-color: rgba(59, 130, 246, 0.3);
            transform: translateY(-1px);
        }

        .sort-loading {
            display: inline-flex;
            align-items: center;
        }

        /* ==================== LAWS LIST ==================== */
        .laws-list-wrapper {
            position: relative;
            min-height: 200px;
        }

        .laws-list-wrapper.loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .loading-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent);
        }

        .laws-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .law-item {
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .law-item:hover {
            transform: translateX(4px);
            border-color: rgba(59, 130, 246, 0.3);
        }

        .law-item.active {
            border-color: var(--primary);
            background: rgba(59, 130, 246, 0.1);
            box-shadow: 0 0 0 1px var(--primary);
        }

        .law-item-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }

        .law-select-btn {
            flex: 1;
            text-align: left;
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            padding: 0;
            min-width: 0;
        }

        .law-title {
            font-weight: 600;
            font-size: 15px;
            color: #e2e8f0;
            margin-bottom: 6px;
        }

        .law-meta {
            font-size: 12px;
            color: var(--muted);
        }

        .doc-id, .hash-text, .id-text {
            font-family: monospace;
            font-size: 11px;
        }

        .meta-separator {
            margin: 0 6px;
            color: rgba(255, 255, 255, 0.2);
        }

        .law-actions {
            display: flex;
            gap: 8px;
            flex-shrink: 0;
        }

        /* ==================== EMPTY STATES ==================== */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-icon {
            font-size: 48px;
            margin-bottom: 12px;
            opacity: 0.5;
        }

        .empty-text {
            color: var(--muted);
            font-size: 14px;
        }

        .empty-selection {
            text-align: center;
            padding: 80px 20px;
        }

        .empty-selection-icon {
            font-size: 64px;
            margin-bottom: 16px;
            opacity: 0.4;
        }

        .empty-selection-text {
            color: var(--muted);
            font-size: 15px;
        }

        .empty-state-cell {
            text-align: center;
            padding: 40px 20px;
        }

        .empty-state-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }

        /* ==================== DETAILS PANEL ==================== */
        .details-header {
            margin-bottom: 0;
        }

        .details-header-content {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 20px;
        }

        .details-info {
            flex: 1;
            min-width: 0;
        }

        .details-title {
            font-size: 20px;
            font-weight: 600;
            margin: 0 0 8px 0;
            color: #e2e8f0;
        }

        .details-meta {
            font-size: 13px;
            color: var(--muted);
        }

        .btn-edit-selected {
            flex-shrink: 0;
        }

        /* ==================== TABS ==================== */
        .tabs-container {
            display: flex;
            gap: 8px;
            border-bottom: 2px solid var(--border);
            margin-bottom: 20px;
        }

        .tab {
            position: relative;
            padding: 12px 20px;
            background: none;
            border: none;
            color: var(--muted);
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 500;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
        }

        .tab:hover:not(:disabled) {
            color: #e2e8f0;
        }

        .tab.active {
            color: var(--accent);
            border-bottom-color: var(--accent);
        }

        .tab-icon {
            font-size: 16px;
        }

        .tab-indicator {
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--accent);
            transform: scaleX(0);
            transition: transform 0.2s ease;
        }

        .tab.active .tab-indicator {
            transform: scaleX(1);
        }

        /* ==================== TAB CONTENT ==================== */
        .tab-content {
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .tab-content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .tab-description {
            font-size: 14px;
            color: var(--muted);
        }

        .btn-create {
            font-size: 13px;
            padding: 8px 16px;
        }

        /* ==================== TABLES ==================== */
        .table-wrapper {
            position: relative;
        }

        .table-wrapper.loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .table-container {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid var(--border);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table thead {
            background: rgba(255, 255, 255, 0.03);
        }

        .data-table th {
            padding: 12px 16px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border);
        }

        .data-table tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background-color 0.2s ease;
        }

        .data-table tbody tr:last-child {
            border-bottom: none;
        }

        .data-table tbody tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }

        .data-table td {
            padding: 14px 16px;
            font-size: 13px;
        }

        .id-cell, .hash-cell {
            font-family: monospace;
            font-size: 11px;
            color: var(--muted);
        }

        .title-cell {
            font-weight: 500;
            color: #e2e8f0;
        }

        .date-cell, .size-cell {
            color: var(--muted);
            font-size: 12px;
        }

        .path-cell {
            max-width: 250px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .actions-cell {
            text-align: center;
        }

        .action-buttons-group {
            display: flex;
            gap: 6px;
            justify-content: center;
        }

        /* ==================== CHIPS ==================== */
        .chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            border: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.05);
            transition: all 0.2s ease;
        }

        .chip-default {
            background: rgba(100, 116, 139, 0.1);
            border-color: rgba(100, 116, 139, 0.3);
            color: #94a3b8;
        }

        .chip-info {
            background: rgba(14, 165, 233, 0.1);
            border-color: rgba(14, 165, 233, 0.3);
            color: #7dd3fc;
        }

        .chip-success {
            background: rgba(16, 185, 129, 0.1);
            border-color: rgba(16, 185, 129, 0.3);
            color: #6ee7b7;
        }

        .chip-error {
            background: rgba(239, 68, 68, 0.1);
            border-color: rgba(239, 68, 68, 0.3);
            color: #f87171;
        }

        .chip-icon {
            font-size: 14px;
        }

        /* ==================== MODALS ==================== */
        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: var(--bg-overlay);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 50;
            padding: 20px;
        }

        .modern-modal {
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        }

        .modal-wide {
            max-width: 900px;
        }

        .modal-xlarge {
            max-width: 1200px;
        }

        .modal-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 20px;
            font-weight: 600;
            color: #e2e8f0;
            margin: 0;
        }

        .modal-icon {
            font-size: 24px;
        }

        .modal-body {
            position: relative;
        }

        .form-loading {
            position: relative;
        }

        .form-loading-overlay {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.9);
            backdrop-filter: blur(2px);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 10;
            border-radius: 8px;
            gap: 16px;
        }

        .loading-text {
            font-size: 14px;
            font-weight: 600;
            color: var(--accent);
        }

        /* ==================== PAGINATION ==================== */
        .pagination {
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
            padding: 16px 0;
        }

        .pagination nav {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .pagination nav span,
        .pagination nav a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s ease;
            border: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.03);
            color: #94a3b8;
        }

        .pagination nav a:hover {
            background: rgba(59, 130, 246, 0.15);
            border-color: rgba(59, 130, 246, 0.3);
            color: #60a5fa;
            transform: translateY(-1px);
        }

        .pagination nav span[aria-current="page"] {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-color: var(--primary);
            color: #ffffff;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
        }

        .pagination nav span[aria-disabled="true"] {
            opacity: 0.3;
            cursor: not-allowed;
            background: rgba(255, 255, 255, 0.02);
        }

        .pagination nav a[rel="prev"],
        .pagination nav a[rel="next"] {
            font-weight: 600;
            padding: 6px 14px;
        }

        /* ==================== MOBILE RESPONSIVENESS ==================== */
        @media (max-width: 768px) {
            .page-title {
                font-size: 24px;
            }

            .title-icon {
                font-size: 28px;
            }

            .controls-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .search-control {
                min-width: auto;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn-action {
                width: 100%;
            }

            .sort-controls {
                flex-direction: column;
                align-items: stretch;
            }

            .sort-chip {
                width: 100%;
                text-align: center;
            }

            .law-item-content {
                flex-direction: column;
                align-items: stretch;
            }

            .law-actions {
                width: 100%;
                justify-content: stretch;
            }

            .law-actions button {
                flex: 1;
            }

            .details-header-content {
                flex-direction: column;
            }

            .btn-edit-selected {
                width: 100%;
            }

            .tab-content-header {
                flex-direction: column;
                align-items: stretch;
            }

            .btn-create {
                width: 100%;
            }

            .table-container {
                font-size: 12px;
            }

            .data-table th,
            .data-table td {
                padding: 8px 10px;
            }

            .action-buttons-group {
                flex-direction: column;
            }

            .modern-modal {
                max-width: 100%;
                margin: 0;
            }
        }

        @media (max-width: 480px) {
            .page-title {
                font-size: 20px;
            }

            .controls-bar {
                padding: 16px;
            }

            .panel-header-content {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</div>
