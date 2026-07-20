<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

final class DeliveryStatusBadge extends Component
{
    public readonly string $label;
    public readonly string $tone;

    public function __construct(public string $status)
    {
        $this->label = match ($status) {
            'pending' => '待投递',
            'processing' => '投递中',
            'retrying' => '等待重试',
            'delivered' => '已送达',
            'failed' => '失败',
            default => $status,
        };
        $this->tone = match ($status) {
            'delivered' => 'success',
            'failed' => 'error',
            'retrying' => 'warning',
            default => 'info',
        };
    }

    public function render(): View
    {
        return view('components.delivery-status-badge');
    }
}
