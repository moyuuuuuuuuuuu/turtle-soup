<?php

declare(strict_types=1);

namespace App\Donation\Formats;

use App\Donation\Models\Donation;
use App\Donation\Models\DonationChannel;
use Illuminate\Database\Eloquent\Collection;

final class DonationFormat
{
    /** @return array<string, mixed> */
    public static function publicPage(): array
    {
        /** @var Collection<int, DonationChannel> $channels */
        $channels = DonationChannel::query()->where('status', true)->orderByDesc('sort')->get();
        /** @var Collection<int, Donation> $donations */
        $donations = Donation::query()->where('status', true)->orderByDesc('sort')->orderByDesc('donated_at')->limit(20)->get();

        return [
            'channels' => $channels->map(static fn (DonationChannel $channel): array => [
                'method' => (string) $channel->method,
                'name' => (string) $channel->name,
                'qr_code_url' => (string) $channel->qr_code_url,
            ])->all(),
            'recent_donations' => $donations->map(static fn (Donation $donation): array => [
                'id' => (string) $donation->public_id,
                'donor_name' => (string) $donation->donor_name,
                'amount' => (string) $donation->amount,
                'method' => $donation->method,
                'message' => $donation->message,
                'donated_at' => (string) $donation->donated_at,
            ])->all(),
            'supporter_count' => Donation::query()->where('status', true)->count(),
        ];
    }
}
