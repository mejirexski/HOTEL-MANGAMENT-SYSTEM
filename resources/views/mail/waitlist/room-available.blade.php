<x-mail::message>
# A room is now available

Hello {{ $guestName }},

Good news. A **{{ $roomType }}** room has become available for the dates you asked about.

<x-mail::panel>
**Room type:** {{ $roomType }}<br>
**Check-in:** {{ $checkIn }}<br>
**Check-out:** {{ $checkOut }} ({{ $nights }} {{ \Illuminate\Support\Str::plural('night', $nights) }})<br>
**Guests:** {{ $adults }} {{ \Illuminate\Support\Str::plural('adult', $adults) }}@if ($children > 0), {{ $children }} {{ \Illuminate\Support\Str::plural('child', $children) }}@endif
</x-mail::panel>

We are holding this room for you until **{{ $holdUntil }}**. To book it, please contact us before then.

@if ($phone)
**Phone:** {{ $phone }}<br>
@endif
@if ($email)
**Email:** {{ $email }}
@endif

If you no longer need the room, you can ignore this message.

Regards,<br>
{{ $hotelName }}
</x-mail::message>