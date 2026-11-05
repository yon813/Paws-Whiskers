(function () {
  const drawer = document.getElementById('drawer');
  const scrim  = document.getElementById('scrim');
  const btn    = document.getElementById('cartBtn');
  const body   = document.getElementById('drawerBody');
  let lastFocus = null;

  function openDrawer() {
    lastFocus = document.activeElement;
    // TODO: hook into script.js. Define window.renderDrawer() there to fill
    // #drawerBody and #drawerTotal from your cart data.
    if (typeof window.renderDrawer === 'function') {
      window.renderDrawer();
    } else if (!body.children.length) {
      body.innerHTML = '<p class="drawer-empty">Your bag is empty.</p>';
    }
    drawer.classList.add('on');
    scrim.classList.add('on');
    drawer.setAttribute('aria-hidden', 'false');
    document.body.classList.add('lock');
    document.getElementById('drawerClose').focus();
  }

  function closeDrawer() {
    drawer.classList.remove('on');
    scrim.classList.remove('on');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('lock');
    if (lastFocus) lastFocus.focus();
  }

  btn.addEventListener('click', openDrawer);
  scrim.addEventListener('click', closeDrawer);
  document.getElementById('drawerClose').addEventListener('click', closeDrawer);
  document.getElementById('viewBag').addEventListener('click', closeDrawer);
  document.getElementById('checkoutBtn').addEventListener('click', closeDrawer);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && drawer.classList.contains('on')) closeDrawer();
  });

  window.openDrawer = openDrawer;   // call this after "Add to bag" to open it
  window.closeDrawer = closeDrawer;
})();
