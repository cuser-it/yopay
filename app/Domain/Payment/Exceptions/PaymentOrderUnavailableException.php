<?php

declare(strict_types=1);

namespace App\Domain\Payment\Exceptions;

use RuntimeException;

final class PaymentOrderUnavailableException extends RuntimeException {}
