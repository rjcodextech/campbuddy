@extends('errors.layout')
@section('title', 'Back soon')
@section('code', 'Maintenance')
@section('art', 'fix')
@section('heading', "CampBuddy is being updated")
@section('message', "We'll be back in a few minutes. Pages you've already opened keep working offline in the meantime.")
@section('actions')
    <button type="button" class="btn btn--primary" onclick="location.reload()">Try again</button>
@endsection
