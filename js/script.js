/* =========================================
   1. GENERAL SETTINGS
========================================= */

const fmt = n => '₱' + n.toLocaleString('en-PH');
const $ = id => document.getElementById(id);

let P = [];


/* =========================================
   2. CATEGORIES AND SORTING
========================================= */

const CATS = [
  'All Products',
  'Dog Essentials',
  'Cat Essentials',
  'Food & Treats',
  'Toys & Entertainment',
  'Grooming & Hygiene',
  'Beds & Furniture',
  'Accessories',
  'Health & Wellness'
];

const SORTS = [
  ['best', 'Best Sellers'],
  ['new', 'New Arrivals'],
  ['lo', 'Price: Low to High'],
  ['hi', 'Price: High to Low']
];

let cart = [];

function loadCart() {
  try {
    const stored = JSON.parse(localStorage.getItem('mp-cart') || '[]');
    if (Array.isArray(stored)) {
      cart = stored.filter(item => {
        const p = P.find(product => product.id === Number(item?.id));
        return p && Number.isInteger(item.q) && item.q > 0 &&
          item.q <= 20 && p.o.includes(item.o);
      }).map(item => ({ id: Number(item.id), o: item.o, q: item.q }));
    }
  } catch (e) {
    cart = [];
  }
}

const S = {
  q: '',
  cat: 0,
  sort: 'best'
};


/* =========================================
   3. PRODUCT HELPERS
========================================= */

function prodExists(id) {
  return P.some(p => p.id == id);
}

function prod(id) {
  return P.find(p => p.id == id);
}

function stars(r) {
  const count = Math.round(r);

  return `
    ${Array.from({ length: 5 }, (_, i) => `
      <i class="${i < count ? 'ri-star-fill' : 'ri-star-line'}"
         aria-hidden="true"></i>
    `).join('')}
    <span>${r.toFixed(1)}</span>
  `;
}


/* =========================================
   4. PRODUCT ILLUSTRATIONS / PHOTOS
========================================= */

const IC = {
  'Food & Treats':
    '<path d="M55 100h90a45 40 0 01-90 0z"/><circle cx="80" cy="88" r="7"/><circle cx="102" cy="82" r="7"/><circle cx="122" cy="89" r="7"/>',

  'Toys & Entertainment':
    '<circle cx="100" cy="100" r="42"/><path d="M62 80c25 12 51 12 76 0M62 120c25-12 51-12 76 0"/>',

  'Grooming & Hygiene':
    '<rect x="82" y="52" width="36" height="90" rx="14"/><path d="M92 142v22M108 142v22M100 142v22M82 78h36"/>',

  'Beds & Furniture':
    '<rect x="45" y="100" width="110" height="46" rx="22"/><ellipse cx="100" cy="100" rx="46" ry="14"/><path d="M62 146v10M138 146v10"/>',

  'Accessories':
    '<circle cx="100" cy="92" r="42"/><circle cx="100" cy="92" r="30"/><rect x="88" y="130" width="24" height="30" rx="12"/>',

  'Health & Wellness':
    '<path d="M100 150C50 116 50 72 80 66c10-2 18 4 20 12 2-8 10-14 20-12 30 6 30 50-20 84z"/><path d="M100 90v28M86 104h28"/>'
};

// PRODUCT IMAGE INTEGRATION
function art(p) {
  return `
    <img class="product-photo"
         src="img/product-${p.id}.jpg"
         alt="${p.n}"
         loading="lazy">
  `;
}


/* =========================================
   5. PRODUCT CARDS
========================================= */

function card(p) {
  return `
    <article class="card glass">

      <a href="#product-${p.id}" aria-label="${p.n}">
        ${art(p)}
      </a>

      <div class="ci">

        <small>
          ${p.pet == 'both'
    ? 'Dogs & Cats'
    : p.pet == 'dog'
      ? 'For Dogs'
      : 'For Cats'}
        </small>

        <h3>
          <a href="#product-${p.id}">
            ${p.n}
          </a>
        </h3>

        <div class="rt">${stars(p.r)}</div>

        <div class="row">
          <b>${fmt(p.p)}</b>

          <button class="btn sm" onclick="add(${p.id})">
            Add to cart
          </button>
        </div>

      </div>
    </article>
  `;
}


