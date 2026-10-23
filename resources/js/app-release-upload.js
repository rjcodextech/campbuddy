// Admin → Mobile app → App updates: sends an Android APK to the server in pieces (shared hosting
// caps one request at a few MB), retries a piece that fails on the way, then asks the server to
// check the whole file against its SHA-256 (App\Http\Controllers\Admin\AppReleaseController).

export const RETRIES = 4;

export class UploadError extends Error {}

export function pieceCount(size, chunkSize) {
    return Math.max(1, Math.ceil(size / chunkSize));
}

const K = new Uint32Array([
    0x428a2f98, 0x71374491, 0xb5c0fbcf, 0xe9b5dba5, 0x3956c25b, 0x59f111f1, 0x923f82a4, 0xab1c5ed5, 0xd807aa98, 0x12835b01, 0x243185be, 0x550c7dc3,
    0x72be5d74, 0x80deb1fe, 0x9bdc06a7, 0xc19bf174, 0xe49b69c1, 0xefbe4786, 0x0fc19dc6, 0x240ca1cc, 0x2de92c6f, 0x4a7484aa, 0x5cb0a9dc, 0x76f988da,
    0x983e5152, 0xa831c66d, 0xb00327c8, 0xbf597fc7, 0xc6e00bf3, 0xd5a79147, 0x06ca6351, 0x14292967, 0x27b70a85, 0x2e1b2138, 0x4d2c6dfc, 0x53380d13,
    0x650a7354, 0x766a0abb, 0x81c2c92e, 0x92722c85, 0xa2bfe8a1, 0xa81a664b, 0xc24b8b70, 0xc76c51a3, 0xd192e819, 0xd6990624, 0xf40e3585, 0x106aa070,
    0x19a4c116, 0x1e376c08, 0x2748774c, 0x34b0bcb5, 0x391c0cb3, 0x4ed8aa4a, 0x5b9cca4f, 0x682e6ff3, 0x748f82ee, 0x78a5636f, 0x84c87814, 0x8cc70208,
    0x90befffa, 0xa4506ceb, 0xbef9a3f7, 0xc67178f2,
]);

/** SHA-256 in plain JS, for pages without crypto.subtle (an http:// development site). */
export function sha256Fallback(buffer) {
    const bytes = new Uint8Array(buffer);
    const length = bytes.length;
    const padded = new Uint8Array(((length + 9 + 63) >> 6) << 6);
    padded.set(bytes);
    padded[length] = 0x80;
    const view = new DataView(padded.buffer);
    view.setUint32(padded.length - 8, Math.floor(length / 0x20000000));
    view.setUint32(padded.length - 4, (length << 3) >>> 0);
    const h = new Uint32Array([0x6a09e667, 0xbb67ae85, 0x3c6ef372, 0xa54ff53a, 0x510e527f, 0x9b05688c, 0x1f83d9ab, 0x5be0cd19]);
    const w = new Uint32Array(64);
    const rotr = (x, n) => (x >>> n) | (x << (32 - n));
    for (let offset = 0; offset < padded.length; offset += 64) {
        for (let i = 0; i < 16; i++) w[i] = view.getUint32(offset + i * 4);
        for (let i = 16; i < 64; i++) {
            const s0 = rotr(w[i - 15], 7) ^ rotr(w[i - 15], 18) ^ (w[i - 15] >>> 3);
            const s1 = rotr(w[i - 2], 17) ^ rotr(w[i - 2], 19) ^ (w[i - 2] >>> 10);
            w[i] = (w[i - 16] + s0 + w[i - 7] + s1) >>> 0;
        }
        let [a, b, c, d, e, f, g, hh] = h;
        for (let i = 0; i < 64; i++) {
            const t1 = (hh + (rotr(e, 6) ^ rotr(e, 11) ^ rotr(e, 25)) + ((e & f) ^ (~e & g)) + K[i] + w[i]) >>> 0;
            const t2 = ((rotr(a, 2) ^ rotr(a, 13) ^ rotr(a, 22)) + ((a & b) ^ (a & c) ^ (b & c))) >>> 0;
            hh = g; g = f; f = e; e = (d + t1) >>> 0; d = c; c = b; b = a; a = (t1 + t2) >>> 0;
        }
        h[0] += a; h[1] += b; h[2] += c; h[3] += d; h[4] += e; h[5] += f; h[6] += g; h[7] += hh;
    }

    return Array.from(h, (x) => x.toString(16).padStart(8, '0')).join('');
}

