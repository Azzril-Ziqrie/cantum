export const W = 1920;
export const H = 1080;
export const FPS = 30;

export const INK = '#0a0a0a';
export const MUTED = '#8c8c8c';
export const LINE = '#e6e6e6';
export const FONT = '"Helvetica Neue", Helvetica, Arial, system-ui, sans-serif';

// Foto MacBook (4340x2860). Layar sudah dibuat transparan oleh tools/make-screen.py; area layar di bawah ini
// diukur dari piksel hijau aslinya (tools/screen-bbox.txt).
export const LAPTOP = {file: 'macbook.png', w: 4340, h: 2860, screen: {x: 451, y: 314, w: 3436, h: 2232}, notchH: 63};
export const K = 0.30;   // skala laptop di kanvas
export const LAPTOP_X = (W - LAPTOP.w * K) / 2;
export const LAPTOP_Y = 92;

// Rekaman browser 1440x936 (rasio layar MacBook). Detik awal/akhir = potongan dari rekaman mentah.
import segmentsJson from './segments.json';
export type Segment = {src: string; from: number; to: number; rate: number; num: string; label: string; fadeIn: boolean};
export const SEGMENTS: Segment[] = segmentsJson;
export const FADE = 9;        // frame crossfade antar adegan
export const OUTRO = 80;      // frame penutup

export const segFrames = (s: Segment) => Math.round(((s.to - s.from) / s.rate) * FPS);

export const timeline = (() => {
  let at = 0;
  return SEGMENTS.map((s) => {
    const start = s.fadeIn && at > 0 ? at - FADE : at;
    const len = segFrames(s);
    at = start + len;
    return {...s, start, len};
  });
})();
export const CONTENT_END = timeline[timeline.length - 1].start + timeline[timeline.length - 1].len;
export const TOTAL = CONTENT_END + OUTRO;