/* =========================================
   6. SHOPPING CART
========================================= */

function save() {
  try {
    localStorage.setItem('mp-cart', JSON.stringify(cart));
  } catch (e) {
    console.warn('Unable to save cart:', e);
  }

  $('cnt').textContent = cart.reduce(
    (a, l) => a + l.q, 0
  );

  if (
    typeof window.renderDrawer === 'function' &&
    $('drawer')?.classList.contains('on')
  ) {
    window.renderDrawer();
  }
}

function toast(t) {
  const e = $('toast');

  e.textContent = t;
  e.classList.add('on');

  clearTimeout(toast.t);

  toast.t = setTimeout(() => {
    e.classList.remove('on');
  }, 1800);
}

function add(id, q = 1, o) {
  const p = prod(id);

  if (!p) return;

  o = o || p.o[0];
  q = Number(q);

  if (!Number.isInteger(q) || q < 1 || q > 20) {
    toast('Please select a valid quantity');
    return;
  }

  if (!p.o.includes(o)) {
    toast('Please select a valid option');
    return;
  }

  const l = cart.find(x => x.id == id && x.o == o);

  if (l) {
    if (l.q + q > 20) {
      toast('Maximum quantity is 20');
      return;
    }

    l.q += q;
  } else {
    cart.push({ id, o, q });
  }

  save();
  toast('Added to your bag');

  if (location.hash == '#cart') {
    route();
  }
}

function chg(i, d) {
  if (!cart[i]) return;

  cart[i].q += d;

  if (cart[i].q > 20) {
    cart[i].q = 20;
    toast('Maximum quantity is 20');
  }

  if (cart[i].q < 1) {
    cart.splice(i, 1);
  }

  save();
  route();
}

function rm(i) {
  if (!cart[i]) return;

  cart.splice(i, 1);

  save();
  route();
}

const sub = () => cart.reduce(
  (a, l) => a + prod(l.id).p * l.q, 0
);

const ship = () => sub() >= 3000 || !cart.length
  ? 0
  : 150;


/* =========================================
   7. SEARCH
========================================= */

function toggleSearch() {
  const b = $('sbar');

  b.classList.toggle('open');

  if (b.classList.contains('open')) {
    $('sq').focus();
  }
}

function doSearch() {
  S.q = $('sq').value.trim();
  S.cat = 0;

  $('sbar').classList.remove('open');

  if (location.hash === '#shop') {
    route();
  } else {
    location.hash = 'shop';
  }
}


/* =========================================
   8. HOME PAGE
========================================= */

function heroArt(v) {
  const a = v ? '#E3B5A4' : '#F5E9E2';
  const b = v ? '#F5E9E2' : '#E3B5A4';

  return `
    <svg viewBox="0 0 400 400"
      style="display:block;width:100%;height:100%"
      role="img"
      aria-label="Illustration of a dog and a cat side by side">

      <rect width="400" height="400" fill="${b}"/>
      <circle cx="300" cy="90" r="120" fill="${a}" opacity=".7"/>
      <circle cx="60" cy="360" r="110" fill="#773344" opacity=".1"/>

      <g stroke="#773344" stroke-width="5"
         stroke-linejoin="round" stroke-linecap="round">

        <path d="M70 300V200a70 70 0 01140 0v100z" fill="${a}"/>

        <path d="M82 150c-34 0-40 70-18 86
          16-22 20-50 18-86zM198 150c34 0 40 70
          18 86-16-22-20-50-18-86z" fill="#773344"/>

        <circle cx="118" cy="190" r="6" fill="#773344"/>
        <circle cx="162" cy="190" r="6" fill="#773344"/>

        <path d="M128 220q12 10 24 0M140 206v10" fill="none"/>

        <path d="M215 300V230a60 60 0 01120 0v70z"
          fill="#E3B5A4" opacity=".95"/>

        <path d="M225 190l10-48 32 22M325 190l-10-48-32 22"
          fill="#E3B5A4"/>

        <circle cx="250" cy="232" r="5" fill="#773344"/>
        <circle cx="296" cy="232" r="5" fill="#773344"/>

        <path d="M265 250q8 8 16 0M273 244v6
          M230 248l-18-4M230 256l-18 6
          M316 248l18-4M316 256l18 6" fill="none"/>

      </g>
    </svg>
  `;
}

