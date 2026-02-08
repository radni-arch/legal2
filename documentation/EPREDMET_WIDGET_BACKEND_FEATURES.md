# EpredmetWidget & EKOM Backend Features Without UI

This document lists EKOM (Croatian E-Court System) backend features that currently have no user interface in the EpredmetWidget or elsewhere in the application.

## Overview

The `EpredmetWidget` currently provides read-only case (predmet) lookup via GraphQL, displaying case details, hearings, parties, and documents. However, the underlying `EkomService` provides extensive functionality for managing EKOM data that is not exposed through any UI.

---

## 1. Data Synchronization Operations

### Feature: Sync Predmeti (Cases)
- **Backend**: `App\Services\EkomService::syncPredmeti()`
- **Command**: `php artisan ekom:sync-predmeti`
- **Missing UI**: No interface to trigger case synchronization
- **Priority**: **High**
- **Functionality**:
  - Fetches cases from EKOM API with filters
  - Supports pagination (configurable page size)
  - Stores cases in `ekom_predmeti` table
  - Returns count of saved records
- **Suggested Implementation**:
  - Add "Sync Cases" button to EpredmetWidget header
  - Modal with filters: date range, court, status
  - Progress bar showing sync status
  - Display sync statistics (saved, failed, pages processed)

### Feature: Sync Podnesci (Submissions)
- **Backend**: `App\Services\EkomService::syncPodnesci()`
- **Command**: `php artisan ekom:sync-podnesci`
- **Missing UI**: No interface to trigger submission synchronization
- **Priority**: **High**
- **Functionality**:
  - Fetches submissions from EKOM API
  - Stores in `ekom_podnesci` table via `EkomPodnesakRepository`
  - Supports filtering and pagination
- **Suggested Implementation**:
  - Add "Sync Submissions" tab/section in widget
  - Filter by predmet ID, date range, type
  - Display submission list with status

### Feature: Sync Otpravci (Dispatches)
- **Backend**: `App\Services\EkomService::syncOtpravci()`
- **Command**: `php artisan ekom:sync-otpravci`
- **Missing UI**: No interface to trigger dispatch synchronization
- **Priority**: **High**
- **Functionality**:
  - Fetches dispatches from EKOM API
  - Stores in `ekom_otpravci` table via `EkomOtpravakRepository`
  - Tracks delivery status
- **Suggested Implementation**:
  - Add "Sync Dispatches" section
  - Show dispatch status (sent, received, confirmed)
  - Integration with document display

---

## 2. Do Not Disturb (DND) Management

### Feature: Turn On/Off DND for Specific Case
- **Backend**:
  - `App\Services\EkomService::turnOnDndPredmet($predmetId)`
  - `App\Services\EkomService::turnOffDndPredmet($predmetId)`
- **Missing UI**: No UI controls for DND management
- **Priority**: **Medium**
- **Functionality**:
  - Prevents EKOM notifications for specific cases
  - Useful for managing notification overload
- **Suggested Implementation**:
  - Add toggle button in case details view
  - DND icon indicator when enabled
  - Tooltip explaining DND functionality

### Feature: General DND (All Cases)
- **Backend**:
  - `App\Services\EkomService::turnOnGeneralDnd()`
  - `App\Services\EkomService::turnOffGeneralDnd()`
- **Missing UI**: No global DND toggle
- **Priority**: **Medium**
- **Functionality**:
  - Disables all EKOM notifications system-wide
  - Useful during vacations or focused work periods
- **Suggested Implementation**:
  - Add DND toggle in user settings/profile
  - Dashboard notification banner when DND is active
  - Schedule DND (e.g., "Enable from X to Y")

### Feature: Turn Off DND for All Cases
- **Backend**: `App\Services\EkomService::dndAllOff()`
- **Missing UI**: No batch DND management
- **Priority**: **Low**
- **Functionality**:
  - Bulk disables DND for all predmeti
- **Suggested Implementation**:
  - Batch actions dropdown in case list
  - Confirmation dialog before bulk operation

---

## 3. Document Management

