<?php

namespace App\Helpers;

use App\Models\Contact;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class ContactImport implements ToCollection
{
    public function __construct(
        protected int|string $labelId,
        protected int $userId,
        protected string $sessionId,
    ) {}

    public function collection(Collection $collection): void
    {
        foreach ($collection as $row) {
            $name = trim((string) ($row[0] ?? ''));
            $rawNumber = trim((string) ($row[1] ?? ''));
            if ($rawNumber === '') continue;

            $number = str_contains($rawNumber, '@g.us')
                ? $rawNumber
                : preg_replace('/\D+/', '', $rawNumber);

            // Ignore headers, malformed rows and suspiciously long input.
            if (!str_contains($number, '@g.us') && (strlen($number) < 7 || strlen($number) > 18)) continue;
            if (strlen($number) > 80 || mb_strlen($name) > 190) continue;

            Contact::firstOrCreate(
                [
                    'user_id' => $this->userId,
                    'session_id' => $this->sessionId,
                    'label_id' => $this->labelId,
                    'number' => $number,
                ],
                [
                    'name' => $name !== '' ? $name : null,
                    // Imports never imply marketing consent.
                    'opt_in_status' => 'unknown',
                ]
            );
        }
    }
}