function home() {
  const top = P.filter(p => p.b).slice(0, 4);

  return `
    <div class="wrap">

      <div class="hero">

        <div>
          <h1>Everything they love, all in one place.</h1>

          <p class="mt">
            Paws&Whiskers is a small boutique of beautiful,
            well-made essentials for dogs and cats.
            Every beanbag, brush and bowl is chosen by us,
            tested at home and made to last.
          </p>

          <div class="btns">
            <a class="btn" href="#shop-dogs">Shop for Dogs</a>
            <a class="btn ghost" href="#shop-cats">Shop for Cats</a>
          </div>
        </div>

        <div class="art glass">
          ${heroArt()}
        </div>

      </div>
    </div>

    <section>
      <div class="wrap">

        <div class="sh">
          <h2>Featured collections</h2>
          <a href="#shop" class="btn ghost sm">View all</a>
        </div>

        <div class="cols">

          ${[
    ['Beds & Furniture', 'Beds & Furniture', 'Soft places to land'],
    ['Food & Treats', 'Food & Treats', 'Honest, well-sourced meals'],
    ['Accessories', 'Accessories', 'Leather, brass and linen'],
    ['Health & Wellness', 'Health & Wellness', 'Daily care, simplified']
  ].map(c => `
            <a class="coll glass" href="#shop-${CATS.indexOf(c[0])}">
              <h3>${c[1]}</h3>
              <span>${c[2]}</span>
            </a>
          `).join('')}

        </div>
      </div>
    </section>

    <section>
      <div class="wrap">

        <div class="sh">
          <h2>Best sellers</h2>
        </div>

        <div class="grid">
          ${top.map(card).join('')}
        </div>

      </div>
    </section>
  `;
}


/* =========================================
   9. SHOP PAGE
========================================= */

function shop() {
  const list = P.filter(p => {
    const matchesSearch =
      !S.q ||
      p.n.toLowerCase().includes(S.q.toLowerCase()) ||
      p.d.toLowerCase().includes(S.q.toLowerCase());

    const matchesCategory =
      S.cat === 0 ||
      p.c === CATS[S.cat];

    return matchesSearch && matchesCategory;
  });

  if (S.sort === 'lo') {
    list.sort((a, b) => a.p - b.p);
  } else if (S.sort === 'hi') {
    list.sort((a, b) => b.p - a.p);
  } else if (S.sort === 'new') {
    list.sort((a, b) => b.id - a.id);
  } else {
    list.sort((a, b) => Number(b.b) - Number(a.b));
  }

  return `
    <div class="wrap">

      <div class="sh">
        <h1>Shop</h1>
        <span>${list.length} products</span>
      </div>

      <div class="shop-tools">

        <div class="filters">
          ${CATS.map((c, i) => `
            <button class="filter ${S.cat === i ? 'active' : ''}"
              onclick="setCat(${i})">${c}</button>
          `).join('')}
        </div>

        <select onchange="setSort(this.value)">
          ${SORTS.map(s => `
            <option value="${s[0]}" ${S.sort === s[0] ? 'selected' : ''}>
              ${s[1]}
            </option>
          `).join('')}
        </select>

      </div>

      <div class="grid" id="product-grid">
        ${list.map(card).join('')}
      </div>

      ${list.length === 0 ? '<p>No products found.</p>' : ''}

    </div>
  `;
}

function setCat(i) {
  S.cat = i;
  route();
}

function setSort(s) {
  S.sort = s;
  route();
}

function grid() {
  const g = $('product-grid');
  if (!g) return;
}


/* =========================================
   10. PRODUCT DETAIL PAGE
========================================= */

