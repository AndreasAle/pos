/*
 * Alpine.js, bundled with the app instead of loaded from a CDN.
 *
 * The CDN tag pointed at "alpinejs@3.x.x": any new 3.x release reached every
 * page (the register included) without a deploy, and a slow or blocked CDN left
 * x-cloak screens blank. Pinned in package.json; upgrade deliberately.
 *
 * Loaded as a module script, which runs after the page's inline scripts, so
 * component functions such as posApp() exist before Alpine starts.
 */
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();
