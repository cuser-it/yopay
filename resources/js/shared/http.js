const DEFAULT_TIMEOUT_MS = 15000;

export class JsonRequestError extends Error {
    constructor(message, { code = 'request_failed', status = 0, requestId = null, details = null } = {}) {
        super(message);
        this.name = 'JsonRequestError';
        this.code = code;
        this.status = status;
        this.requestId = requestId;
        this.details = details;
    }
}

export async function requestJson(url, options = {}) {
    const controller = new AbortController();
    const timeout = window.setTimeout(() => controller.abort(), options.timeout ?? DEFAULT_TIMEOUT_MS);
    const method = (options.method ?? 'GET').toUpperCase();
    const requestId = options.requestId ?? crypto.randomUUID();
    const headers = new Headers(options.headers ?? {});

    headers.set('Accept', 'application/json');
    headers.set('X-Request-ID', requestId);

    if (method !== 'GET' && method !== 'HEAD') {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

        if (csrfToken) {
            headers.set('X-CSRF-TOKEN', csrfToken);
        }

        if (options.body && ! headers.has('Content-Type')) {
            headers.set('Content-Type', 'application/json');
        }
    }

    try {
        const response = await fetch(url, {
            ...options,
            method,
            headers,
            body: options.body && headers.get('Content-Type') === 'application/json'
                ? JSON.stringify(options.body)
                : options.body,
            signal: controller.signal,
        });
        const contentType = response.headers.get('content-type') ?? '';

        if (! contentType.includes('application/json')) {
            throw new JsonRequestError('服务返回了无法识别的响应。', {
                code: 'invalid_response',
                status: response.status,
                requestId,
            });
        }

        const payload = await response.json();

        if (! response.ok) {
            throw new JsonRequestError(payload?.error?.message ?? '请求未能完成，请稍后重试。', {
                code: payload?.error?.code ?? 'request_failed',
                status: response.status,
                requestId: payload?.request_id ?? requestId,
                details: payload?.error?.details ?? null,
            });
        }

        return payload;
    } catch (error) {
        if (error instanceof JsonRequestError) {
            throw error;
        }

        if (error?.name === 'AbortError') {
            throw new JsonRequestError('请求超时，请检查网络后重试。', {
                code: 'request_timeout',
                requestId,
            });
        }

        throw new JsonRequestError('网络连接失败，恢复网络后可继续当前订单。', {
            code: 'network_error',
            requestId,
        });
    } finally {
        window.clearTimeout(timeout);
    }
}
