@props(['header' => null, 'title' => null, 'hideSidebar' => false])

@php
    $client = \Illuminate\Support\Facades\Auth::guard('client')->user();
    $contactTelegram = ltrim((string) config('contact.telegram', ''), '@');
@endphp

<x-client-smm-layout :title="$title" :client="$client" :contactTelegram="$contactTelegram" :hide-sidebar="$hideSidebar">
    @isset($header)
        <div class="smm-client-page-head">
            {{ $header }}
        </div>
    @endisset

    {{ $slot }}
</x-client-smm-layout>
