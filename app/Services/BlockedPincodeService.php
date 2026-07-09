<?php

namespace App\Services;

use App\Models\BlockedPincode;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class BlockedPincodeService
{
    public function isBlocked(string $pincode): bool
    {
        $normalized = $this->normalizePincode($pincode);

        if ($normalized === null) {
            return false;
        }

        return BlockedPincode::query()
            ->where('pincode', $normalized)
            ->where('is_active', true)
            ->exists();
    }

    public function assertNotBlocked(string $pincode, string $field = 'pincode'): void
    {
        if ($this->isBlocked($pincode)) {
            throw ValidationException::withMessages([
                $field => [config('blocked_pincodes.message', 'Delivery is not available for this pincode.')],
            ]);
        }
    }

    /**
     * @return array{
     *     imported: int,
     *     skipped: int,
     *     invalid: int,
     *     total: int
     * }
     */
    public function importFromText(string $contents, ?int $createdBy = null): array
    {
        $createdBy ??= Auth::guard('admin')->id();
        $rows = $this->parseRows($contents);

        $imported = 0;
        $skipped = 0;
        $invalid = 0;

        foreach ($rows as $row) {
            $pincode = $this->normalizePincode($row['pincode'] ?? '');

            if ($pincode === null) {
                $invalid++;

                continue;
            }

            $existing = BlockedPincode::query()->where('pincode', $pincode)->first();

            if ($existing) {
                $skipped++;

                continue;
            }

            BlockedPincode::query()->create([
                'pincode' => $pincode,
                'city' => $row['city'] ?? null,
                'state' => $row['state'] ?? null,
                'reason' => $row['reason'] ?? null,
                'is_active' => true,
                'created_by' => $createdBy,
            ]);

            $imported++;
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'invalid' => $invalid,
            'total' => count($rows),
        ];
    }

    /**
     * @return list<array{pincode: string, city: ?string, state: ?string, reason: ?string}>
     */
    protected function parseRows(string $contents): array
    {
        $rows = [];
        $lines = preg_split('/\R+/', trim($contents)) ?: [];

        foreach ($lines as $index => $line) {
            $line = trim($line);

            if ($line === '' || ($index === 0 && $this->isHeaderRow($line))) {
                continue;
            }

            $parts = str_getcsv($line);

            if (count($parts) === 1 && str_contains($parts[0], ',')) {
                $parts = array_map('trim', explode(',', $parts[0]));
            }

            $rows[] = [
                'pincode' => trim((string) ($parts[0] ?? '')),
                'city' => isset($parts[1]) ? trim((string) $parts[1]) : null,
                'state' => isset($parts[2]) ? trim((string) $parts[2]) : null,
                'reason' => isset($parts[3]) ? trim((string) $parts[3]) : null,
            ];
        }

        return $rows;
    }

    protected function isHeaderRow(string $line): bool
    {
        return (bool) preg_match('/^pincode\b/i', $line);
    }

    protected function normalizePincode(string $pincode): ?string
    {
        $digits = preg_replace('/\D/', '', $pincode) ?? '';

        if (! preg_match('/^\d{6}$/', $digits)) {
            return null;
        }

        return $digits;
    }
}
