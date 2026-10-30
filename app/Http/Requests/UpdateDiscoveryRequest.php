<?php

namespace App\Http\Requests;

/** Same fields and rules as joining — an update replaces the whole shared profile. */
class UpdateDiscoveryRequest extends StoreDiscoveryRequest
{
    /** The person agreed when they joined; an edit only changes what is shown, so no new tick is asked. */
    public function rules(): array
    {
        return ['consent' => ['sometimes', 'accepted']] + parent::rules();
    }
}
