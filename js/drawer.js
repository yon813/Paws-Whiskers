(function () {
  const $d = id => document.getElementById(id);

  const drawer   = $d('drawer');
  const scrim    = $d('scrim');
  const btn      = $d('cartBtn');
  const body     = $d('drawerBody');
  const closeBtn = $d('drawerClose');
  const foot     = document.querySelector('.drawer-f');

  if (!drawer || !scrim || !body || !closeBtn || !foot) {
    console.error('Cart drawer elements are missing from the HTML.');
    return;
  }

  let lastFocus = null;

  /* Fill the drawer from the cart defined in script.js */
  function renderDrawer() {
    $d('drawerTotal').textContent = fmt(sub());

    if (!cart.length) {
      body.innerHTML = '<p class="drawer-empty">Your bag is empty.</p>';
      foot.style.display = 'none';
      return;
    }

    foot.style.display = '';
    body.innerHTML = cart.map((l, i) => {
      const p = prod(l.id);
      return `
        <div class="dline">
          <a class="dthumb" href="#product-${p.id}" aria-label="${p.n}"
             style="display:block;width:100%;max-width:75px;aspect-ratio:1/1;border-radius:12px;overflow:hidden;">
            ${art(p).replace('<img', '<img style="display:block;width:100%;height:100%;object-fit:cover;"')}
          </a>
          <div class="dinfo">
            <a href="#product-${p.id}"><b>${p.n}</b></a>
            <small>${p.ol}: ${l.o}</small>
            <b>${fmt(p.p * l.q)}</b>
            <div class="qty">
              <button aria-label="Decrease" onclick="dchg(${i},-1)">−</button>
              <span>${l.q}</span>
              <button aria-label="Increase" onclick="dchg(${i},1)">+</button>
            </div>
          </div>
          <button class="ib" aria-label="Remove ${p.n}" onclick="drm(${i})">
            <i class="ri-delete-bin-line"></i>
          </button>
        </div>`;
    }).join('');
  }

  /* Quantity / remove, without re-rendering the whole page */
  window.dchg = function (i, d) {
    const l = cart[i];
    if (!l) return;
    l.q += d;
    if (l.q > 20) { l.q = 20; toast('Maximum quantity is 20'); }
    if (l.q < 1) cart.splice(i, 1);
    save();
    if (location.hash === '#cart') route();
  };

  window.drm = function (i) {
    if (!cart[i]) return;
    cart.splice(i, 1);
    save();
    if (location.hash === '#cart') route();
  };

  function openDrawer() {
    lastFocus = document.activeElement;
    renderDrawer();
    drawer.classList.add('on');
    scrim.classList.add('on');
    drawer.setAttribute('aria-hidden', 'false');
    document.body.classList.add('lock');
    closeBtn.focus();
  }

  function closeDrawer() {
    if (!drawer.classList.contains('on')) return;
    drawer.classList.remove('on');
    scrim.classList.remove('on');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('lock');
    if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
  }

  if (btn) btn.addEventListener('click', openDrawer);
  closeBtn.addEventListener('click', closeDrawer);
  scrim.addEventListener('click', closeDrawer);
  $d('viewBag').addEventListener('click', closeDrawer);
  $d('checkoutBtn').addEventListener('click', closeDrawer);
  window.addEventListener('hashchange', closeDrawer);
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeDrawer();
  });

  window.renderDrawer = renderDrawer;
  window.openDrawer = openDrawer;
  window.closeDrawer = closeDrawer;
  renderDrawer();
})();