function product(id) {
  const p = prod(id);

  if (!p) {
    return `
      <div class="wrap center">
        <h1>Product not found</h1>
        <a href="#shop" class="btn">Back to shop</a>
      </div>
    `;
  }

  return `
    <div class="wrap product-detail">

      <div class="product-image glass">
        ${art(p)}
      </div>

      <div class="product-info">

        <small>${p.c}</small>

        <h1>${p.n}</h1>

        <div class="rt">${stars(p.r)}</div>

        <h2>${fmt(p.p)}</h2>

        <p>${p.d}</p>

        <label for="option">Choose option</label>

        <select id="option">
          ${p.o.map(o => `<option value="${o}">${o}</option>`).join('')}
        </select>

        <label for="quantity">Quantity</label>
        <input id="quantity" type="number" min="1" max="20" value="1">

        <button class="btn" onclick="add(${p.id},
          Number($('quantity').value), $('option').value)">
          Add to cart
        </button>

      </div>

    </div>
  `;
}


/* =========================================
   11. CART PAGE
========================================= */

function cartPg() {
  if (!cart.length) {
    return `
      <div class="wrap center" style="padding:80px 22px">
        <h1>Your bag is empty</h1>
        <a href="#shop" class="btn mt">Continue shopping</a>
      </div>
    `;
  }

  return `
    <div class="wrap">

      <h1>Your shopping bag</h1>

      <div class="cartw mt">

        <div class="cart-items">

          ${cart.map((l, i) => {
    const p = prod(l.id);

    return `
              <article class="cart-item glass">

                <img src="img/product-${p.id}.jpg"
                  alt="${p.n}" class="cart-photo">

                <div>
                  <h3>${p.n}</h3>
                  <p>${l.o}</p>
                  <b>${fmt(p.p)}</b>

                  <div class="quantity">
                    <button onclick="chg(${i}, -1)">−</button>
                    <span>${l.q}</span>
                    <button onclick="chg(${i}, 1)">+</button>
                  </div>

                  <button onclick="rm(${i})">Remove</button>
                </div>

              </article>
            `;
  }).join('')}

        </div>

        <aside class="glass sum">

          <h2>Order summary</h2>

          <p>Subtotal: ${fmt(sub())}</p>
          <p>Shipping: ${fmt(ship())}</p>

          <hr>

          <h3>Total: ${fmt(sub() + ship())}</h3>

          <a href="#checkout" class="btn">Proceed to checkout</a>

        </aside>

      </div>
    </div>
  `;
}


/* =========================================
   12. CHECKOUT PAGE
========================================= */

function checkout() {
  if (!cart.length) {
    return `
      <div class="wrap center">
        <h1>Your bag is empty</h1>
        <a href="#shop" class="btn">Shop now</a>
      </div>
    `;
  }

  return `
    <div class="wrap">

      <h1>Checkout</h1>

      <form id="checkout-form" class="glass"
        onsubmit="placeOrder(event)">

        <label for="customer-name">Full name</label>
        <input id="customer-name" required>

        <label for="customer-email">Email</label>
        <input id="customer-email" type="email" required>

        <label for="customer-phone">Phone</label>
        <input id="customer-phone" required>

        <label for="customer-address">Delivery address</label>
        <textarea id="customer-address" required></textarea>

        <label for="payment">Payment method</label>
        <select id="payment">
          <option value="GCash">GCash</option>
          <option value="Cash on Delivery">Cash on Delivery</option>
        </select>

        <h3>Total: ${fmt(sub() + ship())}</h3>

        <button class="btn" type="submit">Place order</button>

      </form>

    </div>
  `;
}


/* =========================================
   13. PLACE ORDER
========================================= */

let last = null;

