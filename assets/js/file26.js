(function () {
  'use strict';

  const cfg = window.SabriFile26 || {};
  const root = document;
  const newId = () => (window.crypto && window.crypto.randomUUID ? window.crypto.randomUUID() : String(Date.now()) + '-' + Math.random());
  const safeSameOriginUrl = (value) => {
    try {
      const url = new URL(String(value || ''), window.location.origin);
      return url.origin === window.location.origin ? url.href : '';
    } catch (error) {
      return '';
    }
  };

  async function api(path, options) {
    const response = await fetch(String(cfg.restUrl || '') + path, Object.assign({
      credentials: 'same-origin',
      headers: {'Accept': 'application/json', 'X-WP-Nonce': cfg.nonce || ''}
    }, options || {}));
    const body = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(body.message || (cfg.strings && cfg.strings.error) || 'Request failed');
    return body;
  }

  function liveRegion(context) {
    const component = context && context.closest ? context.closest('.sabri-f26') : null;
    return (component || root).querySelector('[data-f26-live]');
  }

  function announce(message, isError, context) {
    const live = liveRegion(context);
    if (!live) return;
    live.textContent = String(message || '');
    live.classList.toggle('sabri-f26-state--error', Boolean(isError));
  }

  root.querySelectorAll('[data-f26-suggest]').forEach((input, inputIndex) => {
    let timer;
    let controller;
    let requestSequence = 0;
    let activeIndex = -1;
    let composing = false;
    const listId = input.getAttribute('aria-controls') || ('sabri-f26-suggestions-' + inputIndex);
    input.setAttribute('aria-controls', listId);
    input.setAttribute('aria-autocomplete', 'list');

    const currentList = () => {
      const host = input.closest('.sabri-f26__search-field');
      return host && host.querySelector('.sabri-f26__suggestions');
    };
    const options = () => Array.from((currentList() || {querySelectorAll: () => []}).querySelectorAll('[role="option"]'));
    const setActive = (index) => {
      const items = options();
      if (!items.length) {
        activeIndex = -1;
        input.removeAttribute('aria-activedescendant');
        return;
      }
      activeIndex = ((index % items.length) + items.length) % items.length;
      items.forEach((item, i) => item.setAttribute('aria-selected', i === activeIndex ? 'true' : 'false'));
      const active = items[activeIndex];
      if (!active.id) active.id = listId + '-option-' + activeIndex;
      input.setAttribute('aria-activedescendant', active.id);
      if (active.scrollIntoView) active.scrollIntoView({block: 'nearest'});
    };
    const close = () => {
      const old = currentList();
      if (old) old.remove();
      activeIndex = -1;
      input.setAttribute('aria-expanded', 'false');
      input.removeAttribute('aria-activedescendant');
    };
    const cancelPending = () => {
      window.clearTimeout(timer);
      requestSequence += 1;
      if (controller) controller.abort();
      controller = undefined;
      close();
    };
    const activateOption = (item) => {
      if (!item) return;
      const target = safeSameOriginUrl(item.getAttribute('data-url'));
      if (target) window.location.assign(target);
    };
    const scheduleSuggestions = () => {
      window.clearTimeout(timer);
      requestSequence += 1;
      if (controller) controller.abort();
      close();
      if (composing) return;
      const value = input.value.trim();
      const host = input.closest('.sabri-f26__search-field');
      if (value.length < 2 || !host) return;
      const sequence = requestSequence;
      timer = window.setTimeout(async () => {
        controller = new AbortController();
        try {
          const data = await api('suggest?q=' + encodeURIComponent(value), {signal: controller.signal});
          if (sequence !== requestSequence || input.value.trim() !== value || document.activeElement !== input || composing) return;
          const suggestions = Array.isArray(data.suggestions) ? data.suggestions : [];
          if (!suggestions.length) return;
          const list = document.createElement('ul');
          list.id = listId;
          list.className = 'sabri-f26__suggestions';
          list.setAttribute('role', 'listbox');
          suggestions.forEach((item, i) => {
            const target = safeSameOriginUrl(item && item.url);
            if (!target) return;
            const option = document.createElement('li');
            option.id = listId + '-option-' + i;
            option.setAttribute('role', 'option');
            option.setAttribute('aria-selected', 'false');
            option.setAttribute('data-url', target);
            option.textContent = String((item && item.label) || '');
            option.addEventListener('mousedown', (event) => event.preventDefault());
            option.addEventListener('click', () => activateOption(option));
            list.appendChild(option);
          });
          if (!list.children.length) return;
          host.appendChild(list);
          activeIndex = -1;
          input.setAttribute('aria-expanded', 'true');
        } catch (error) {
          if (error.name !== 'AbortError') console.warn('File 26 suggest:', error.message);
        }
      }, 220);
    };

    input.addEventListener('compositionstart', () => {
      composing = true;
      cancelPending();
    });
    input.addEventListener('compositionend', () => {
      composing = false;
      scheduleSuggestions();
    });

    input.addEventListener('keydown', (event) => {
      if (event.isComposing || composing || event.keyCode === 229) return;
      const items = options();
      if (event.key === 'Escape') {
        close();
      } else if (event.key === 'ArrowDown' && items.length) {
        event.preventDefault();
        setActive(activeIndex + 1);
      } else if (event.key === 'ArrowUp' && items.length) {
        event.preventDefault();
        setActive(activeIndex < 0 ? items.length - 1 : activeIndex - 1);
      } else if (event.key === 'Enter' && activeIndex >= 0 && items[activeIndex]) {
        event.preventDefault();
        activateOption(items[activeIndex]);
      }
    });

    input.addEventListener('input', (event) => {
      if (event.isComposing || composing) {
        cancelPending();
        composing = true;
        return;
      }
      scheduleSuggestions();
    });

    input.addEventListener('blur', () => {
      window.clearTimeout(timer);
      requestSequence += 1;
      if (controller) controller.abort();
      window.setTimeout(() => {
        if (!currentList() || !currentList().contains(document.activeElement)) close();
      }, 120);
    });
  });

  root.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-f26-feedback]');
    if (!button) return;
    event.preventDefault(); if (button.disabled) return; button.disabled = true;
    const original = button.textContent; const actionId = newId(); button.textContent = (cfg.strings && cfg.strings.working) || 'Working…';
    try {
      await api('feedback', {method:'POST',headers:{'Accept':'application/json','Content-Type':'application/json','X-WP-Nonce':cfg.nonce || ''},body:JSON.stringify({item_key:button.getAttribute('data-object-key'),type:button.getAttribute('data-f26-feedback'),scope_key:button.getAttribute('data-scope-key') || '',idempotency_key:actionId,context:button.getAttribute('data-context') || 'discover'})});
      button.textContent=(cfg.strings&&cfg.strings.done)||'Saved';button.setAttribute('aria-pressed','true');const type=button.getAttribute('data-f26-feedback');const card=button.closest('.sabri-f26-card');
      if(card&&['not_interested','hide_item','hide_author','hide_topic'].includes(type)){card.hidden=true;const live=liveRegion(button);if(live){live.textContent='';const text=document.createElement('span');text.textContent=(cfg.strings&&cfg.strings.done)||'Saved';const undo=document.createElement('button');undo.type='button';undo.className='sabri-f26__action';undo.textContent=(cfg.strings&&cfg.strings.undo)||'Undo';undo.addEventListener('click',async()=>{undo.disabled=true;try{await api('feedback',{method:'POST',headers:{'Accept':'application/json','Content-Type':'application/json','X-WP-Nonce':cfg.nonce||''},body:JSON.stringify({type:'undo',item_key:button.getAttribute('data-object-key'),idempotency_key:newId(),undo_idempotency_key:actionId})});card.hidden=false;live.textContent=(cfg.strings&&cfg.strings.done)||'Saved';card.focus&&card.focus();}catch(error){undo.disabled=false;announce(error.message,true,button);}});live.append(text,document.createTextNode(' '),undo);undo.focus();}}
    } catch(error){button.textContent=original;button.removeAttribute('aria-pressed');announce(error.message,true,button);} finally{button.disabled=false;}
  });

  root.addEventListener('click', async (event) => {
    const button=event.target.closest('[data-f26-personalization]');if(!button)return;event.preventDefault();if(button.disabled)return;const action=button.getAttribute('data-f26-personalization');const map={'consent-on':['personalization/consent',{consent:true}],'consent-off':['personalization/consent',{consent:false}],'reset':['personalization/reset',{}],'opt-out':['personalization/opt-out',{}]};if(!map[action])return;button.disabled=true;const original=button.textContent;button.textContent=(cfg.strings&&cfg.strings.working)||'Working…';try{await api(map[action][0],{method:'POST',headers:{'Accept':'application/json','Content-Type':'application/json','X-WP-Nonce':cfg.nonce||''},body:JSON.stringify(map[action][1])});announce((cfg.strings&&cfg.strings.done)||'Saved',false,button);window.location.reload();}catch(error){button.textContent=original;button.disabled=false;announce(error.message,true,button);}
  });

  root.querySelectorAll('[data-f26-interests]').forEach((form) => {
    form.addEventListener('submit', async (event) => {event.preventDefault();const input=form.querySelector('[name="interests"]');const button=form.querySelector('button[type="submit"]');const interests=String(input&&input.value||'').split(',').map((value)=>value.trim()).filter(Boolean).slice(0,50);if(button)button.disabled=true;try{await api('personalization/interests',{method:'POST',headers:{'Accept':'application/json','Content-Type':'application/json','X-WP-Nonce':cfg.nonce||''},body:JSON.stringify({interests:interests})});announce((cfg.strings&&cfg.strings.done)||'Saved',false,form);window.location.reload();}catch(error){announce(error.message,true,form);if(button)button.disabled=false;}});
  });
})();
