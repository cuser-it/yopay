<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Domain\Payment\Enums\PaymentStatus;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

final class PaymentStatusBadge extends Component
{
    public readonly string $label;
    public readonly string $tone;

    public function __construct(public PaymentStatus|string $status)
    {
        $resolved = is_string($status) ? PaymentStatus::from($status) : $status;
        $this->label = match ($resolved) {
            PaymentStatus::Creating => '创建中',
            PaymentStatus::Pending => '待支付',
            PaymentStatus::Paid => '已支付',
            PaymentStatus::Expired => '已过期',
            PaymentStatus::Cancelled => '已取消',
            PaymentStatus::Failed => '失败',
            PaymentStatus::AmountMismatch => '金额异常',
            PaymentStatus::PaidAfterCancel => '取消后到账',
            PaymentStatus::Refunded => '已退款',
        };
        $this->tone = match ($resolved) {
            PaymentStatus::Paid, PaymentStatus::Refunded => 'success',
            PaymentStatus::AmountMismatch, PaymentStatus::PaidAfterCancel => 'warning',
            PaymentStatus::Failed, PaymentStatus::Expired, PaymentStatus::Cancelled => 'error',
            default => 'info',
        };
    }

    public function render(): View
    {
        return view('components.payment-status-badge');
    }
}
