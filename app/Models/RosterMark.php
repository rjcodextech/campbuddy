<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One hand-set role on the attendee list (App\Support\RosterMarks). */
class RosterMark extends Model
{
    protected $fillable = ['event_id', 'person_key', 'role', 'action', 'set_by'];
}
