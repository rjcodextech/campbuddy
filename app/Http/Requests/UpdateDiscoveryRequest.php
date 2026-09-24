<?php

namespace App\Http\Requests;

/** Same fields and rules as joining — an update replaces the whole shared profile. */
class UpdateDiscoveryRequest extends StoreDiscoveryRequest {}