async function placeOrder(e) {
  e.preventDefault();

  const payload = {
    customer: {
      name: $('customer-name').value.trim(),
      email: $('customer-email').value.trim(),
      phone: $('customer-phone').value.trim(),
      address: $('customer-address').value.trim()
    },
    payment: $('payment').value,
    items: cart.map(l => ({
      id: l.id,
      option: l.o,
      quantity: l.q
    }))
  };

  const submitBtn = e.target.querySelector('[type="submit"]');

  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.textContent = 'Processing...';
  }

  try {
    const r = await fetch('api/orders.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(payload)
    });

    const data = await r.json();

    if (!r.ok) {
      throw new Error(data.error || 'Order failed.');
    }

    if (!data.no || data.total === undefined) {
      throw new Error('Invalid server response.');
    }

    last = {
      no: data.no,
      name: payload.customer.name,
      pay: payload.payment,
      total: Number(data.total)
    };

    cart = [];
    save();

    location.hash = 'confirmed';

  } catch (err) {
    console.error('Checkout error:', err);
    toast(err.message || 'Unable to place your order.');

  } finally {
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.textContent = 'Place order';
    }
  }
}


function confirmed() {
  if (!last) {
    return `
      <div class="wrap center" style="padding:80px 22px">
        <h1>No recent order</h1>
        <p class="mt">
          <a class="btn" href="#shop">Go to shop</a>
        </p>
      </div>
    `;
  }

  return `
    <div class="wrap" style="max-width:640px;padding:70px 22px">

      <div class="glass center" style="padding:44px">

        <h1>
          Thank you, ${last.name.split(' ')[0].replace(/[<>&]/g, '')}
        </h1>

        <p class="mt">
          Your order <b>${last.no}</b> has been recorded.
          No actual payment or delivery has been arranged.
        </p>

        <div class="row" style="justify-content:center;gap:30px">

          <span>Total: <b>${fmt(last.total)}</b></span>
          <span>Payment: <b>${last.pay}</b></span>

        </div>

        <a class="btn mt" href="#home">Back to home</a>

      </div>
    </div>
  `;
}


/* =========================================
   14. ABOUT PAGE
========================================= */

function about() {
  return `
    <div class="wrap">

      <div class="two" style="padding:50px 0">

        <div>

          <h1>Fewer things, chosen well.</h1>

          <p class="mt">
            Paws&Whiskers exists to give dogs and cats
            carefully selected, high-quality products.
            We look for natural materials, honest ingredients
            and good craft, and we test every item with
            our own animals before we sell it.
          </p>

          <p>
            We sell for dogs and cats only.
            It keeps our range focused, and lets us
            know every product well enough to advise you.
          </p>

        </div>

        <div class="art glass">
          ${heroArt()}
        </div>

      </div>

      <div class="cols">

        ${[
    [
      'Selected, not stocked',
      'Each product is reviewed for safety, materials and durability.'
    ],
    [
      'Made to last',
      'Natural fibers, leather and ceramic age well and can be repaired.'
    ],
    [
      'Honest advice',
      'Message us and a real person will help you choose.'
    ]
  ].map(c => `
          <div class="glass quote">
            <h3>${c[0]}</h3>

            <p style="font-family:var(--sans);font-size:15px;margin-top:8px">
              ${c[1]}
            </p>
          </div>
        `).join('')}

      </div>
    </div>
  `;
}


/* =========================================
   15. CONTACT PAGE
========================================= */

function contact() {
  return `
    <div class="wrap" style="padding-top:36px">

      <h1>Get in touch</h1>

      <div class="cartw mt">

        <form class="glass" style="padding:26px"
          id="kf" novalidate onsubmit="send(event)">

          <div class="form">

            <div>
              <label for="kn">Name</label>
              <input id="kn">
              <div class="err" id="e-kn"></div>
            </div>

            <div>
              <label for="ke">Email</label>
              <input id="ke" type="email">
              <div class="err" id="e-ke"></div>
            </div>

            <div class="f">
              <label for="km">Message</label>
              <textarea id="km" rows="5"></textarea>
              <div class="err" id="e-km"></div>
            </div>

          </div>

          <button class="btn mt" type="submit">Send message</button>

          <p id="ok" class="mt" style="font-weight:600"></p>

        </form>

        <aside class="glass sum" style="position:static">

          <h3>Contact</h3>

          <p class="mt">
            hello@pawsandwhiskers.ph<br>
            +63 917 555 0142<br>
            Davao City, Philippines<br>
            Mon–Sat, 9 am–6 pm
          </p>

          <p>
            Instagram · Facebook · TikTok<br>
            @pawsandwhiskers
          </p>

        </aside>

      </div>

      <section>

        <h2 style="margin-bottom:18px">Frequently asked questions</h2>

        ${[
    [
      'How long does delivery take?',
      'Metro Davao: 1–2 days. Other areas in the Philippines: 3–7 days.'
    ],
    [
      'How do I pay with GCash?',
      'GCash is displayed as a demonstration payment option. No actual payment is processed.'
    ],
    [
      'Can I return an item?',
      'Unopened items can be returned within 14 days. Used food and hygiene items cannot be returned.'
    ],
    [
      'Do you sell for other animals?',
      'No. We only carry products for dogs and cats.'
    ]
  ].map(q => `
          <details class="glass">
            <summary>${q[0]}</summary>
            <p>${q[1]}</p>
          </details>
        `).join('')}

      </section>

    </div>
  `;
}

