'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

async function main() {
  const listeners = {};
  const requests = [];
  const timers = new Map();
  const attributes = new Map();
  let nextTimer = 0;
  let mobile = false;
  let progressBounds = {top: 120, bottom: 168};
  const reveals = [];
  const scrolls = [];
  const progress = {
    hidden: true,
    getBoundingClientRect: () => progressBounds,
    scrollIntoView: options => reveals.push(options.block),
  };
  const content = {
    contains: () => false,
    querySelectorAll: () => [],
    setAttribute: (key, value) => attributes.set(key, value),
    removeAttribute: key => attributes.delete(key),
    replaceChildren() {},
    focus() {},
  };
  const location = new URL('https://example.test/community');
  const document = {
    hidden: false,
    getElementById: id => ({communityContent: content, communityProgress: progress})[id] ?? null,
    querySelector: selector => selector === '.site-header'
      ? {getBoundingClientRect: () => ({bottom: 68})}
      : null,
    querySelectorAll: () => [],
    addEventListener: (name, listener) => { listeners[name] = listener; },
  };
  const source = fs.readFileSync(path.join(__dirname, '../assets/community.js'), 'utf8');
  vm.runInNewContext(source, {
    document, location, URL, AbortController, scrollY: 240, scrollTo: options => scrolls.push(options.top),
    window: {
      addEventListener: (name, listener) => { listeners[name] = listener; },
      matchMedia: () => ({matches: mobile}),
      visualViewport: {offsetTop: 0, height: 640},
    },
    history: {replaceState() {}, pushState() {}},
    DOMParser: class {
      parseFromString() {
        return {getElementById: () => ({childNodes: []}), querySelector: () => null};
      }
    },
    fetch: async (url, options) => {
      requests.push({url: String(url), cache: options.cache});
      return {text: async () => ''};
    },
    setTimeout: callback => { timers.set(++nextTimer, callback); return nextTimer; },
    clearTimeout: id => timers.delete(id),
  });

  assert.equal(timers.size, 0, 'An idle page does not schedule a reload');
  assert.deepEqual(requests, []);
  const link = {href: location.href + '?page=2', closest: () => null};
  let intercepted = false;
  listeners.click({target: {closest: () => link}, button: 0, preventDefault: () => { intercepted = true; }});
  assert(intercepted);
  assert.equal(progress.hidden, false);
  assert.equal(progress.textContent, 'Loading the Circle…');
  assert.deepEqual(reveals, []);
  await new Promise(setImmediate);
  assert.deepEqual(requests, [{url: link.href, cache: 'no-store'}]);
  assert.equal(progress.hidden, true);
  assert.equal(timers.size, 0, 'Navigation does not restart a periodic reload');

  document.hidden = true;
  listeners.visibilitychange();
  assert(attributes.has('inert'));
  mobile = true;
  progressBounds = {top: 660, bottom: 708};
  document.hidden = false;
  listeners.visibilitychange();
  assert.equal(progress.hidden, false);
  assert.equal(progress.textContent, 'Updating the Circle…');
  assert.deepEqual(reveals, ['center'], 'An offscreen indicator scrolls into view on mobile');
  await new Promise(setImmediate);
  assert(!attributes.has('inert'));
  assert.deepEqual(requests.at(-1), {url: location.href, cache: 'no-store'});
  assert.equal(requests.length, 2, 'Returning to the tab revalidates once');
  assert.equal(timers.size, 0, 'Revalidation does not schedule a reload');
  assert.equal(progress.hidden, true);
  assert.equal(scrolls.at(-1), 240, 'Tab revalidation restores the prior scroll position');

  progressBounds = {top: 85, bottom: 133};
  listeners.popstate({state: {community_scroll: 90}});
  await new Promise(setImmediate);
  assert.deepEqual(reveals, ['center'], 'An already visible mobile indicator does not move the page');
  assert.equal(scrolls.at(-1), 90, 'Back navigation restores the history scroll position');
  console.log('Circle navigation and tab revalidation passed without periodic reloads.');
}

main().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
