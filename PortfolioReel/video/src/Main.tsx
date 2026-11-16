import React from 'react';
import {AbsoluteFill, Easing, Img, Sequence, Video, interpolate, spring, staticFile, useCurrentFrame, useVideoConfig} from 'remotion';
import {ALTER_FRAMES, ALTER_HOLD, BEAT, CLOSE, FONT, H, INK, LAPTOP, MUTED, SOFT, START, TILES, TRANS, W} from './theme';

const clamp = {extrapolateLeft: 'clamp', extrapolateRight: 'clamp'} as const;
const smooth = (t: number) => t * t * (3 - 2 * t);

const LAST = TILES.length - 1;
const ALT = TILES.findIndex((t) => t.laptop);
/** How long each project holds, and the frame at which project i is centred. Alterthat holds longest. */
const dur = (i: number) => (i === ALT ? ALTER_HOLD : BEAT);
const at = (i: number) => START + TILES.slice(0, i).reduce((s, _, k) => s + dur(k), 0);

/** The one scroll position everything hangs off (list and images), 0 = first project, LAST = Alterthat.
 *  Each hop is a critically damped spring, so it settles with no overshoot and the next hop picks up from where the
 *  previous one ended (the same idea as re-targeting a spring from its current value). */
const usePos = () => {
  const f = useCurrentFrame();
  const {fps} = useVideoConfig();
  let pos = 0;
  for (let i = 1; i <= LAST; i++) {
    pos += spring({frame: f - (at(i) - TRANS / 2), fps, config: {damping: 200}, durationInFrames: TRANS});
  }
  return pos;
};

// ---- list (left) -----------------------------------------------------------------------------------------------
const ROW = 96;
const ALTER_GAP = 44;
const cum = TILES.map((_, i) => i * ROW + (i > ALT ? ALTER_GAP : 0));
const FOCUS_Y = 960;
const ALTER_LIFT = 330;

const List: React.FC<{pos: number}> = ({pos}) => {
  const f = useCurrentFrame();
  const aAlt = smooth(Math.max(0, Math.min(1, 1 - Math.abs(ALT - pos))));
  const focusY = FOCUS_Y - ALTER_LIFT * aAlt;
  const cumPos = interpolate(pos, TILES.map((_, i) => i), cum, clamp);
  return (
    <>
      {TILES.map((t, i) => {
        const d = Math.abs(i - pos);
        const near = smooth(Math.max(0, Math.min(1, 1 - d * 1.6)));         // 1 only while this project is the active one
        const fade = interpolate(d, [0, 1, 2, 3], [1, 0.34, 0.2, 0.1], clamp) * (i === ALT ? 1 : 1 - aAlt);   // the rest step aside while Alterthat has the screen
        const intro = interpolate(f, [i * 3, i * 3 + 22], [0, 1], clamp);
        const big = i === ALT ? 1 + 0.35 * near : 1;                        // Alterthat grows a little when it is its turn
        const y = focusY + (cum[i] - cumPos);
        const name = t.title;
        const text: React.CSSProperties = {fontSize: 24, textTransform: 'uppercase', whiteSpace: 'nowrap', letterSpacing: 0};
        return (
          <div key={t.n} style={{position: 'absolute', left: 64, top: y, height: 0, width: 360, opacity: fade * intro, transform: `translateY(${(1 - intro) * 18}px) scale(${big})`, transformOrigin: '0 50%', fontFamily: FONT, color: INK}}>
            <span style={{position: 'absolute', left: 0, top: -12, fontSize: 18, lineHeight: '24px', color: MUTED, fontVariantNumeric: 'tabular-nums', width: 40}}>{t.n}</span>
            <span style={{position: 'absolute', left: 48, top: -14, lineHeight: '28px', ...text, fontWeight: 400, opacity: 1 - near}}>{name}</span>
            <span style={{position: 'absolute', left: 48, top: -14, lineHeight: '28px', ...text, fontWeight: 700, opacity: near}}>{name}</span>
          </div>
        );
      })}
    </>
  );
};

