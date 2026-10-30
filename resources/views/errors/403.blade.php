@extends('errors.layout')
@section('code', '403')
@section('title', 'Access denied')
@section('message'){{ ($exception ?? null)?->getMessage() && ! in_array($exception->getMessage(), ['Forbidden', 'This action is unauthorized.']) ? $exception->getMessage() : 'Your role does not allow this action. Viewers have read-only access; transfers, maintenance and disposals need an Asset Manager, and finalising or unlocking depreciation needs a Company Admin.' }}@endsection
@section('hint')If you think this is a mistake, ask your Company Admin to review your role.@endsection
