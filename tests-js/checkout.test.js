import assert from 'node:assert/strict';
import test from 'node:test';
import { checkoutApp, isValidPositiveAmount } from '../resources/js/checkout.js';

const terminalOrder = {
    status: 'paid',
    payment_action: null,
    payment_method: 'alipay',
};

function createStorage(entries = {}) {
    const values = new Map(Object.entries(entries));

    return {
        getItem(key) {
            return values.get(key) ?? null;
        },
        setItem(key, value) {
            values.set(key, value);
        },
        removeItem(key) {
            values.delete(key);
        },
    };
}

function createConfig(resumeToken = null) {
    return {
        resumeToken,
        createUrl: '/checkout-api/orders',
        showUrlTemplate: '/checkout-api/orders/__TOKEN__',
        initializeUrlTemplate: '/checkout-api/orders/__TOKEN__/initialize',
        cancelUrlTemplate: '/checkout-api/orders/__TOKEN__/cancel',
        rootUrl: '/',
        messages: {},
    };
}

function prepareBrowser({ storedToken = null, responseOrder = terminalOrder } = {}) {
    const storage = createStorage(storedToken
        ? { 'payment.checkout.resume-token': storedToken }
        : {});
    let animationFrames = 0;

    Object.defineProperty(globalThis, 'navigator', {
        configurable: true,
        value: { onLine: true },
    });
    Object.defineProperty(globalThis, 'localStorage', {
        configurable: true,
        value: storage,
    });
    Object.defineProperty(globalThis, 'document', {
        configurable: true,
        value: {
        hidden: false,
        documentElement: {},
        addEventListener() {},
        querySelector() {
            return null;
        },
        },
    });
    Object.defineProperty(globalThis, 'window', {
        configurable: true,
        value: {
        addEventListener() {},
        clearInterval() {},
        clearTimeout() {},
        setTimeout() {
            return 1;
        },
        requestAnimationFrame(callback) {
            animationFrames += 1;
            callback();
            return animationFrames;
        },
        },
    });
    Object.defineProperty(globalThis, 'fetch', {
        configurable: true,
        value: async () => new Response(JSON.stringify({ data: responseOrder }), {
            status: 200,
            headers: { 'content-type': 'application/json' },
        }),
    });

    return {
        animationFrames() {
            return animationFrames;
        },
        storage,
    };
}

function prepareApp(config) {
    const app = checkoutApp(config);
    app.$nextTick = async () => {};
    app.$refs = {};

    return app;
}

test('public checkout accepts one cent but rejects zero amounts', () => {
    assert.equal(isValidPositiveAmount('0.01'), true);
    assert.equal(isValidPositiveAmount('0.1'), true);
    assert.equal(isValidPositiveAmount('0'), false);
    assert.equal(isValidPositiveAmount('0.00'), false);
});

test('root checkout discards a terminal order recovered only from local storage', async () => {
    const browser = prepareBrowser({ storedToken: 'stored-paid-token' });
    const app = prepareApp(createConfig());

    await app.boot();

    assert.equal(app.token, null);
    assert.equal(app.order, null);
    assert.equal(browser.storage.getItem('payment.checkout.resume-token'), null);
});

test('explicit checkout result URL keeps the authoritative terminal result', async () => {
    const browser = prepareBrowser();
    const app = prepareApp(createConfig('route-paid-token'));

    await app.boot();

    assert.equal(app.token, 'route-paid-token');
    assert.equal(app.order.status, 'paid');
    assert.equal(browser.animationFrames(), 1);
});

test('QR rendering reports a fallback when the nested canvas is unavailable', async () => {
    const browser = prepareBrowser({
        responseOrder: {
            status: 'pending',
            payment_action: {
                type: 'qr_code',
                payload: 'https://pay.example.test/order',
                direct_url: 'https://pay.example.test/order',
            },
            payment_method: 'alipay',
        },
    });
    const app = prepareApp(createConfig('route-pending-token'));

    await app.boot();

    assert.equal(app.qrError, true);
    assert.equal(browser.animationFrames(), 1);
});
