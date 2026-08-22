@php($token = config('services.cryptomus.site_verification'))
@if(filled($token))
<meta name="cryptomus" content="{{ e($token) }}" />
@endif
