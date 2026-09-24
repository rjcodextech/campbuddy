@extends('errors.layout')
@section('title', 'Back soon')
@section('code', 'Maintenance')
@section('art', 'fix')
@section('heading', "We're making CampBuddy better")
@section('message', "We'll be back in a few minutes. Pages you've already opened keep working offline in the meantime.")
@section('actions')
    <button type="button" class="btn btn--primary" onclick="location.reload()">Try again</button>
@endsection
