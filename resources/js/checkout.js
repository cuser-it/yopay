import QRCode from 'qrcode';
import { JsonRequestError, requestJson } from './shared/http.js';

const TOKEN_STORAGE_KEY = 'payment.checkout.resume-token';
const IDEMPOTENCY_STORAGE_KEY = 'payment.checkout.idempotency-key';
const VISIBLE_POLL_INTERVAL_MS = 3000;
const HIDDEN_POLL_INTERVAL_MS = 15000;

function randomKey() {
    return crypto.randomUUID?.() ?? Array.from(crypto.getRandomValues(new Uint8Array(24)), (value) => value.toString(16).padStart(2, '0')).join('');
}

export function isValidPositiveAmount(amount) {
    const match = /^(0|[1-9]\d{0,6})(?:\.(\d{1,2}))?$/.exec(amount);

    if (! match) {
        return false;
    }

    const wholeCents = Number.parseInt(match[1], 10) * 100;
    const fractionalCents = Number.parseInt((match[2] ?? '').padEnd(2, '0') || '0', 10);

    return wholeCents + fractionalCents > 0;
}

export function checkoutApp(config) {
    return {
        amount: '',
        paymentMethod: 'alipay',
        token: config.resumeToken,
        order: null,
        loading: false,
        online: navigator.onLine,
        errorMessage: '',
        validationError: '',
        qrError: false,
        pollTimer: null,
        statusRequestInFlight: false,
        messages: config.messages,

        async boot() {
            window.addEventListener('online', () => {
                this.online = true;
                void this.restoreOrder();
            });
            window.addEventListener('offline', () => {
                this.online = false;
                this.stopPolling();
            });
            window.addEventListener('beforeunload', () => this.stopPolling());
            document.addEventListener('visibilitychange', () => {
                if (! this.shouldPoll()) {
                    return;
                }

                if (document.hidden) {
                    this.startPolling();
                    return;
                }

                this.stopPolling();
                void this.restoreOrder();
            });

            let recoveredFromStorage = false;

            if (! this.token) {
                this.token = localStorage.getItem(TOKEN_STORAGE_KEY);
                recoveredFromStorage = Boolean(this.token);
            }

            if (this.token) {
                await this.restoreOrder(true, recoveredFromStorage);
            }
        },

        async createOrder() {
            if (this.loading) {
                return;
            }

            this.errorMessage = '';
            this.validationError = '';

            if (! isValidPositiveAmount(this.amount)) {
                this.validationError = '请输入大于 0 且最多保留两位小数的金额。';
                return;
            }

            const idempotencyKey = localStorage.getItem(IDEMPOTENCY_STORAGE_KEY) ?? randomKey();
            localStorage.setItem(IDEMPOTENCY_STORAGE_KEY, idempotencyKey);
            this.loading = true;

            try {
                const response = await requestJson(config.createUrl, {
                    method: 'POST',
                    headers: { 'Idempotency-Key': idempotencyKey },
                    body: {
                        amount: this.amount,
                        payment_method: this.paymentMethod,
                    },
                });
                this.token = response.data.checkout_token;
                this.order = response.data;
                localStorage.setItem(TOKEN_STORAGE_KEY, this.token);
                localStorage.removeItem(IDEMPOTENCY_STORAGE_KEY);
                history.replaceState({}, '', response.data.checkout_url);
                await this.afterOrderUpdate();
            } catch (error) {
                this.handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async restoreOrder(silentMissing = false, discardTerminalRecovery = false) {
            if (! this.token || ! this.online || this.statusRequestInFlight) {
                return;
            }

            this.statusRequestInFlight = true;
            this.loading = this.order === null;
            this.errorMessage = '';

            try {
                const response = await requestJson(this.endpoint(config.showUrlTemplate));
                this.order = response.data;

                if (discardTerminalRecovery && this.isTerminal()) {
                    this.clearRecovery();
                    return;
                }

                await this.afterOrderUpdate();
            } catch (error) {
                if (silentMissing && error instanceof JsonRequestError && error.status === 404) {
                    this.clearRecovery();
                    return;
                }

                this.handleError(error);
            } finally {
                this.statusRequestInFlight = false;
                this.loading = false;

                if (this.shouldPoll() && this.pollTimer === null) {
                    this.startPolling();
                }
            }
        },

        async initializePayment() {
            if (! this.token || this.loading) {
                return;
            }

            this.loading = true;
            this.errorMessage = '';

            try {
                const response = await requestJson(this.endpoint(config.initializeUrlTemplate), {
                    method: 'POST',
                    body: { payment_method: this.paymentMethod },
                });
                this.order = response.data;
                await this.afterOrderUpdate();
            } catch (error) {
                this.handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async cancelOrder() {
            if (! this.token || this.loading || ! this.isCancellable()) {
                return;
            }

            this.loading = true;
            this.errorMessage = '';

            try {
                const response = await requestJson(this.endpoint(config.cancelUrlTemplate), { method: 'POST' });
                this.order = response.data;
                this.stopPolling();
            } catch (error) {
                this.handleError(error);
            } finally {
                this.loading = false;
            }
        },

        async afterOrderUpdate() {
            if (this.order?.payment_method) {
                this.paymentMethod = this.order.payment_method;
            }

            await this.waitForPaymentActionDom();
            await this.renderQrCode();

            if (this.isPollable()) {
                this.startPolling();
            } else {
                this.stopPolling();
            }
        },

        async renderQrCode() {
            this.qrError = false;

            if (this.order?.payment_action?.type !== 'qr_code') {
                return;
            }

            if (! this.$refs.qrCanvas) {
                this.qrError = true;
                return;
            }

            try {
                const rawSize = getComputedStyle(document.documentElement).getPropertyValue('--qr-size');
                const width = Number.parseInt(rawSize, 10) || 232;
                await QRCode.toCanvas(this.$refs.qrCanvas, this.order.payment_action.payload, {
                    width,
                    margin: 1,
                    errorCorrectionLevel: 'M',
                });
            } catch {
                this.qrError = true;
            }
        },

        async waitForPaymentActionDom() {
            await this.$nextTick();
            await new Promise((resolve) => window.requestAnimationFrame(resolve));
            await this.$nextTick();
        },

        startPolling() {
            this.stopPolling();

            if (! this.shouldPoll()) {
                return;
            }

            const interval = document.hidden ? HIDDEN_POLL_INTERVAL_MS : VISIBLE_POLL_INTERVAL_MS;
            this.pollTimer = window.setTimeout(() => {
                this.pollTimer = null;
                void this.restoreOrder();
            }, interval);
        },

        stopPolling() {
            if (this.pollTimer !== null) {
                window.clearInterval(this.pollTimer);
                this.pollTimer = null;
            }
        },

        startNewPayment() {
            this.clearRecovery();
            window.location.assign(config.rootUrl);
        },

        refreshOrder() {
            this.stopPolling();
            void this.restoreOrder();
        },

        clearRecovery() {
            this.stopPolling();
            this.token = null;
            this.order = null;
            localStorage.removeItem(TOKEN_STORAGE_KEY);
            localStorage.removeItem(IDEMPOTENCY_STORAGE_KEY);
        },

        endpoint(template) {
            return template.replace('__TOKEN__', encodeURIComponent(this.token));
        },

        handleError(error) {
            this.errorMessage = error instanceof JsonRequestError
                ? error.message
                : '请求未能完成，请稍后重试。';
        },

        paymentLink() {
            if (! this.order?.payment_action) {
                return null;
            }

            return this.order.payment_action.direct_url
                ?? (['redirect', 'url_scheme'].includes(this.order.payment_action.type)
                    ? this.order.payment_action.payload
                    : null);
        },

        showsPaymentAction() {
            return this.order?.status === 'pending' && this.order?.payment_action !== null;
        },

        isPollable() {
            return ['creating', 'pending'].includes(this.order?.status);
        },

        shouldPoll() {
            return this.online && Boolean(this.token) && (this.order === null || this.isPollable());
        },

        hasUnrestoredOrder() {
            return Boolean(this.token) && this.order === null;
        },

        isCancellable() {
            return ['creating', 'pending'].includes(this.order?.status);
        },

        isConfirmed() {
            return ['paid', 'amount_mismatch', 'paid_after_cancel', 'refunded'].includes(this.order?.status);
        },

        isTerminalWithoutConfirmation() {
            return ['expired', 'cancelled', 'failed'].includes(this.order?.status);
        },

        isTerminal() {
            return this.isConfirmed() || this.isTerminalWithoutConfirmation();
        },

        statusLabel() {
            return this.messages[this.order?.status] ?? '订单状态更新中';
        },

        statusEyebrow() {
            return this.isConfirmed() ? this.messages.paid_amount : '支付状态';
        },

        statusBadgeClass() {
            const status = this.order?.status;

            if (['paid', 'refunded'].includes(status)) return 'badge--success';
            if (['amount_mismatch', 'paid_after_cancel'].includes(status)) return 'badge--warning';
            if (['failed', 'cancelled', 'expired'].includes(status)) return 'badge--error';
            return 'badge--info';
        },

        terminalMessage() {
            return {
                expired: '订单已超过支付有效期，请重新发起付款。',
                cancelled: '当前订单已放弃，不会被删除，可在运营记录中追溯。',
                failed: '支付信息创建失败，请稍后重新发起付款。',
            }[this.order?.status] ?? '';
        },

        methodLabel(method) {
            return method ? this.messages[method] : '待选择';
        },

        formatTime(value) {
            if (! value) return '—';
            return new Intl.DateTimeFormat('zh-CN', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
            }).format(new Date(value));
        },
    };
}