export async function sha256Hex(buffer, subtle = globalThis.crypto?.subtle) {
    if (!subtle) return sha256Fallback(buffer);
    const digest = await subtle.digest('SHA-256', buffer);

    return Array.from(new Uint8Array(digest), (b) => b.toString(16).padStart(2, '0')).join('');
}

function newId() {
    if (globalThis.crypto?.randomUUID) return globalThis.crypto.randomUUID();
    const hex = Array.from({ length: 32 }, () => Math.floor(Math.random() * 16).toString(16)).join('');

    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-4${hex.slice(13, 16)}-a${hex.slice(17, 20)}-${hex.slice(20)}`;
}

async function readJson(response) {
    try {
        return await response.json();
    } catch {
        return {};
    }
}

function refusal(status, json) {
    if (status === 419) return 'Your session has expired. Reload the page and upload again.';
    if (status === 413) return 'The server refused a piece as too big. Reload the page and upload again.';
    const first = json.errors ? Object.values(json.errors)[0]?.[0] : null;

    return json.message || first || `The server answered ${status}.`;
}

/**
 * Uploads `buffer` piece by piece, then asks the server to check it. Resolves with the server's
 * answer ({ redirect }); rejects with an UploadError whose message can be shown as it is.
 */
export async function uploadRelease({ buffer, chunkUrl, storeUrl, chunkSize, csrf, fields = {}, fetchImpl = fetch, onProgress = () => {}, wait = (ms) => new Promise((r) => setTimeout(r, ms)), id = newId(), subtle }) {
    const sha256 = await sha256Hex(buffer, subtle);
    const total = pieceCount(buffer.byteLength, chunkSize);
    let index = 0;

    while (index < total) {
        const body = buffer.slice(index * chunkSize, Math.min(buffer.byteLength, (index + 1) * chunkSize));
        for (let attempt = 0; ; attempt++) {
            let response;
            try {
                response = await fetchImpl(chunkUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/octet-stream', Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Upload-Id': id, 'X-Chunk-Index': String(index) },
                    body,
                });
            } catch {
                response = null; // offline for a moment: try again
            }
            if (response && response.ok) {
                const json = await readJson(response);
                // The server says which piece it wants next (more than one ahead when a retried piece had arrived).
                index = Number.isInteger(json.next) && json.next > index ? json.next : index + 1;
                break;
            }
            if (response && response.status >= 400 && response.status < 500 && response.status !== 408 && response.status !== 429) {
                throw new UploadError(refusal(response.status, await readJson(response)));
            }
            if (attempt >= RETRIES) throw new UploadError('The upload stopped: the connection or the server kept failing. Check the connection and upload again.');
            await wait(1000 * 2 ** attempt);
        }
        onProgress(Math.round((index / total) * 95));
    }

    let response;
    try {
        response = await fetchImpl(storeUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ upload: id, sha256, ...fields }),
        });
    } catch {
        throw new UploadError('The connection dropped while the server was checking the APK. Reload the page: if the version is not listed, upload again.');
    }
    const json = await readJson(response);
    if (!response.ok) throw new UploadError(refusal(response.status, json));
    onProgress(100);

    return json;
}

export function initAppReleaseUpload(form = document.querySelector('[data-app-release-upload]')) {
    if (!form) return;
    const button = form.querySelector('[data-upload-button]');
    const progress = form.querySelector('[data-upload-progress]');
    const status = form.querySelector('[data-upload-status]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const file = form.elements.file.files[0];
        if (!file) return;
        if (!/\.apk$/i.test(file.name)) {
            status.textContent = 'Choose the .apk file.';
            return;
        }
        button.disabled = true;
        progress.classList.remove('hidden');
        progress.value = 0;
        status.className = 'text-sm text-muted';
        status.textContent = `Uploading ${file.name} (${(file.size / 1048576).toFixed(1)} MB)…`;
        try {
            const answer = await uploadRelease({
                buffer: await file.arrayBuffer(),
                chunkUrl: form.dataset.chunkUrl,
                storeUrl: form.dataset.storeUrl,
                chunkSize: Number(form.dataset.chunkSize) || 1048576,
                csrf,
                fields: { notes_en: form.elements.notes_en.value, notes_hi: form.elements.notes_hi.value, required: form.elements.required.checked },
                onProgress: (percent) => {
                    progress.value = percent;
                    if (percent >= 95) status.textContent = 'Checking the APK…';
                },
            });
            status.textContent = 'Checked. Opening the list…';
            window.location.assign(answer.redirect || window.location.href);
        } catch (error) {
            status.className = 'text-sm text-danger';
            status.textContent = error instanceof UploadError ? error.message : 'Something went wrong. Reload the page and upload again.';
            button.disabled = false;
            progress.classList.add('hidden');
        }
    });
}
