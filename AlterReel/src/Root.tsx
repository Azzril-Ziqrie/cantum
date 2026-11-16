import React from 'react';
import {Composition} from 'remotion';
import {Main} from './Main';
import {FPS, TOTAL} from './theme';

export const Root: React.FC = () => (
  <Composition id="AlterReel45" component={Main} durationInFrames={TOTAL} fps={FPS} width={1080} height={1350} />
);
