import {Config} from '@remotion/cli/config';

// Use the Chrome that is already installed instead of downloading Remotion's own browser.
Config.setBrowserExecutable('/usr/bin/google-chrome');
Config.setOverwriteOutput(true);

// Output that plays everywhere (phones, WhatsApp, Instagram) and keeps thin lines and dither dots sharp:
// lossless PNG frames, standard yuv420p with BT.709 colour, and a high-quality x264 setting.
Config.setVideoImageFormat('png');
Config.setPixelFormat('yuv420p');
Config.setColorSpace('bt709');
Config.setCrf(14);
Config.setX264Preset('slow');
