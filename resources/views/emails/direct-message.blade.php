<x-mail::message>
@if ($recipientName)
Dear {{ $recipientName }},
@endif

{!! nl2br(e($body)) !!}

Regards,<br>
**{{ $sender->name }}**<br>
{{ config('app.name') }}

<x-mail::subcopy>
This message was sent by {{ $sender->name }} through {{ config('app.name') }}. Replying will reach {{ $sender->email }} directly.
</x-mail::subcopy>
</x-mail::message>
