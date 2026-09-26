<?php

namespace App\Http\Requests\Manager;

/**
 * Adding or changing a checklist item on one of the manager's events — the
 * same rules as the admin's quest form.
 */
class ManagedQuestRequest extends ManagerEventRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }
}