// ---- panels (right) --------------------------------------------------------------------------------------------
const PANEL_X = 440;
const PANEL_W = 592;
const STRIDE = 1100;
const LAPTOP_W = 1000;

/** Still project: slow drift, and a cross-fade to the second image part-way through its time on screen. */
const Shot: React.FC<{i: number; w: number; h: number}> = ({i, w, h}) => {
  const f = useCurrentFrame();
  const t = TILES[i];
  if (t.video) {
    return <Video src={staticFile(t.video)} muted loop style={{width: w, height: h, objectFit: 'cover'}} />;
  }
  const local = f - at(i);
  const swap = interpolate(local, [dur(i) * 0.45, dur(i) * 0.45 + 30], [0, 1], clamp);
  const drift = 1.03 + Math.max(0, local + 40) * 0.00016;
  const style: React.CSSProperties = {position: 'absolute', inset: 0, width: '100%', height: '100%', objectFit: 'cover', transform: `scale(${drift})`};
  return (
    <div style={{position: 'relative', width: w, height: h, overflow: 'hidden', background: SOFT}}>
      <Img src={staticFile(t.imgs[0])} style={style} />
      <Img src={staticFile(t.imgs[1])} style={{...style, opacity: swap}} />
    </div>
  );
};

/** The MacBook with the Alterthat screen recording on it. It starts at the column width and opens up to nearly the full
 *  width of the frame when it is Alterthat's turn, then pushes in toward the screen. */
const Laptop: React.FC<{w: number; f: number}> = ({w, f}) => {
  const h = (w * LAPTOP.h) / LAPTOP.w;
  const K = w / LAPTOP.w;
  const scr = {x: LAPTOP.screen.x * K, y: LAPTOP.screen.y * K, w: LAPTOP.screen.w * K, h: LAPTOP.screen.h * K};
  const bar = LAPTOP.notchH * K;
  const t0 = at(ALT) - 40;
  const tEnd = at(ALT) + ALTER_HOLD;
  const idx = 1 + Math.floor(interpolate(f, [t0, tEnd], [0, ALTER_FRAMES - 1], clamp));
  const zoom = interpolate(f, [at(ALT) + 70, tEnd - 20], [1, 1.25], {...clamp, easing: Easing.inOut(Easing.cubic)});
  const u = K / 0.3;
  return (
    <div style={{position: 'relative', width: w, height: h, overflow: 'hidden'}}>
      <div style={{position: 'absolute', inset: 0, transform: `scale(${zoom})`, transformOrigin: `${scr.x + scr.w / 2}px ${scr.y + scr.h / 2}px`}}>
        <div style={{position: 'absolute', left: scr.x, top: scr.y, width: scr.w, height: scr.h, overflow: 'hidden', background: '#ececec'}}>
          <div style={{position: 'absolute', left: 0, right: 0, top: bar, bottom: 0, overflow: 'hidden'}}>
            <Img src={staticFile(`alter-frames/${String(idx).padStart(5, '0')}.jpg`)} style={{position: 'absolute', left: 0, top: 0, width: '100%', height: '100%', objectFit: 'cover'}} />
          </div>
          <div style={{position: 'absolute', left: 0, right: 0, top: 0, height: bar, background: 'rgba(250,250,250,.96)', display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: `0 ${16 * u}px`, fontSize: 11 * u, color: INK, letterSpacing: '-0.01em', fontFamily: FONT}}>
            <span style={{display: 'flex', gap: 15 * u}}><b>Chrome</b><span>File</span><span>Edit</span><span>View</span><span>History</span><span>Window</span></span>
            <span style={{display: 'flex', gap: 14 * u}}><span>Thu 12 Nov</span><span>13.15</span></span>
          </div>
        </div>
        <Img src={staticFile(LAPTOP.file)} style={{position: 'absolute', left: 0, top: 0, width: w, height: h}} />
      </div>
    </div>
  );
};

