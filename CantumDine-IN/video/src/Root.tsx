import React from 'react';
import {Composition} from 'remotion';
import {Main, MainProps} from './Main';
import {FPS, H, TOTAL, W} from './theme';

export const Root: React.FC = () => (
  <Composition
    id="CantumDineReels"
    component={Main}
    durationInFrames={TOTAL}
    fps={FPS}
    width={W}
    height={H}
    defaultProps={{sfx: 'sfx.mp3', voice: ''} satisfies MainProps}
  />
);
