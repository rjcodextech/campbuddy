@extends('errors.layout')
@section('title', 'Page expired')
@section('code', 'Page expired')
@section('art', 'wait')
@section('heading', 'This page sat open a little too long')
@section('message', 'For your security, forms expire after a while. Go back, refresh the page, and try again.')
@section('actions')
    <button type="button" class="btn btn--primary" onclick="history.back()">Go back and retry</button>
@endsection
