@extends('errors.layout')
@section('title', 'Something went wrong')
@section('code', 'Error 500')
@section('art', 'fix')
@section('heading', 'Something went wrong on our side')
@section('message', "It's not you. We've noted the problem. Please try again in a moment. Pages you've already opened keep working offline.")
@section('actions')
    <button type="button" class="btn btn--primary" onclick="location.reload()">Try again</button>
@endsection
