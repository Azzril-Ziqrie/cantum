export const W = 1080;
export const H = 1920;
export const FPS = 30;
export const TOTAL = 450; // 15 s

export const INK = '#0a0a0a';
export const MUTED = '#8c8c8c';
export const SOFT = '#eeeeee';
export const FONT = '"Helvetica Neue", Helvetica, Arial, system-ui, sans-serif';

export const HEADLINE =
  'CANTUM STUDIO is an end-to-end creative practice translating ideas into clear, intuitive visual experiences.';

// Same order as the home page (sort_order, year desc, id desc). Covers are copies of uploads/*.webp.
export const PROJECTS = [
  {n: '03', title: 'PASTPRESENTFURTHER', file: 'p4.webp', w: 2025, h: 2531},
  {n: '02', title: 'DEEPDIMES', file: 'p3.webp', w: 2025, h: 2531},
  {n: '01', title: 'HARDPREAZE', file: 'p2.webp', w: 2531, h: 2025},
] as const;

export const SCENES = {
  open: {from: 0, dur: 120},
  work: {from: 120, dur: 240},
  close: {from: 360, dur: 90},
} as const;