const Panels: React.FC<{pos: number}> = ({pos}) => {
  const f = useCurrentFrame();
  const intro = interpolate(f, [4, 30], [0, 1], {...clamp, easing: Easing.bezier(0.2, 0.9, 0.25, 1)});
  return (
    <>
      {TILES.map((t, i) => {
        const d = i - pos;
        const op = Math.max(0, Math.min(1, 1.5 - 2 * Math.abs(d)));
        if (op <= 0) return null;
        const a = t.laptop ? smooth(Math.max(0, Math.min(1, 1 - Math.abs(d)))) : 0;   // Alterthat opens up while it is the active one
        const w = t.laptop ? PANEL_W + (LAPTOP_W - PANEL_W) * a : PANEL_W;
        const h = w * t.ratio;
        const left = t.laptop ? PANEL_X + (40 - PANEL_X) * a : PANEL_X;
        const cy = FOCUS_Y + d * STRIDE + (t.laptop ? 100 * a : 0);
        const first = i === 0 ? intro : 1;
        return (
          <div key={t.n} style={{position: 'absolute', left, top: cy - h / 2 + (1 - first) * 70, width: w, opacity: op * first}}>
            {t.laptop ? <Laptop w={w} f={f} /> : <Shot i={i} w={w} h={h} />}
            <div style={{marginTop: 16, display: 'flex', gap: 18, fontFamily: FONT, fontSize: 22, color: MUTED, whiteSpace: 'nowrap'}}>
              {t.meta.map((m) => <span key={m}>{m}</span>)}
              {t.link && <span style={{color: INK}}>{t.link} ↗</span>}
            </div>
          </div>
        );
      })}
    </>
  );
};

// ---- chrome ----------------------------------------------------------------------------------------------------
/** Translucent top bar like the site; the counter follows the scroll position. */
const Bar: React.FC<{pos: number}> = ({pos}) => {
  const f = useCurrentFrame();
  const shown = Math.round(pos) + 1;
  const show = interpolate(f, [CLOSE, CLOSE + 20], [1, 0], clamp);
  return (
    <div style={{position: 'absolute', top: 0, left: 0, right: 0, height: 120, padding: '0 48px', display: 'flex', alignItems: 'center', justifyContent: 'space-between',
      fontFamily: FONT, fontSize: 32, color: INK, background: 'rgba(255,255,255,.72)', zIndex: 10, opacity: show}}>
      <span style={{textTransform: 'uppercase'}}>Cantum Studio</span>
      <span style={{fontVariantNumeric: 'tabular-nums', color: MUTED}}>[{String(shown).padStart(2, '0')}/{String(TILES.length).padStart(2, '0')}]</span>
    </div>
  );
};

const Stage: React.FC = () => {
  const f = useCurrentFrame();
  const pos = usePos();
  const dim = interpolate(f, [CLOSE, CLOSE + 24], [1, 0], clamp);
  return (
    <AbsoluteFill style={{opacity: dim}}>
      <Panels pos={pos} />
      <List pos={pos} />
      <Bar pos={pos} />
    </AbsoluteFill>
  );
};

const Close: React.FC = () => {
  const f = useCurrentFrame();
  const line = (txt: string, from: number, top: number, size: number, color: string) => {
    const p = interpolate(f, [from, from + 22], [0, 1], clamp);
    return <div style={{position: 'absolute', left: 64, top, fontFamily: FONT, fontSize: size, letterSpacing: '-0.04em', lineHeight: 1.05, color, opacity: p, transform: `translateY(${(1 - p) * 28}px)`, filter: `blur(${(1 - p) * 8}px)`}}>{txt}</div>;
  };
  return (
    <AbsoluteFill>
      {line('Cantum Studio', 6, 700, 150, INK)}
      {line('Web design studio in Bogor.', 24, 880, 54, INK)}
      {line('cantum-studio.site', 44, 1700, 44, MUTED)}
    </AbsoluteFill>
  );
};

export const Main: React.FC = () => (
  <AbsoluteFill style={{background: '#fff'}}>
    <Stage />
    <Sequence from={CLOSE} durationInFrames={180}><Close /></Sequence>
  </AbsoluteFill>
);