### Feature: Download Case Documents
- **Backend**: `App\Services\EkomService::download($type, $params, $saveToPath)`
- **Missing UI**: No document download interface in widget
- **Priority**: **High**
- **Document Types Supported**:
  1. `predmet-dokumenti` - Case documents
  2. `predmet-dostavnica` - Case delivery note
  3. `otpravak-potvrda` - Dispatch receipt confirmation
  4. `otpravak-dokumenti` - Dispatch documents
  5. `podnesak-obavijest` - Submission notification
  6. `podnesak-nalog` - Submission payment order
  7. `podnesak-dokaz` - Submission payment proof
- **Suggested Implementation**:
  - Add "Documents" tab in case details
  - List all available documents with types
  - Download button for each document
  - Batch download selected documents
  - Integration with existing document viewer

---

## 4. Submission Operations

### Feature: Confirm Receipt of Dispatch (Otpravak)
- **Backend**: `App\Services\EkomService::potvrdiPrimitakOtpravka($id)`
- **Missing UI**: No UI to confirm dispatch receipt
- **Priority**: **Medium**
- **Functionality**:
  - Confirms receipt of court dispatch to EKOM
  - Legally required acknowledgment
  - Updates dispatch status
- **Suggested Implementation**:
  - "Confirm Receipt" button on unconfirmed dispatches
  - Confirmation timestamp display
  - Automatic receipt after viewing document
  - Notification for pending confirmations

### Feature: Create New Submission (Podnesak)
- **Backend**: `App\Services\EkomService::createPodnesak($payload, $filePaths)`
- **Missing UI**: No submission creation interface
- **Priority**: **Very High**
- **Functionality**:
  - Creates new submissions to court
  - Attaches files (PDF, Word, images)
  - Validates submission requirements
  - Returns submission ID and status
- **Suggested Implementation**:
  - "New Submission" button in case view
  - Multi-step form wizard:
    1. Select submission type
    2. Fill required fields (court, case number, description)
    3. Attach documents (drag & drop, file picker)
    4. Review and submit
  - Save draft functionality
  - Template library for common submissions
  - Track submission status after creation

---

## 5. Statistics & Monitoring

### Feature: EKOM Sync Statistics
- **Backend**: Logging in sync methods (via `Log::info`)
- **Missing UI**: No dashboard for sync statistics
- **Priority**: **Medium**
- **Data Available**:
  - Records saved/failed per sync
  - Sync duration (ms)
  - Pages processed
  - Correlation IDs for tracking
- **Suggested Implementation**:
  - "Sync History" tab in settings
  - Chart showing sync trends over time
  - Alert on high failure rate
  - Export sync logs to CSV

---

## 6. Advanced Search & Filtering

### Feature: Court/Case Type Filtering
- **Backend**: Filters supported in `listPredmeti()`, `listPodnesci()`, `listOtpravci()`
- **Missing UI**: Basic widget only supports sud ID and oznakaBroj
- **Priority**: **Medium**
- **Filter Options Available (from EKOM API)**:
  - Court ID (sud)
  - Case number (oznaka/broj)
  - Status (active, archived, on appeal)
  - Date ranges
  - Case type (criminal, civil, administrative)
  - Judge assigned
- **Suggested Implementation**:
  - Advanced search panel (collapsible)
  - Filter chips showing active filters
  - Save filter presets
  - Export filtered results

---

## 7. Configuration Management

### Feature: EKOM Connection Settings
- **Backend**: `config/ekom.php`, `EkomApiClientInterface`
- **Missing UI**: No UI for EKOM configuration
- **Priority**: **Low**
- **Settings**:
  - API endpoint URL
  - Authentication tokens
  - Timeout settings
  - Page size defaults
  - Rate limiting
- **Suggested Implementation**:
  - Admin settings page for EKOM
  - Test connection button
  - View API quota/usage
  - Configure retry logic

---

## 8. Batch Operations

### Feature: Bulk Case Operations
- **Backend**: Repository upsert methods support batch operations
- **Missing UI**: No bulk action interface
- **Priority**: **Medium**
- **Potential Operations**:
  - Bulk DND enable/disable
  - Batch document download
  - Mass case tagging/categorization
  - Bulk export to PDF/Excel
