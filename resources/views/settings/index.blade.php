@extends('statamic::layout')

@section('title', 'AI Gateway Settings')

@section('content')
    <gateway-settings
        :settings='@json($settings)'
        masked-token="{{ $maskedToken }}"
        :log-channels='@json($logChannels)'
        update-url="{{ cp_route('ai-gateway.settings.update') }}"
        resources-url="{{ cp_route('ai-gateway.settings.resources') }}"
        csrf-token="{{ csrf_token() }}"
        success-message="{{ session('success') }}"
        :errors='@json($errors->toArray())'
    ></gateway-settings>
@endsection
