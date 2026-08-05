(function () {
  'use strict';

  const cfg = window.SabriFile26 || {};
  const root = document;
  const escapeHtml = (value) => String(value || '').replace(/[&<>'"]/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
  const newId = () => (window.crypto && window.crypto.randomUUID ? window.crypto.randomUUID() : String(Date.now()) + '-' + Math.random());

  async function api(path, options) {
    const response = await fetch(String(cfg.restUrl || '') + path, Object.assign({
      credentials: 'same-origin',
      headers: {'Accept': 'application/json', 'X-WP-Nonce': cfg.nonce || ''}
    }, options || {}));
    const body = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(body.message || (cfg.strings && cfg.strings.error) || 'Request failed');
    return body;
  }

  function announce(message, isError) {
    const live = root.querySelector('[data-f26-live]');
    if (!live) return;
    live.textContent = String(message || '');
    live.classList.toggle('sabri-f26-state--error', Boolean(isError));
  }

  root.querySelectorAll('[data-f26-suggest]').forEach((input) => {
    let timer;
    let controller;
    const close = () => {
      const host = input.closest('.sabri-f26__search-field');
      const old = host && host.querySelector('.sabri-f26__suggestions');
      if (old) old.remove();
      input.setAttribute('aria-expanded', 'false');
    };
    input.addEventListener('keydown', (event) => { if (event.key === 'Escape') close(); });
    input.addEventListener('input', () => {
      window.clearTimeout(timer);
      close();
      const value = input.value.trim();
      const host = input.closest('.sabri-f26__search-field');
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
          input.setAttribute('aria-expanded', 'true');
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
    const actionId = newId();
    button.textContent = (cfg.strings && cfg.strings.working) || 'Working…';
    try {
      await api('feedback', {
        method: 'POST',
        headers: {'Accept':'application/json','Content-Type':'application/json','X-WP-Nonce':cfg.nonce || ''},
        body: JSON.stringify({
          item_key: button.getAttribute('data-object-key'),
          type: button.getAttribute('data-f26-feedback'),
          scope_key: button.getAttribute('data-scope-key') || '',
          idempotency_key: (window.crypto && window.crypto.randomUUID ? actionId : actionId),
          context: button.getAttribute('data-context') || 'discover'
        })
      });
      button.textContent = (cfg.strings && cfg.strings.done) || 'Saved';
      button.setAttribute('aria-pressed', 'true');
      const type = button.getAttribute('data-f26-feedback');
      const card = button.closest('.sabri-f26-card');
      if (card && ['not_interested', 'hide_item', 'hide_author', 'hide_topic'].includes(type)) {
        card.hidden = true;
        const live = root.querySelector('[data-f26-live]');
        if (live) {
          live.textContent = '';
          const text = document.createElement('span');
          text.textContent = (cfg.strings && cfg.strings.done) || 'Saved';
          const undo = document.createElement('button');
          undo.type = 'button';
          undo.className = 'sabri-f26__action';
          undo.textContent = (cfg.strings && cfg.strings.undo) || 'Undo';
          undo.addEventListener('click', async () => {
            undo.disabled = true;
            try {
              await api('feedback', {
                method: 'POST',
                headers: {'Accept':'application/json','Content-Type':'application/json','X-WP-Nonce':cfg.nonce || ''},
                body: JSON.stringify({type:'undo', item_key:button.getAttribute('data-object-key'), idempotency_key:newId(), undo_idempotency_key:actionId})
              });
              card.hidden = false;
              live.textContent = (cfg.strings && cfg.strings.done) || 'Saved';
            } catch (error) {
              announce(error.message, true);
            }
          });
          live.append(text, document.createTextNode(' '), undo);
        }
      }
    } catch (error) {
      button.textContent = original;
      button.removeAttribute('aria-pressed');
      announce(error.message, true);
    } finally {
      button.disabled = false;
    }
  });

  root.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-f26-personalization]');
    if (!button) return;
    event.preventDefault();
    if (button.disabled) return;
    const action = button.getAttribute('data-f26-personalization');
    const map = {
      'consent-on': ['personalization/consent', {consent:true}],
      'consent-off': ['personalization/consent', {consent:false}],
      'reset': ['personalization/reset', {}],
      'opt-out': ['personalization/opt-out', {}]
    };
    if (!map[action]) return;
    button.disabled = true;
    const original = button.textContent;
    button.textContent = (cfg.strings && cfg.strings.working) || 'Working…';
    try {
      await api(map[action][0], {
        method:'POST',
        headers:{'Accept':'application/json','Content-Type':'application/json','X-WP-Nonce':cfg.nonce || ''},
        body:JSON.stringify(map[action][1])
      });
      announce((cfg.strings && cfg.strings.done) || 'Saved', false);
      window.location.reload();
    } catch (error) {
      button.textContent = original;
      button.disabled = false;
      announce(error.message, true);
    }
  });

  root.querySelectorAll('[data-f26-interests]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const input = form.querySelector('[name="interests"]');
      const button = form.querySelector('button[type="submit"]');
      const interests = String(input && input.value || '').split(',').map((value) => value.trim()).filter(Boolean).slice(0, 50);
      if (button) button.disabled = true;
      try {
        await api('personalization/interests', {
          method:'POST',
          headers:{'Accept':'application/json','Content-Type':'application/json','X-WP-Nonce':cfg.nonce || ''},
          body:JSON.stringify({interests:interests})
        });
        announce((cfg.strings && cfg.strings.done) || 'Saved', false);
        window.location.reload();
      } catch (error) {
        announce(error.message, true);
        if (button) button.disabled = false;
      }
    });
  });
})();
