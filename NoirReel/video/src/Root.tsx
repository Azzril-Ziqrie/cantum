import React from 'react';
import {Composition} from 'remotion';
import {Main} from './Main';
import {FPS, H, TOTAL, W} from './theme';

export const Root: React.FC = () => (
  <>
    <Composition id="NoirReel" component={Main} durationInFrames={TOTAL} fps={FPS} width={W} height={H} />
    <Composition id="NoirReel45" component={Main} durationInFrames={TOTAL} fps={FPS} width={1080} height={1350} />
    <Composition id="NoirReel43" component={Main} durationInFrames={TOTAL} fps={FPS} width={1440} height={H} />
  </>
);
