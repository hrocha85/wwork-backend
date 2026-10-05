<?php

namespace App\Actions\Clients;

use App\Enums\MembershipRole;
use App\Enums\VisitStatus;
use App\Models\Client;
use App\Models\Visit;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\RecordActivity;
use Illuminate\Support\Carbon;

class ImportClients
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{rows: list<array<string, mixed>>, counts: array{ok: int, invalid: int, duplicate: int}}
     */
    public function sheet(array $rows, bool $commit): array
    {
        $this->owner();
        $agencyId = AgencyContext::membership()->agency_id;
        $ownerId = AgencyContext::user()->id;
        $known = $this->phones($agencyId);
        $seen = [];
        $report = [];
        $ok = 0;
        $invalid = 0;
        $duplicate = 0;

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $name = trim((string) ($row['name'] ?? ''));
            $phone = trim((string) ($row['phone'] ?? $row['whatsapp'] ?? ''));
            $address = trim((string) ($row['address'] ?? ''));
            $email = trim((string) ($row['email'] ?? ''));
            $digits = preg_replace('/\D/', '', $phone) ?? '';
            $reason = null;
            if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
                $reason = 'name';
            } elseif ($digits === '' || mb_strlen($phone) > 32) {
                $reason = 'phone';
            } elseif (mb_strlen($address) > 255) {
                $reason = 'address';
            } elseif ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $reason = 'email';
            } elseif (isset($known[$digits]) || isset($seen[$digits])) {
                $reason = 'duplicate';
            }

            if ($reason === null) {
                $ok++;
                $seen[$digits] = true;
                if ($commit) {
                    Client::query()->create([
                        'agency_id' => $agencyId,
                        'created_by' => $ownerId,
                        'name' => $name,
                        'phone' => $phone,
                        'email' => $email === '' ? null : $email,
                        'address' => $address,
                        'lat' => null,
                        'lng' => null,
                    ]);
                    $known[$digits] = true;
                }
                $report[] = [
                    'line' => $line,
                    'status' => 'ok',
                    'name' => $name,
                    'needs_pin' => true,
                    'needs_address' => $address === '',
                ];
            } elseif ($reason === 'duplicate') {
                $duplicate++;
                $report[] = ['line' => $line, 'status' => 'duplicate', 'name' => $name, 'reason' => 'duplicate'];
            } else {
                $invalid++;
                $report[] = ['line' => $line, 'status' => 'invalid', 'name' => $name, 'reason' => $reason];
            }
        }

        if ($commit && $ok > 0) {
            RecordActivity::add($agencyId, $ownerId, 'client.created');
        }

        return ['rows' => $report, 'counts' => ['ok' => $ok, 'invalid' => $invalid, 'duplicate' => $duplicate]];
    }

    /**
     * @return array{rows: list<array<string, mixed>>, counts: array{ok: int, invalid: int, duplicate: int}}
     */
    public function calendar(string $ics, bool $commit): array
    {
        $this->owner();
        $membership = AgencyContext::membership();
        $agencyId = $membership->agency_id;
        $ownerId = AgencyContext::user()->id;
        $clients = Client::query()->where('agency_id', $agencyId)->get();
        $report = [];
        $ok = 0;
        $invalid = 0;

        foreach ($this->events($ics) as $event) {
            if ($event['date'] === null) {
                $invalid++;
                $report[] = ['status' => 'invalid', 'name' => $event['summary'], 'reason' => 'date'];

                continue;
            }
            $client = $clients->first(function (Client $client) use ($event): bool {
                $name = mb_strtolower(trim($client->name));
                $address = mb_strtolower(trim($client->address));
                $summary = mb_strtolower($event['summary']);
                $location = mb_strtolower($event['location']);

                return ($summary !== '' && $summary === $name) || ($location !== '' && $location === $address);
            });
            if ($client === null) {
                $invalid++;
                $report[] = ['status' => 'invalid', 'name' => $event['summary'], 'reason' => 'phone', 'date' => $event['date'], 'address' => $event['location']];

                continue;
            }
            $ok++;
            if ($commit) {
                Visit::query()->create([
                    'agency_id' => $agencyId,
                    'client_id' => $client->id,
                    'assignee_id' => $ownerId,
                    'service_date' => $event['date'],
                    'service_time' => $event['time'],
                    'description' => $event['summary'],
                    'price_pence' => 0,
                    'lat' => $client->lat,
                    'lng' => $client->lng,
                    'status' => VisitStatus::Todo,
                ]);
            }
            $report[] = ['status' => 'ok', 'name' => $client->name, 'date' => $event['date'], 'time' => $event['time']];
        }

        if ($commit && $ok > 0) {
            RecordActivity::add($agencyId, $ownerId, 'visit.created');
        }

        return ['rows' => $report, 'counts' => ['ok' => $ok, 'invalid' => $invalid, 'duplicate' => 0]];
    }

    private function owner(): void
    {
        if (AgencyContext::membership()->role !== MembershipRole::Owner) {
            throw new ApiException(ErrorCodes::CLIENT_FORBIDDEN, 403);
        }
    }

    /**
     * @return array<string, true>
     */
    private function phones(int $agencyId): array
    {
        $known = [];
        foreach (Client::query()->where('agency_id', $agencyId)->pluck('phone') as $phone) {
            $digits = preg_replace('/\D/', '', (string) $phone) ?? '';
            if ($digits !== '') {
                $known[$digits] = true;
            }
        }

        return $known;
    }

    /**
     * @return list<array{summary: string, location: string, date: ?string, time: string}>
     */
    private function events(string $ics): array
    {
        $text = str_replace("\r\n ", '', str_replace("\r\n\t", '', $ics));
        $blocks = preg_split('/BEGIN:VEVENT/', $text) ?: [];
        $events = [];
        foreach (array_slice($blocks, 1) as $block) {
            $body = strstr($block, 'END:VEVENT', true);
            if (! is_string($body)) {
                continue;
            }
            $summary = $this->field($body, 'SUMMARY');
            $location = $this->field($body, 'LOCATION');
            $start = $this->field($body, 'DTSTART');
            $when = $this->when($start);
            $events[] = [
                'summary' => $summary,
                'location' => $location,
                'date' => $when['date'],
                'time' => $when['time'],
            ];
        }

        return $events;
    }

    private function field(string $body, string $name): string
    {
        if (! preg_match('/^'.$name.'(?:;[^:]*)?:(.*)$/m', $body, $match)) {
            return '';
        }

        return trim(str_replace(['\\n', '\\,', '\\;'], ["\n", ',', ';'], $match[1]));
    }

    /**
     * @return array{date: ?string, time: string}
     */
    private function when(string $value): array
    {
        if (preg_match('/(\d{8})T(\d{4})/', $value, $match)) {
            $date = Carbon::createFromFormat('Ymd', $match[1]);

            return [
                'date' => $date === false ? null : $date->toDateString(),
                'time' => substr($match[2], 0, 2).':'.substr($match[2], 2, 2),
            ];
        }
        if (preg_match('/(\d{8})/', $value, $match)) {
            $date = Carbon::createFromFormat('Ymd', $match[1]);

            return ['date' => $date === false ? null : $date->toDateString(), 'time' => '09:00'];
        }

        return ['date' => null, 'time' => '09:00'];
    }
}
