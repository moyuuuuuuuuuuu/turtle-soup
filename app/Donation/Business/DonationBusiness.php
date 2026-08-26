<?php

declare(strict_types=1);

namespace App\Donation\Business;

use App\Common\Enums\ErrorCode;
use App\Common\Support\PublicId;
use App\Donation\Formats\DonationFormat;
use App\Donation\Models\Donation;
use App\Donation\Models\DonationChannel;
use App\Storage\Services\BosObjectStorage;
use Webman\Http\UploadFile;

final class DonationBusiness
{
    /** @return array<string, mixed> */
    public function publicPage(): array
    {
        return DonationFormat::publicPage();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function page(array $filters, int $page, int $size): array
    {
        $query = Donation::query();
        if ($keyword = trim((string) ($filters['keyword'] ?? ''))) {
            $query->where('donor_name', 'like', "%{$keyword}%");
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (bool) $filters['status']);
        }
        $total = $query->count();

        return ['items' => $query->orderByDesc('sort')->orderByDesc('donated_at')->forPage($page, $size)->get()->toArray(), 'total' => $total, 'page' => $page, 'pageSize' => $size];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function save(array $input): array
    {
        $data = $this->validatedDonation($input);
        $data['public_id'] = PublicId::make();

        return Donation::create($data)->toArray();
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function update(int $id, array $input): array
    {
        $donation = Donation::find($id);
        if (!$donation instanceof Donation) {
            ErrorCode::DONATION_NOT_FOUND->throw();
        }
        $donation->update($this->validatedDonation($input));

        return $donation->fresh()?->toArray() ?? $donation->toArray();
    }

    /** @param array<int, int|string> $ids */
    public function destroy(array $ids): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            ErrorCode::PARAM_ERROR->throw();
        }
        Donation::query()->whereIn('id', $ids)->delete();
    }

    /** @return array<int, array<string, mixed>> */
    public function channels(): array
    {
        return DonationChannel::query()->orderByDesc('sort')->get()->toArray();
    }

    /** @return array<string, mixed> */
    public function updateChannel(string $method, UploadFile $file, string $name, bool $status, int $sort): array
    {
        if (!in_array($method, ['wechat', 'alipay'], true) || !$file->isValid() || $file->getSize() > 5 * 1024 * 1024) {
            ErrorCode::DONATION_CHANNEL_INVALID->throw();
        }
        $mime = (string) $file->getUploadMimeType();
        $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        if (!isset($extensions[$mime])) {
            ErrorCode::DONATION_CHANNEL_INVALID->throw('仅支持 PNG、JPEG 或 WebP');
        }
        $body = file_get_contents($file->getPathname());
        if ($body === false) {
            ErrorCode::STORAGE_UPLOAD_FAILED->throw();
        }
        $key = 'donations/qr/'.$method.'/'.hash('sha256', $body).'.'.$extensions[$mime];
        $stored = (new BosObjectStorage())->put($key, $body, $mime);
        $channel = DonationChannel::query()->updateOrCreate(['method' => $method], [
            'name' => trim($name) ?: ($method === 'wechat' ? '微信支付' : '支付宝'),
            'qr_code_url' => $stored['url'],
            'qr_code_object_key' => $stored['object_key'],
            'status' => $status,
            'sort' => $sort,
        ]);

        return $channel->toArray();
    }

    /** @return array<string, int|string> */
    public function stats(): array
    {
        return [
            'supporter_count' => Donation::query()->where('status', true)->count(),
            'total_amount' => (string) Donation::query()->where('status', true)->sum('amount'),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function validatedDonation(array $input): array
    {
        $name = trim((string) ($input['donor_name'] ?? ''));
        $amount = round((float) ($input['amount'] ?? 0), 2);
        $donatedAt = (string) ($input['donated_at'] ?? '');
        if ($name === '' || mb_strlen($name) > 80 || $amount <= 0 || strtotime($donatedAt) === false) {
            ErrorCode::PARAM_ERROR->throw();
        }
        $method = (string) ($input['method'] ?? '');
        if ($method !== '' && !in_array($method, ['wechat', 'alipay', 'other'], true)) {
            ErrorCode::PARAM_ERROR->throw();
        }

        return [
            'donor_name' => $name,
            'amount' => number_format($amount, 2, '.', ''),
            'method' => $method ?: null,
            'message' => mb_substr(trim((string) ($input['message'] ?? '')), 0, 255) ?: null,
            'donated_at' => date('Y-m-d H:i:s', strtotime($donatedAt)),
            'status' => filter_var($input['status'] ?? true, FILTER_VALIDATE_BOOL),
            'sort' => (int) ($input['sort'] ?? 0),
        ];
    }
}
