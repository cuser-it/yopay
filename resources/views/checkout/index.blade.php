<x-layouts.app :title="__('payment.title')">
    <main
        class="checkout-shell"
        x-data="checkoutApp(@js([
            'resumeToken' => $resumeToken,
            'createUrl' => route('checkout.api.orders.store'),
            'showUrlTemplate' => route('checkout.api.orders.show', ['token' => '__TOKEN__']),
            'initializeUrlTemplate' => route('checkout.api.orders.initialize', ['token' => '__TOKEN__']),
            'cancelUrlTemplate' => route('checkout.api.orders.cancel', ['token' => '__TOKEN__']),
            'rootUrl' => route('checkout.create'),
            'messages' => trans('payment'),
        ]))"
        x-init="boot()"
        x-cloak
    >
        <div class="checkout-stage" :class="{ 'checkout-stage--active': order !== null }">
            <x-ui.card class="checkout-card checkout-primary">
                <div class="checkout-brand">
                    <p class="checkout-brand__name">{{ __('payment.brand') }}</p>
                    <x-ui.badge tone="info">服务端验签</x-ui.badge>
                </div>

                <template x-if="order === null && !hasUnrestoredOrder()">
                    <form class="checkout-form" @submit.prevent="createOrder">
                        <div class="checkout-intro">
                            <h1 class="checkout-intro__title">{{ __('payment.title') }}</h1>
                            <p class="checkout-intro__copy">{{ __('payment.subtitle') }}</p>
                        </div>

                        <label class="field">
                            <span class="field__label">{{ __('payment.amount') }}</span>
                            <span class="amount-input">
                                <span class="amount-input__currency">¥</span>
                                <input
                                    class="amount-input__control"
                                    type="text"
                                    inputmode="decimal"
                                    autocomplete="off"
                                    placeholder="0.00"
                                    x-model.trim="amount"
                                    :disabled="loading"
                                    aria-describedby="amount-error"
                                >
                            </span>
                            <span class="field__error" id="amount-error" x-show="validationError" x-text="validationError"></span>
                        </label>

                        <fieldset class="payment-methods">
                            <legend class="field__label">{{ __('payment.payment_method') }}</legend>
                            <div class="payment-methods__grid">
                                <label class="payment-method" :class="{ 'payment-method--selected': paymentMethod === 'alipay' }">
                                    <input type="radio" value="alipay" x-model="paymentMethod">
                                    <span class="payment-method__indicator" aria-hidden="true"></span>
                                    <span>{{ __('payment.alipay') }}</span>
                                </label>
                                <label class="payment-method" :class="{ 'payment-method--selected': paymentMethod === 'wxpay' }">
                                    <input type="radio" value="wxpay" x-model="paymentMethod">
                                    <span class="payment-method__indicator" aria-hidden="true"></span>
                                    <span>{{ __('payment.wxpay') }}</span>
                                </label>
                            </div>
                        </fieldset>

                        <p class="checkout-error" x-show="errorMessage" x-text="errorMessage" role="alert"></p>
                        <x-ui.button type="submit" class="checkout-submit" x-bind:disabled="loading">
                            <span x-show="!loading">{{ __('payment.create') }}</span>
                            <span x-show="loading">正在创建…</span>
                        </x-ui.button>
                    </form>
                </template>

                <template x-if="hasUnrestoredOrder()">
                    <section class="state-message state-message--stacked" aria-live="polite">
                        <span class="loading-indicator" x-show="loading" aria-hidden="true"></span>
                        <p>{{ __('payment.restore_order') }}</p>
                        <p class="checkout-error" x-show="errorMessage" x-text="errorMessage" role="alert"></p>
                        <button class="button button--secondary" type="button" @click="refreshOrder" :disabled="loading || !online">
                            {{ __('payment.restore_retry') }}
                        </button>
                    </section>
                </template>

                <template x-if="order !== null">
                    <section class="order-summary">
                        <div>
                            <p class="eyebrow">{{ __('payment.order_no') }}</p>
                            <p class="order-summary__number" x-text="order.order_no"></p>
                        </div>
                        <div>
                            <p class="eyebrow">{{ __('payment.expected_amount') }}</p>
                            <p class="order-summary__amount"><span>¥</span><span x-text="order.expected_amount"></span></p>
                        </div>
                        <dl class="order-summary__facts">
                            <div>
                                <dt>{{ __('payment.payment_method') }}</dt>
                                <dd x-text="methodLabel(order.payment_method)"></dd>
                            </div>
                            <div>
                                <dt>{{ __('payment.expires_at') }}</dt>
                                <dd x-text="formatTime(order.expires_at)"></dd>
                            </div>
                        </dl>
                        <button class="button button--secondary" type="button" @click="cancelOrder" :disabled="loading || !isCancellable()">
                            {{ __('payment.cancel') }}
                        </button>
                    </section>
                </template>
            </x-ui.card>

            <x-ui.card class="checkout-card checkout-detail" x-show="order !== null" x-transition.opacity>
                <section class="payment-panel" aria-live="polite">
                    <div class="payment-panel__header">
                        <div>
                            <p class="eyebrow" x-text="statusEyebrow()"></p>
                            <h2 class="payment-panel__title" x-text="statusLabel()"></h2>
                        </div>
                        <span class="badge" :class="statusBadgeClass()">
                            <span x-text="statusLabel()"></span>
                        </span>
                    </div>

                    <div class="mobile-order-amount">
                        <span>{{ __('payment.expected_amount') }}</span>
                        <strong>¥<span x-text="order?.expected_amount"></span></strong>
                    </div>

                    <template x-if="order?.payment_method_selectable">
                        <div class="payment-initialize">
                            <p class="payment-panel__copy">金额由开发者固定，请选择支付方式。</p>
                            <div class="payment-methods__grid">
                                <label class="payment-method" :class="{ 'payment-method--selected': paymentMethod === 'alipay' }">
                                    <input type="radio" value="alipay" x-model="paymentMethod">
                                    <span class="payment-method__indicator" aria-hidden="true"></span>
                                    <span>{{ __('payment.alipay') }}</span>
                                </label>
                                <label class="payment-method" :class="{ 'payment-method--selected': paymentMethod === 'wxpay' }">
                                    <input type="radio" value="wxpay" x-model="paymentMethod">
                                    <span class="payment-method__indicator" aria-hidden="true"></span>
                                    <span>{{ __('payment.wxpay') }}</span>
                                </label>
                            </div>
                            <x-ui.button type="button" @click="initializePayment" x-bind:disabled="loading">
                                {{ __('payment.initialize') }}
                            </x-ui.button>
                        </div>
                    </template>

                    <template x-if="showsPaymentAction()">
                        <div class="qr-panel">
                            <div class="qr-panel__canvas-wrap" x-show="order.payment_action?.type === 'qr_code'">
                                <canvas class="qr-panel__canvas" x-ref="qrCanvas" aria-label="支付二维码"></canvas>
                            </div>
                            <p class="payment-panel__copy">{{ __('payment.scan_hint') }}</p>
                            <p class="checkout-error" x-show="qrError" x-text="messages.qr_failed"></p>
                            <a class="button button--secondary" x-show="paymentLink()" :href="paymentLink()" rel="noopener noreferrer">
                                {{ __('payment.direct_pay') }}
                            </a>
                            <button class="button button--secondary" type="button" @click="refreshOrder" :disabled="loading || !online">
                                {{ __('payment.refresh_status') }}
                            </button>
                        </div>
                    </template>

                    <template x-if="order?.status === 'creating' && !order?.payment_method_selectable">
                        <div class="state-message">
                            <span class="loading-indicator" aria-hidden="true"></span>
                            <p>{{ __('payment.recovering') }}</p>
                        </div>
                    </template>

                    <template x-if="isConfirmed()">
                        <div class="payment-result">
                            <p class="eyebrow">{{ __('payment.paid_amount') }}</p>
                            <p class="payment-result__amount">¥<span x-text="order.paid_amount"></span></p>
                            <p class="payment-panel__copy">{{ __('payment.verified_hint') }}</p>
                            <a class="button" x-show="order.return_url" :href="order.return_url" rel="noopener noreferrer">返回商户网站</a>
                            <button class="button" type="button" x-show="order.can_start_new_payment" @click="startNewPayment">{{ __('payment.new_payment') }}</button>
                        </div>
                    </template>

                    <template x-if="isTerminalWithoutConfirmation()">
                        <div class="state-message state-message--stacked">
                            <p class="payment-panel__copy" x-text="terminalMessage()"></p>
                            <a class="button" x-show="order.return_url" :href="order.return_url" rel="noopener noreferrer">返回商户网站</a>
                            <button class="button" type="button" x-show="order.can_start_new_payment" @click="startNewPayment">{{ __('payment.new_payment') }}</button>
                        </div>
                    </template>

                    <p class="network-notice" x-show="!online">{{ __('payment.network_offline') }}</p>
                    <p class="checkout-error" x-show="errorMessage" x-text="errorMessage" role="alert"></p>
                </section>
            </x-ui.card>
        </div>
    </main>
</x-layouts.app>
