<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Domain\Payment\ValueObjects\Money as MoneyValue;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

final class Money extends Component
{
    public readonly string $formatted;

    public readonly string $sign;

    public function __construct(int $cents, string $currency = 'CNY')
    {
        $this->formatted = (new MoneyValue(abs($cents), $currency))->format();
        $this->sign = $cents < 0 ? '−' : '';
    }

    public function render(): View
    {
        return view('components.money');
    }
}
