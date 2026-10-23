<?php

namespace App\Notifications;

use App\Enums\WaitlistStatus;
use App\Models\WaitlistEntry;
use App\Support\HotelSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class WaitlistRoomAvailable extends Notification implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public WaitlistEntry $entry)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /** Skips the email if the offer is no longer open when it is finally sent (for example after a failed-job retry). */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        $entry = $this->entry->fresh();

        return $entry !== null
            && $entry->status === WaitlistStatus::Notified
            && $entry->offer_expires_at !== null
            && $entry->offer_expires_at->isFuture();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $entry = $this->entry->loadMissing(['guest', 'roomType']);
        $hotel = HotelSettings::get('hotel_name');

        return (new MailMessage)
            ->from(config('mail.from.address'), $hotel)
            ->subject("A room is now available at {$hotel}")
            ->markdown('mail.waitlist.room-available', [
                'hotelName' => $hotel,
                'guestName' => $entry->guest->full_name,
                'roomType' => $entry->roomType->name,
                'checkIn' => $entry->check_in->format('D d M Y'),
                'checkOut' => $entry->check_out->format('D d M Y'),
                'nights' => (int) round($entry->check_in->diffInDays($entry->check_out)),
                'adults' => $entry->adults,
                'children' => $entry->children,
                'holdUntil' => $entry->offer_expires_at?->format('D d M Y, H:i') ?? 'further notice',
                'phone' => HotelSettings::get('hotel_phone'),
                'email' => HotelSettings::get('hotel_email'),
            ]);
    }
}