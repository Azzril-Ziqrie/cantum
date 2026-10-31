export const W = 1080;
export const H = 1920;
export const FPS = 30;

export const INK = '#0b0b0b';
export const MUTED = '#8a8a8a';
export const PAPER = '#f1f1f1';

// Scene timing, in frames at 30 fps (30 seconds in total). K stretches every animation so the pace stays calm;
// the scene lengths are the original 20 s storyboard times K (75, 105, 105, 105, 135, 75 frames).
export const K = 1.5;
export const SCENES = {
  intro: {from: 0, dur: 112},
  scan: {from: 112, dur: 158},
  order: {from: 270, dur: 158},
  pay: {from: 428, dur: 157},
  served: {from: 585, dur: 203},
  outro: {from: 788, dur: 112},
} as const;
export const TOTAL = 900;

export const FONT =
  '-apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", "Helvetica Neue", Helvetica, Arial, sans-serif';
export const COMIC_FONT = '"Comic Sans MS", "Chalkboard SE", "Marker Felt", sans-serif';

export const TABLE_RATIO = 480 / 378;   // table-lines.png is 480 x 378
