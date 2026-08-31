<x-mail::message>
# New enquiry from the website

@if ($senderName)
**Name:** {{ $senderName }}

@endif
**Phone:** {{ $senderPhone }}

@if ($source)
**Came through:** {{ $source }}

@endif
@if ($commercialRegister)
**Commercial register:** {{ $commercialRegister }}

@endif
{{ $body }}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
