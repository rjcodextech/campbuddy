<?php

namespace App\Http\Requests\Manager;

use App\Http\Middleware\EnsureEventManager;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Base for every manager form that changes one event: allowed only for an
 * event the manager is assigned to. Anything else answers "not found" — as if
 * the event didn't exist — so the response says nothing about other events.
 */
abstract class ManagerEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user(EnsureEventManager::GUARD)
            ?->events()
            ->whereKey($this->route('eventId'))
            ->exists();
    }

    protected function failedAuthorization(): never
    {
        throw new NotFoundHttpException;
    }
}
