<?php

namespace App\Models;

use App\Support\Bookings\BookingException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Guest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 
        'full_name', 
        'phone', 
        'email', 
        'address',
        'id_type', 
        'id_number', 
        'nationality', 
        'notes',
    ];

    // ID details are sensitive: never include them in JSON or array output by accident.
    protected $hidden = ['id_number'];

    protected static function booted(): void
    {
        static::saving(function (Guest $guest) {
            $guest->phone_digits = self::digitsOf($guest->phone) ?: null;

            $email = $guest->email !== null ? strtolower(trim($guest->email)) : '';
            $guest->email = $email !== '' ? $email : null;
        });

        static::deleting(function (Guest $guest) {
            if ($guest->bookings()->currentOrFuture()->exists() || $guest->waitlistEntries()->active()->exists()) {
                throw new BookingException(
                    'This guest has current or future bookings or is on the waitlist and cannot be deleted.'
                );
            }
        });
    }

    protected function casts(): array
    {
        return [
            'id_number' => 'encrypted',
        ];
    }

    /** The last 10 digits of a phone number, so +234 806 123 4567 and 08061234567 match. */
    public static function digitsOf(?string $phone): string
    {
        $digits = (string) preg_replace('/\D+/', '', (string) $phone);

        return $digits === '' ? '' : substr($digits, -10);
    }

    /** The ID number with all but the last four characters hidden. */
    public function maskedIdNumber(): ?string
    {
        $id = $this->id_number;

        if ($id === null || $id === '') {
            return null;
        }

        return mb_strlen($id) <= 4
            ? str_repeat('•', mb_strlen($id))
            : '••••' . mb_substr($id, -4);
    }

    /** Search by name, email, phone in any format, or the reference of one of their bookings. */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        if ($term === '') {
            return $query;
        }

        $like = '%' . $term . '%';
        $digits = (string) preg_replace('/\D+/', '', $term);

        return $query->where(function (Builder $w) use ($like, $digits) {
            $w->where('full_name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhereHas('bookings', fn (Builder $b) => $b->where('reference', 'like', $like));

            if (strlen($digits) >= 4) {
                $w->orWhere('phone_digits', 'like', '%' . substr($digits, -10) . '%');
            }
        });
    }

    /** Guests that probably are the same person: same phone number or same email. */
    public static function findDuplicates(?string $phone, ?string $email, ?int $ignoreId = null): Collection
    {
        $digits = self::digitsOf($phone);
        $email = $email !== null ? strtolower(trim($email)) : '';

        if ($digits === '' && $email === '') {
            return new Collection();
        }

        return static::query()
            ->when($ignoreId, fn (Builder $q) => $q->whereKeyNot($ignoreId))
            ->where(function (Builder $w) use ($digits, $email) {
                if ($digits !== '') {
                    $w->orWhere('phone_digits', $digits);
                }
                if ($email !== '') {
                    $w->orWhere('email', $email);
                }
            })
            ->orderBy('full_name')
            ->limit(5)
            ->get(['id', 'full_name', 'phone', 'email']);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function waitlistEntries(): HasMany
    {
        return $this->hasMany(WaitlistEntry::class);
    }
}