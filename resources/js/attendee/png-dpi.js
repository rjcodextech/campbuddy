// canvas.toBlob() writes PNGs with no resolution metadata, so an image
// that is genuinely print-resolution opens in an editor or print dialog
// as "72 DPI" (or unspecified) and prints at the wrong physical size.
// This stamps the real DPI into the file: a `pHYs` chunk (pixels per
// metre, PNG spec §11.3.5.3) inserted straight after the IHDR header
// chunk, replacing any existing one. Pixels are untouched.

const SIGNATURE_LENGTH = 8;
const METRES_PER_INCH = 0.0254;

let crcTable = null;

function crc32(bytes) {
  if (!crcTable) {
    crcTable = new Uint32Array(256);
    for (let n = 0; n < 256; n++) {
      let c = n;
      for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
      crcTable[n] = c >>> 0;
    }
  }

  let crc = 0xffffffff;
  for (const byte of bytes) crc = crcTable[(crc ^ byte) & 0xff] ^ (crc >>> 8);
  return (crc ^ 0xffffffff) >>> 0;
}

function pHYsChunk(dpi) {
  const perMetre = Math.round(dpi / METRES_PER_INCH);
  const chunk = new Uint8Array(4 + 4 + 9 + 4); // length, type, data, CRC
  const view = new DataView(chunk.buffer);

  view.setUint32(0, 9);
  chunk.set([0x70, 0x48, 0x59, 0x73], 4); // "pHYs"
  view.setUint32(8, perMetre); // pixels per unit, X
  view.setUint32(12, perMetre); // pixels per unit, Y
  view.setUint8(16, 1); // unit: metre
  view.setUint32(17, crc32(chunk.subarray(4, 17)));

  return chunk;
}

/**
 * Returns a copy of the PNG `blob` recording `dpi` as its resolution.
 * Falls back to the original blob if it isn't a PNG this can parse.
 */
export async function withPngDpi(blob, dpi) {
  try {
    const bytes = new Uint8Array(await blob.arrayBuffer());
    const view = new DataView(bytes.buffer);

    const chunks = [];
    for (let pos = SIGNATURE_LENGTH; pos < bytes.length; ) {
      const end = pos + 12 + view.getUint32(pos);
      const type = String.fromCharCode(...bytes.subarray(pos + 4, pos + 8));
      chunks.push({ type, bytes: bytes.subarray(pos, end) });
      pos = end;
    }

    if (chunks[0]?.type !== 'IHDR') return blob;

    return new Blob(
      [
        bytes.subarray(0, SIGNATURE_LENGTH),
        chunks[0].bytes,
        pHYsChunk(dpi),
        ...chunks.slice(1).filter((chunk) => chunk.type !== 'pHYs').map((chunk) => chunk.bytes),
      ],
      { type: 'image/png' }
    );
  } catch {
    return blob;
  }
}
