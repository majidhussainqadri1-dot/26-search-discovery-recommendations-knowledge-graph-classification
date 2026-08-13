(function () {
  'use strict';

  const KEY = 'sabri_file26_local_search_history_v1';
  const MAX = 50;
  const cfg = window.SabriFile26FutureConfig || {};

  function cleanQuery(value) {
    return String(value || '').replace(/\s+/g, ' ').trim().slice(0, 500);
  }

  function sensitive(query) {
    return /(?:\bcnic\b|\bpassport\b|\bphone\s*number\b|\bmobile\s*number\b|\bsuicid|\bself[ -]?harm|شناختی\s*کارڈ|پاسپورٹ|فون\s*نمبر|خودکشی|مریض)/iu.test(query);
  }

  function read() {
    try {
      const parsed = JSON.parse(window.localStorage.getItem(KEY) || '[]');
      return Array.isArray(parsed) ? parsed.slice(-MAX) : [];
    } catch (error) {
      return [];
    }
  }

  function write(items) {
    try {
      window.localStorage.setItem(KEY, JSON.stringify(items.slice(-MAX)));
      return true;
    } catch (error) {
      return false;
    }
  }

  async function api(path, options) {
    if (!cfg.restUrl || !cfg.nonce || !cfg.loggedIn) {
      throw new Error('Authenticated File 26 Future REST configuration is unavailable.');
    }
    const response = await fetch(String(cfg.restUrl) + path, Object.assign({
      credentials: 'same-origin',
      headers: {'Accept': 'application/json', 'X-WP-Nonce': cfg.nonce}
    }, options || {}));
    const body = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(body.message || 'Request failed');
    return body;
  }

  const history = {
    policy: 'local_first',
    record(query, meta) {
      const q = cleanQuery(query);
      if (!q) return false;
      const items = read();
      items.push({
        query: q,
        searched_at: new Date().toISOString(),
        mode: String(meta && meta.mode || '').slice(0, 40),
        local_only: true
      });
      return write(items);
    },
    list() {
      return read().slice().reverse();
    },
    clear() {
      try {
        window.localStorage.removeItem(KEY);
        return true;
      } catch (error) {
        return false;
      }
    },
    async syncOptIn() {
      const all = read().slice(-50);
      const items = all.filter((item) => !sensitive(cleanQuery(item.query)));
      const accepted = [];
      for (const item of items) {
        const q = cleanQuery(item.query);
        if (!q) continue;
        await api('future/search-history', {
          method: 'POST',
          headers: {'Accept':'application/json','Content-Type':'application/json','X-WP-Nonce':cfg.nonce},
          body: JSON.stringify({sync_opt_in: true, q: q})
        });
        accepted.push(q);
      }
      return {synced: accepted.length, sensitive_skipped: all.length - items.length};
    }
  };

  // Merely loading this script never sends history to the network.
  window.SabriFile26Future = Object.assign({}, window.SabriFile26Future || {}, {searchHistory: history});
})();
