import React from 'react';
import {AbsoluteFill, Audio, Sequence, staticFile} from 'remotion';
import {Intro, Order, Outro, Pay, Scan, Served} from './Scenes';
import {PAPER, SCENES} from './theme';

export type MainProps = {
  /** Interaction sounds (no music), a file inside public/ made by tools/make_music.py. Empty = silent. */
  sfx: string;
  /** Optional narration, a file inside public/ (for example "voice-id.mp3"). Leave empty for none. */
  voice: string;
};

export const Main: React.FC<MainProps> = ({sfx, voice}) => (
  <AbsoluteFill
    style={{
      background: `${PAPER} radial-gradient(rgba(0,0,0,.07) 2px, transparent 2.6px) 0 0 / 18px 18px`,
    }}
  >
    <Sequence from={SCENES.intro.from} durationInFrames={SCENES.intro.dur}><Intro /></Sequence>
    <Sequence from={SCENES.scan.from} durationInFrames={SCENES.scan.dur}><Scan /></Sequence>
    <Sequence from={SCENES.order.from} durationInFrames={SCENES.order.dur}><Order /></Sequence>
    <Sequence from={SCENES.pay.from} durationInFrames={SCENES.pay.dur}><Pay /></Sequence>
    <Sequence from={SCENES.served.from} durationInFrames={SCENES.served.dur}><Served /></Sequence>
    <Sequence from={SCENES.outro.from} durationInFrames={SCENES.outro.dur}><Outro /></Sequence>
    {sfx ? <Audio src={staticFile(sfx)} volume={voice ? 0.6 : 1} /> : null}   {/* a little lower under a voice */}
    {voice ? <Audio src={staticFile(voice)} /> : null}
  </AbsoluteFill>
);
