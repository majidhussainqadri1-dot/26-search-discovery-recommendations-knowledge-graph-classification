(function () {
  'use strict';

  const cfg = window.SabriFile26 || {};
  const root = document;
  const escapeHtml = (value) => String(value || '').replace(/[&<>'"]/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));

  async function api(path, options) {
    const response = await fetch(String(cfg.restUrl || '') + path, Object.assign({
      credentials: 'same-origin',
      headers: {'Accept': 'application/json', 'X-WP-Nonce': cfg.nonce || ''}
    }, options || {}));
    const body = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(body.message || (cfg.strings && cfg.strings.error) || 'Request failed');
    return body;
  }

  root.querySelectorAll('[data-f26-suggest]').forEach((input) => {
    let timer;
    let controller;
    input.addEventListener('input', () => {
      window.clearTimeout(timer);
      const value = input.value.trim();
      const host = input.closest('.sabri-f26__search-field');
      const old = host && host.querySelector('.sabri-f26__suggestions');
      if (old) old.remove();
      if (value.length < 2 || !host) return;
      timer = window.setTimeout(async () => {
        if (controller) controller.abort();
        controller = new AbortController();
        try {
          const data = await api('suggest?q=' + encodeURIComponent(value), {signal: controller.signal});
          const suggestions = Array.isArray(data.suggestions) ? data.suggestions : [];
          if (!suggestions.length) return;
          const list = document.createElement('ul');
          list.className = 'sabri-f26__suggestions';
          list.setAttribute('role', 'listbox');
          list.innerHTML = suggestions.map((item) => '<li role="option"><a href="' + escapeHtml(item.url) + '">' + escapeHtml(item.label) + '</a></li>').join('');
          host.appendChild(list);
        } catch (error) {
          if (error.name !== 'AbortError') console.warn('File 26 suggest:', error.message);
        }
      }, 220);
    });
  });

  root.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-f26-feedback]');
    if (!button) return;
    event.preventDefault();
    if (button.disabled) return;
    button.disabled = true;
    const original = button.textContent;
    button.textContent = (cfg.strings && cfg.strings.working) || 'Working…';
    try {
      await api('feedback', {
        method: 'POST',
        headers: {'Accept':'application/json','Content-Type':'application/json','X-WP-Nonce':cfg.nonce || ''},
        body: JSON.stringify({
          item_key: button.getAttribute('data-object-key'),
          type: button.getAttribute('data-f26-feedback'),
          idempotency_key: (window.crypto && window.crypto.randomUUID ? window.crypto.randomUUID() : String(Date.now()) + '-' + Math.random()),
          context: button.getAttribute('data-context') || 'discover'
        })
      });
      button.textContent = (cfg.strings && cfg.strings.done) || 'Saved';
      button.setAttribute('aria-pressed', 'true');
    } catch (error) {
      button.textContent = original;
      button.removeAttribute('aria-pressed');
      window.alert(error.message);
    } finally {
      button.disabled = false;
    }
  });
})();
