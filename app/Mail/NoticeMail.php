<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Todos os e-mails do app usam o mesmo layout (`mail.notice`).
 * Cada classe só diz o assunto e o conteúdo, já traduzidos na locale do envio.
 */
abstract class NoticeMail extends Mailable
{
    use Queueable, SerializesModels;

    abstract protected function subjectLine(): string;

    /**
     * @return array{
     *     heading: string,
     *     lines?: list<string>,
     *     details?: array<string, string|null>,
     *     button?: array{label: string, url: string}|null,
     *     note?: string|null,
     * }
     */
    abstract protected function notice(): array;

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine());
    }

    public function content(): Content
    {
        $notice = $this->notice();

        return new Content(
            view: 'mail.notice',
            text: 'mail.notice-text',
            with: [
                'subjectLine' => $this->subjectLine(),
                'heading' => $notice['heading'],
                'lines' => array_values(array_filter($notice['lines'] ?? [], 'filled')),
                'details' => array_filter($notice['details'] ?? [], 'filled'),
                'button' => $notice['button'] ?? null,
                'note' => $notice['note'] ?? null,
            ],
        );
    }

    protected static function money(int $minor, string $currency): string
    {
        return $currency.' '.number_format($minor / 100, 2, '.', '');
    }

    protected static function date(mixed $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        return Carbon::parse($date)->locale(app()->getLocale())->isoFormat('LL');
    }

    protected static function time(mixed $time): ?string
    {
        return $time === null || $time === '' ? null : substr((string) $time, 0, 5);
    }

    protected static function app(string $path = ''): string
    {
        return rtrim((string) config('wwork.frontend_url'), '/').$path;
    }
}
