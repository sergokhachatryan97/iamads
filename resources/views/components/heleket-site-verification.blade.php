@php($token = config('services.heleket.site_verification'))
@if(filled($token))
<meta name="heleket" content="{{ e($token) }}" />
@endif
