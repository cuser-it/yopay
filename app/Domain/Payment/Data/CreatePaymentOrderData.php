<?php

declare(strict_types=1);

namespace App\Domain\Payment\Data;

use App\Domain\Payment\Enums\OrderSource;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\ValueObjects\Money;
use App\Models\DeveloperApplication;

final readonly class CreatePaymentOrderData
{
    public function __construct(
        public OrderSource $source,
        public Money $amount,
        public string $subject,
        public ?PaymentMethod $paymentMethod,
        public string $clientIp,
        public ?DeveloperApplication $application = null,
        public ?string $externalOrderNumber = null,
        public ?string $description = null,
        public ?string $notifyUrl = null,
        public ?string $notifySecret = null,
        public ?string $returnUrl = null,
        public array $metadata = [],
    ) {}
}