async function send(e) {
  e.preventDefault();

  let valid = true;

  const fields = [
    ['kn', x => x.length > 1, 'Enter your name.'],
    ['ke', x => /^\S+@\S+\.\S+$/.test(x), 'Enter a valid email address.'],
    ['km', x => x.length >= 10, 'Write at least 10 characters.']
  ];

  fields.forEach(([k, f, m]) => {
    const i = $(k);
    const good = f(i.value.trim());

    i.classList.toggle('bad', !good);
    $('e-' + k).textContent = good ? '' : m;

    if (!good) valid = false;
  });

  if (!valid) return;

  const payload = {
    name: $('kn').value.trim(),
    email: $('ke').value.trim(),
    message: $('km').value.trim()
  };

  const form = $('kf');
  const btn = form.querySelector('[type="submit"]');

  btn.disabled = true;
  btn.textContent = 'Sending...';

  $('ok').textContent = '';

  try {
    const response = await fetch('api/contact.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(payload)
    });

    const data = await response.json();

    if (!response.ok) {
      throw new Error(data.error || 'Unable to send your message.');
    }

    $('ok').textContent = data.message;
    form.reset();

  } catch (error) {
    console.error('Contact form error:', error);
    $('ok').textContent = error.message;

  } finally {
    btn.disabled = false;
    btn.textContent = 'Send message';
  }
}


/* =========================================
   16. PAGE ROUTER
========================================= */

function route() {

  const h = location.hash.slice(1) || 'home';

  let html;

  const m = h.match(/^product-(\d+)$/);
  const s = h.match(/^shop-(\w+)$/);

  $('links').classList.remove('open');

  if (s) {

    S.cat =
      s[1] == 'dogs' ? 1 :
        s[1] == 'cats' ? 2 :
          +s[1] || 0;

    S.q = '';

    history.replaceState(null, '', '#shop');
  }

  const k = m ? 'product' : s ? 'shop' : h;

  html = m
    ? product(m[1])
    : ({
      home,
      shop,
      cart: cartPg,
      checkout,
      confirmed,
      about,
      contact
    }[k] || home)();

  $('app').innerHTML = html;

  if (k == 'shop') {
    grid();
  }

  document.querySelectorAll('.links a').forEach(a => {

    a.classList.toggle(
      'on',
      a.getAttribute('href') == '#' + (s ? h : k) ||
      (k == 'shop' && S.cat == 1 && a.getAttribute('href') == '#shop-dogs') ||
      (k == 'shop' && S.cat == 2 && a.getAttribute('href') == '#shop-cats')
    );

  });

  window.scrollTo(0, 0);

  document.title =
    'Paws&Whiskers — ' +
    k.charAt(0).toUpperCase() +
    k.slice(1);
}


/* =========================================
   17. INITIALIZE WEBSITE
========================================= */

window.addEventListener('hashchange', route);

fetch('api/products.php')
  .then(r => r.json())
  .then(data => {
    P = data;
    loadCart();
    save();
    route();
  })
  .catch(() => {
    $('app').innerHTML =
      '<div class="wrap center" style="padding:80px 22px"><h1>Could not load products</h1></div>';
  });
