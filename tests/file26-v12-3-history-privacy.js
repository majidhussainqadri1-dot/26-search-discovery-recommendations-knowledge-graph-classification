'use strict';
/**
 * Isolated browser-like privacy test for FUT-17.
 * No real network, no WordPress credentials and no external dependencies.
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

async function main() {
  const saved = new Map();
  const requests = [];
  const storage = {
    getItem(key) { return saved.has(key) ? saved.get(key) : null; },
    setItem(key, value) { saved.set(key, value); },
    removeItem(key) { saved.delete(key); }
  };
  const win = {
    SabriFile26FutureConfig: {
      restUrl: '/wp-json/sabri-search/v1/',
      nonce: 'disposable-test-only',
      loggedIn: true
    },
    localStorage: storage
  };
  const script = fs.readFileSync(path.join(__dirname, '..', 'assets/js/file26-future.js'), 'utf8');
  vm.runInNewContext(script, {
    window: win,
    fetch: async (url, options) => {
      requests.push({url: String(url), body: JSON.parse(options.body)});
      return {ok: true, json: async () => ({saved: true})};
    },
    Date, Promise, JSON, String, console
  }, {filename: 'file26-future.js', timeout: 1000});

  assert.equal(requests.length, 0, 'Loading script never makes a network request');
  const history = win.SabriFile26Future.searchHistory;
  const privateQueries = [
    'learner@example.com',
    'learner＠example.com',
    '+92 300 123 4567',
    '۰۳۱۲ ۱۲۳۴۵۶۷',
    '12345-1234567-1',
    'private patient medical record'
  ];
  for (const query of privateQueries) {
    assert.equal(history.record(query), true, 'Local recording is user-controlled');
  }
  assert.equal(history.record('history of homeopathy'), true);
  assert.equal(requests.length, 0, 'Local recording is not network sync');
  assert.equal(history.list().length, 7, 'Local history contains recorded items before explicit opt-in');

  const result = await history.syncOptIn();
  assert.equal(result.synced, 1, 'Only non-sensitive search is eligible for explicit server sync');
  assert.equal(result.sensitive_skipped, 6, 'All six sensitive variants must stay local');
  assert.equal(requests.length, 1, 'Only one network call is permitted');
  assert.equal(requests[0].body.q, 'history of homeopathy');
  assert.equal(requests[0].body.sync_opt_in, true);
  assert.equal(requests[0].url, '/wp-json/sabri-search/v1/future/search-history');

  assert.equal(history.clear(), true, 'User can clear local history');
  assert.equal(history.list().length, 0, 'History erasure removes local entries');

  console.log('PASS: Local-first FUT-17 history blocks sensitive sync before network; privacy regression (7 queries)');
}
main().catch((error) => {
  console.error('FAIL: File26 local-history privacy:', error);
  process.exit(1);
});
