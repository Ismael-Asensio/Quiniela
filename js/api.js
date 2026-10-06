// ═══════════════════════════════════════════════════════════════
// api.js — Cliente HTTP centralizado
// ═══════════════════════════════════════════════════════════════

async function apiGet(url) {
    const res = await fetch(url);
    return res.json();
}

async function apiPost(url, body) {
    const options = { method: 'POST' };
    if (body !== undefined) {
        options.headers = { 'Content-Type': 'application/json' };
        options.body = JSON.stringify(body);
    }
    const res = await fetch(url, options);
    return res.json();
}
