import { Capacitor, registerPlugin } from '@capacitor/core';
import { App } from '@capacitor/app';

/** True inside the WaterHub Android app, false in a normal browser. */
export const isNative = () => Capacitor.isNativePlatform();

/** Native background location (keeps working with the screen locked, via a foreground-service notification). */
export const BackgroundGeolocation = registerPlugin('BackgroundGeolocation');

export function initNative() {
  if (!isNative()) return;
  document.documentElement.classList.add('native-app');
  // Android back button: go back in the app, exit on the first screen.
  App.addListener('backButton', ({ canGoBack }) => {
    if (canGoBack && window.history.length > 1) window.history.back();
    else App.exitApp();
  });
}
