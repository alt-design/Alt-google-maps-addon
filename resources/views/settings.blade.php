@extends('statamic::layout')

@section('title', 'Google Maps Settings')

@section('content')
    <google-maps-settings
        :initial-defaults='@json($defaults)'
        save-url="{{ route('statamic.cp.google-maps.settings.store') }}"
    ></google-maps-settings>
@endsection
