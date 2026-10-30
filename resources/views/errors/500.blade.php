@extends('errors.layout')
@section('code', '500')
@section('title', 'Something went wrong')
@section('message')An unexpected error occurred on our side. It has been logged; please try again in a moment.@endsection
@section('hint')Nothing you entered has been partially saved – every posting runs in a database transaction.@endsection
