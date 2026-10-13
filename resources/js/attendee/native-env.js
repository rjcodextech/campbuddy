// What the installed app build can do, asked before the page tries (the Android
// shell answers through its AppEnv plugin). Used by native-bridge.js and
// organizer-native.js; loaded only inside the app.

import { nativePlatform } from './platform.js';

/**
 * Whether push registration may be attempted. An Android build made without
 * Firebase files has no push; asking anyway fails (or, in older builds, closed
 * the app). iOS builds without Firebase simply get no usable token, which the
 * server drops, so they are allowed to try.
 */
export async function pushSupported() {
  if (nativePlatform() !== 'android') return true;

  const cap = window.Capacitor;
  const env = cap?.Plugins?.AppEnv ?? cap?.registerPlugin?.('AppEnv');
  if (!env?.info) return false;

  try {
    return Boolean((await env.info()).firebase);
  } catch {
    return false;
  }
}
