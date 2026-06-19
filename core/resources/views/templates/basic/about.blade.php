@extends('Template::layouts.master')

@section('content')
    <div class="bg-white">
        @if (@$sections->secs != null)
            @foreach (json_decode($sections->secs) as $sec)
                @include('Template::sections.' . $sec)
            @endforeach
        @endif
    </div>
@endsection

@push('style')
    <link rel="stylesheet" href="{{ asset($activeTemplateTrue . 'css/about-us-page.css') }}">
@endpush
