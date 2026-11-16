import React from 'react';
import {AbsoluteFill, Img, Sequence, interpolate, spring, staticFile, useCurrentFrame, useVideoConfig} from 'remotion';
import {FONT, H, HEADLINE, INK, MUTED, PROJECTS, SCENES, SOFT, W} from './theme';

const clamp = {extrapolateLeft: 'clamp', extrapolateRight: 'clamp'} as const;

/** Translucent top bar, like .bar.site on the home page. */
const Bar: React.FC = () => (
  <div style={{position: 'absolute', top: 0, left: 0, right: 0, height: 120, padding: '0 48px', display: 'flex', alignItems: 'center',
    justifyContent: 'space-between', fontFamily: FONT, fontSize: 32, color: INK, background: 'rgba(255,255,255,.72)', zIndex: 10}}>
    <span style={{textTransform: 'uppercase'}}>Cantum Studio</span>
    <span style={{display: 'flex', gap: 36}}><span>Info</span><span>Index</span><span>Demo</span><span>Contact</span></span>
  </div>
);

const Fade: React.FC<{dur: number; children: React.ReactNode}> = ({dur, children}) => {
  const f = useCurrentFrame();
  return <AbsoluteFill style={{opacity: interpolate(f, [0, 8, dur - 8, dur], [0, 1, 1, 0], clamp)}}>{children}</AbsoluteFill>;
};

/** Headline revealed word by word. */
const Open: React.FC = () => {
  const f = useCurrentFrame();
  const words = HEADLINE.split(' ');
  return (
    <Fade dur={SCENES.open.dur}>
      <div style={{position: 'absolute', left: 64, right: 64, top: 520, fontFamily: FONT, fontSize: 84, lineHeight: 1.12, letterSpacing: '-0.04em', color: INK}}>
        {words.map((w, i) => {
          const p = interpolate(f, [8 + i * 3, 8 + i * 3 + 14], [0, 1], clamp);
          return <span key={i} style={{display: 'inline-block', marginRight: '0.28em', opacity: p, transform: `translateY(${(1 - p) * 24}px)`, filter: `blur(${(1 - p) * 8}px)`}}>{w}</span>;
        })}
      </div>
    </Fade>
  );
};

/** One project card per beat: cover keeps its own proportions, caption "[03] TITLE" under it. */
const Card: React.FC<{i: number}> = ({i}) => {
  const f = useCurrentFrame();
  const {fps} = useVideoConfig();
  const p = PROJECTS[i];
  const dur = SCENES.work.dur / PROJECTS.length;
  const s = spring({frame: f - 4, fps, config: {damping: 200}});
  const out = interpolate(f, [dur - 10, dur], [1, 0], clamp);
  const w = p.w > p.h ? 940 : 720;
  const h = w * (p.h / p.w);
  return (
    <AbsoluteFill style={{opacity: out}}>
      <div style={{position: 'absolute', left: (W - w) / 2, top: (H - h) / 2 - 40, width: w, opacity: s, transform: `translateY(${(1 - s) * 80}px) scale(${0.96 + 0.04 * s})`}}>
        <div style={{width: w, height: h, background: SOFT, overflow: 'hidden'}}>
          <Img src={staticFile(p.file)} style={{width: '100%', height: '100%', transform: `scale(${1 + f * 0.0007})`}} />
        </div>
        <div style={{display: 'flex', gap: 24, marginTop: 28, fontFamily: FONT, fontSize: 36, color: INK}}>
          <span style={{color: MUTED, fontVariantNumeric: 'tabular-nums'}}>[{p.n}]</span>
          <span>{p.title}</span>
        </div>
      </div>
    </AbsoluteFill>
  );
};

const Work: React.FC = () => {
  const dur = SCENES.work.dur / PROJECTS.length;
  return (
    <>
      {PROJECTS.map((_, i) => (
        <Sequence key={i} from={i * dur} durationInFrames={dur}><Card i={i} /></Sequence>
      ))}
    </>
  );
};

/** The two links under the grid: demo + Cantum OnePay, then the URL. */
const Close: React.FC = () => {
  const f = useCurrentFrame();
  const line = (t: string, at: number, top: number) => {
    const p = interpolate(f, [at, at + 16], [0, 1], clamp);
    return (
      <div style={{position: 'absolute', left: 64, top, fontFamily: FONT, fontSize: 64, letterSpacing: '-0.03em', color: INK, opacity: p, transform: `translateX(${(1 - p) * -30}px)`}}>
        {t} →
      </div>
    );
  };
  return (
    <Fade dur={SCENES.close.dur}>
      {line('Demo your website now', 8, 700)}
      {line('Cantum OnePay', 26, 820)}
      <div style={{position: 'absolute', left: 64, bottom: 160, fontFamily: FONT, fontSize: 44, color: MUTED, opacity: interpolate(f, [44, 60], [0, 1], clamp)}}>
        cantum-studio.site
      </div>
    </Fade>
  );
};

export const Main: React.FC = () => (
  <AbsoluteFill style={{background: '#fff'}}>
    <Sequence from={SCENES.open.from} durationInFrames={SCENES.open.dur}><Open /></Sequence>
    <Sequence from={SCENES.work.from} durationInFrames={SCENES.work.dur}><Work /></Sequence>
    <Sequence from={SCENES.close.from} durationInFrames={SCENES.close.dur}><Close /></Sequence>
    <Bar />
  </AbsoluteFill>
);
