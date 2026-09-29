<?php

namespace App\Mail;

use App\Enums\SubscriptionStatus;

/**
 * Mudança de estado da assinatura vinda do Stripe: `past_due`, `active` (saindo de outro estado) ou `cancelled`.
 */
class SubscriptionStatusMail extends NoticeMail
{
    public function __construct(
        public SubscriptionStatus $status,
        public string $agencyName,
    ) {}

    private function key(string $suffix): string
    {
        return 'mail.subscription.'.$this->status->value.'_'.$suffix;
    }

    protected function subjectLine(): string
    {
        return __($this->key('subject'));
    }

    protected function notice(): array
    {
        return [
            'heading' => __($this->key('heading')),
            'lines' => [__($this->key('body'), ['agency' => $this->agencyName])],
            'button' => ['label' => __('mail.subscription.button'), 'url' => self::app('/subscription')],
        ];
    }
}
