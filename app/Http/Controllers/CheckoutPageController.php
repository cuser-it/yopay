<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

final class CheckoutPageController extends Controller
{
    public function create(): View
    {
        return view('checkout.index', ['resumeToken' => null]);
    }

    public function resume(string $token): View
    {
        return view('checkout.index', ['resumeToken' => $token]);
    }
}