- **Suggested Implementation**:
  - Checkbox selection in case list
  - "Bulk Actions" dropdown
  - Progress bar for long operations
  - Undo/rollback capability

---

## 9. Real-time Notifications

### Feature: EKOM Change Notifications
- **Backend**: Webhook support (if EKOM API provides)
- **Missing UI**: No real-time updates
- **Priority**: **Low**
- **Functionality**:
  - Push notifications for new dispatches
  - Case status changes
  - Hearing schedule updates
- **Suggested Implementation**:
  - WebSocket connection to Laravel Echo
  - Toast notifications
  - Notification center/inbox
  - Email/SMS integration

---

## 10. Reporting & Analytics

### Feature: EKOM Analytics Dashboard
- **Backend**: Data in `ekom_predmeti`, `ekom_podnesci`, `ekom_otpravci` tables
- **Missing UI**: No analytics/reporting
- **Priority**: **Medium**
- **Metrics Available**:
  - Case count by type/status
  - Average case duration
  - Submission success rate
  - Court workload distribution
  - Monthly trends
- **Suggested Implementation**:
  - Analytics dashboard page
  - Charts: bar, line, pie
  - Date range filters
  - Export to PDF/Excel
  - Comparison with previous periods

---

## Summary Table

| Feature | Backend | Command | Priority | Complexity |
|---------|---------|---------|----------|------------|
| Sync Predmeti | `syncPredmeti()` | `ekom:sync-predmeti` | High | Medium |
| Sync Podnesci | `syncPodnesci()` | `ekom:sync-podnesci` | High | Medium |
| Sync Otpravci | `syncOtpravci()` | `ekom:sync-otpravci` | High | Medium |
| Case DND Toggle | `turnOnDndPredmet()` | - | Medium | Low |
| General DND | `turnOnGeneralDnd()` | - | Medium | Low |
| Batch DND Off | `dndAllOff()` | - | Low | Low |
| Document Download | `download()` | - | High | Medium |
| Confirm Receipt | `potvrdiPrimitakOtpravka()` | - | Medium | Low |
| Create Submission | `createPodnesak()` | - | **Very High** | High |
| Sync Statistics | Logging | - | Medium | Low |
| Advanced Filters | API params | - | Medium | Medium |
| EKOM Settings | Config | - | Low | Low |
| Bulk Operations | Repositories | - | Medium | Medium |
| Real-time Notifications | Webhooks | - | Low | High |
| Analytics Dashboard | Database | - | Medium | Medium |

---

## Implementation Recommendations

### Phase 1: Essential Features (High Priority)
1. **Create Submission Interface** - Most important for lawyer workflow
2. **Document Download UI** - Essential for case management
3. **Sync Operations UI** - Keep data up-to-date

### Phase 2: Quality of Life (Medium Priority)
4. **DND Management** - Improve notification control
5. **Advanced Search** - Better case discovery
6. **Confirm Receipt UI** - Legal compliance

### Phase 3: Analytics & Automation (Low Priority)
7. **Analytics Dashboard** - Business intelligence
8. **Real-time Notifications** - Proactive alerts
9. **EKOM Settings** - Advanced configuration

---

## Technical Notes

- All EKOM operations use correlation IDs for request tracking
- Extensive logging at INFO and ERROR levels
- Error handling via `EkomApiException`
- Repository pattern for data persistence
- Transaction support for batch operations
- Validation at service and repository layers

---

## Related Files

- **Service**: `app/Services/EkomService.php`
- **Interface**: `app/Contracts/External/EkomServiceInterface.php`
- **Client**: `app/Clients/Ekom/EkomApiClientInterface.php`
- **Repositories**:
  - `app/Repositories/EkomPredmetRepository.php`
  - `app/Repositories/EkomPodnesakRepository.php`
  - `app/Repositories/EkomOtpravakRepository.php`
- **Models**:
  - `app/Models/EkomPredmet.php`
  - `app/Models/EkomPodnesak.php`
  - `app/Models/EkomOtpravak.php`
- **Config**: `config/ekom.php`
- **Widget**: `app/Http/Livewire/EpredmetWidget.php`
- **View**: `resources/views/livewire/epredmet-widget.blade.php`
