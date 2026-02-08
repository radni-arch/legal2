<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ManageSystemRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only admin users can manage system
        return $this->user()?->is_admin ?? false;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:clear_cache,rebuild_index,sync_graph,restart_workers,purge_logs,backup_database'],
            'parameters' => ['nullable', 'array'],
            'parameters.force' => ['nullable', 'boolean'],
            'parameters.dry_run' => ['nullable', 'boolean'],
            'parameters.scope' => ['nullable', 'string', 'in:all,specific'],
            'parameters.entity_ids' => ['nullable', 'array'],
            'parameters.entity_ids.*' => ['string', 'max:255'],
            'confirm' => ['required', 'boolean', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => 'System action is required.',
            'action.in' => 'Action must be one of: clear_cache, rebuild_index, sync_graph, restart_workers, purge_logs, backup_database.',
            'confirm.required' => 'You must confirm this system action.',
            'confirm.accepted' => 'You must explicitly confirm this destructive action.',
        ];
    }
}
