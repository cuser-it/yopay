<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Domain\Payment\Enums\PaymentStatus;
use App\Models\NotificationDelivery;
use App\Models\PaymentOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class OperationsOrderController
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $search = trim($request->string('search')->toString());
        $orders = PaymentOrder::query()
            ->when(PaymentStatus::tryFrom($status), static fn ($query, PaymentStatus $resolved) => $query->where('status', $resolved->value))
            ->when($search !== '', static function ($query) use ($search): void {
                $query->where(static function ($nested) use ($search): void {
                    $nested->where('order_no', 'like', '%'.$search.'%')
                        ->orWhere('external_order_no', 'like', '%'.$search.'%')
                        ->orWhere('gateway_trade_no', 'like', '%'.$search.'%');
                });
            })
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        $statuses = PaymentStatus::cases();

        return view('operations.orders.index', compact('orders', 'status', 'search', 'statuses'));
    }

    public function show(PaymentOrder $order): View
    {
        $order->load([
            'application',
            'callbacks' => static fn ($query) => $query->latest('id'),
            'statusEvents' => static fn ($query) => $query->latest('id'),
            'paymentEvents.webhookDeliveries' => static fn ($query) => $query->latest('id'),
        ]);
        $notifications = NotificationDelivery::query()->where('order_id', $order->getKey())->latest('id')->get();

        return view('operations.orders.show', compact('order', 'notifications'));
    }
}
