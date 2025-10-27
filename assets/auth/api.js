const AUTH_CSRF_META_NAME = 'csrf-token-authenticate';

export async function postJson(url, payload, { signal } = {}) {
    const headers = new Headers({
        Accept: 'application/json',
        'Content-Type': 'application/json',
    });

    const csrfToken = resolveCsrfToken(AUTH_CSRF_META_NAME);

    if (csrfToken) {
        headers.set('X-CSRF-Token', csrfToken);
    }

    const response = await fetch(url, {
        method: 'POST',
        headers,
        body: JSON.stringify(payload),
        credentials: 'same-origin',
        signal,
    });

    let json;

    if (response.headers.get('content-type')?.includes('application/json')) {
        try {
            json = await response.json();
        } catch (error) {
            console.error('Nieprawidłowa odpowiedź JSON', error);
        }
    }

    return { response, json };
}

function resolveCsrfToken(metaName) {
    const meta = document.querySelector(`meta[name="${metaName}"]`);

    return meta?.getAttribute('content') ?? undefined;
}

