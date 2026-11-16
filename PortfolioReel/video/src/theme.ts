export const W = 1080;
export const H = 1920;
export const FPS = 30;
export const TOTAL = 1350; // 45 s

export const INK = '#0a0a0a';
export const MUTED = '#8c8c8c';
export const SOFT = '#eeeeee';
export const FONT = '"Helvetica Neue", Helvetica, Arial, system-ui, sans-serif';

/** Each project holds for BEAT frames; the list and the images scroll to the next one in a TRANS-frame move. */
export const START = 30;
export const BEAT = 120;
export const TRANS = 36;
/** Alterthat opens the list and is held longer; the last project holds until the close card takes over. */
export const ALTER_HOLD = 390;
export const CLOSE = 1170;

export const LAPTOP = {file: 'macbook.png', w: 4340, h: 2860, screen: {x: 451, y: 314, w: 3436, h: 2232}, notchH: 63};
export const ALTER_FRAMES = 503;

type Tile = {n: string; title: string; name: string; meta: string[]; link?: string; imgs: string[]; video?: string; ratio: number; laptop?: boolean};

// Newest first: Alterthat, Noirlab, then the rest. Order of the array is the order of the list, top to bottom.
export const TILES: Tile[] = [
  {n: '01', title: 'ALTERTHAT', name: 'Alterthat', meta: ['Web', '2026'], link: 'alter-that.com', imgs: [], ratio: LAPTOP.h / LAPTOP.w, laptop: true},
  {n: '02', title: 'NOIRLAB COLLECTIVE', name: 'Noirlab Collective', meta: ['Web', '2026'], link: 'noirlabcolective.com', imgs: [], video: 'noir.mp4', ratio: 540 / 720},
  {n: '03', title: 'HARDPREAZE', name: 'HARDPREAZE', meta: ['Web', '2026'], imgs: ['hardpreaze-1.jpg', 'hardpreaze-2.jpg'], ratio: 1040 / 1300},
  {n: '04', title: 'DEEPDIMES', name: 'DEEPDIMES', meta: ['Web', '2026'], link: 'deepdimes.com', imgs: ['deepdimes-1.jpg', 'deepdimes-2.jpg'], ratio: 1300 / 1040},
  {n: '05', title: 'PASTPRESENTFURTHER', name: 'PASTPRESENTFURTHER', meta: ['Web', '2026'], link: 'pastpresentfurther.com', imgs: ['past-1.jpg', 'past-2.jpg'], ratio: 1300 / 1040},
  {n: '06', title: 'ALBRYCIA', name: 'Albrycia', meta: ['Web', '2026'], link: 'albrycia.com', imgs: ['albrycia-1.jpg', 'albrycia-2.jpg'], ratio: 1300 / 980},
  {n: '07', title: 'THE SIMPLE DAYS', name: 'The Simple Days', meta: ['Web', '2026'], link: 'thesimpledays.co', imgs: ['simple-1.jpg', 'simple-2.jpg'], ratio: 1300 / 1040},
];
