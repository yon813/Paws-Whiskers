
const accountOverlay = document.getElementById('accountOverlay');
const accountBtn = document.getElementById('accountBtn');

const loginTab = document.getElementById('loginTab');
const signupTab = document.getElementById('signupTab');
const accountTabs = document.querySelector('.account-tabs');

const loginForm = document.getElementById('loginForm');
const signupForm = document.getElementById('signupForm');

const accountProfile = document.getElementById('accountProfile');
const profileName = document.getElementById('profileName');
const profileEmail = document.getElementById('profileEmail');

const accountMessage = document.getElementById('accountMessage');
const logoutBtn = document.getElementById('logoutBtn');
const myOrdersBtn = document.getElementById('myOrdersBtn');

let currentUser = null;


// OPEN AND CLOSE MODAL
function openAccount() {
  accountOverlay.hidden = false;

  if (currentUser) {
    showProfile();
  } else {
    showLogin();
  }
}

function closeAccount() {
  accountOverlay.hidden = true;
}


// LOGIN AND SIGNUP TABS
function showLogin() {
  accountTabs.hidden = false;
  loginForm.hidden = false;
  signupForm.hidden = true;
  accountProfile.hidden = true;

  loginTab.classList.add('active');
  signupTab.classList.remove('active');

  accountMessage.textContent = '';
}

function showSignup() {
  accountTabs.hidden = false;
  loginForm.hidden = true;
  signupForm.hidden = false;
  accountProfile.hidden = true;

  signupTab.classList.add('active');
  loginTab.classList.remove('active');

  accountMessage.textContent = '';
}


// ACCOUNT PROFILE
function showProfile() {
  accountTabs.hidden = true;
  loginForm.hidden = true;
  signupForm.hidden = true;
  accountProfile.hidden = false;

  profileName.textContent = currentUser.name;
  profileEmail.textContent = currentUser.email;

  accountMessage.textContent = '';
}


// MODAL EVENTS
accountBtn.addEventListener('click', openAccount);

document.getElementById('accountClose')
  .addEventListener('click', closeAccount);

loginTab.addEventListener('click', showLogin);
signupTab.addEventListener('click', showSignup);

document.getElementById('switchSignup')
  .addEventListener('click', (e) => {
    e.preventDefault();
    showSignup();
  });

document.getElementById('switchLogin')
  .addEventListener('click', (e) => {
    e.preventDefault();
    showLogin();
  });

accountOverlay.addEventListener('click', (e) => {
  if (e.target === accountOverlay) closeAccount();
});

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') closeAccount();
});

// MY ORDERS BUTTON
myOrdersBtn.addEventListener('click', () => {
  closeAccount();
  location.hash = 'my-orders';
});


// SIGN UP
signupForm.addEventListener('submit', async (e) => {
  e.preventDefault();

  accountMessage.textContent = 'Creating your account...';

  const formData = new FormData(signupForm);

  const data = {
    name: formData.get('name'),
    email: formData.get('email'),
    password: formData.get('password'),
    confirm_password: formData.get('confirm_password')
  };

  try {
    const response = await fetch('api/signup.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(data)
    });

    const result = await response.json();

    accountMessage.textContent = result.message;

    if (result.success) {
      signupForm.reset();
      showLogin();
      accountMessage.textContent = result.message;
    }

  } catch (error) {
    accountMessage.textContent =
      'Unable to connect to the server. Please try again.';
  }
});


// LOG IN
loginForm.addEventListener('submit', async (e) => {
  e.preventDefault();

  accountMessage.textContent = 'Logging in...';

  const formData = new FormData(loginForm);

  const data = {
    email: formData.get('email'),
    password: formData.get('password')
  };

  try {
    const response = await fetch('api/login.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(data)
    });

    const result = await response.json();

    if (!response.ok || !result.success) {
      accountMessage.textContent = result.message;
      return;
    }

    currentUser = result.user;
    loginForm.reset();

    showProfile();

  } catch (error) {
    accountMessage.textContent =
      'Unable to connect to the server. Please try again.';
  }
});


// CHECK EXISTING SESSION
async function checkSession() {
  try {
    const response = await fetch('api/session.php');
    const result = await response.json();

    if (response.ok && result.success) {
      currentUser = result.user;
    } else {
      currentUser = null;
    }

  } catch (error) {
    currentUser = null;
  }
}


// LOG OUT
logoutBtn.addEventListener('click', async () => {
  logoutBtn.disabled = true;
  accountMessage.textContent = 'Logging out...';

  try {
    const response = await fetch('api/logout.php', {
      method: 'POST'
    });

    const result = await response.json();

    if (!response.ok || !result.success) {
      accountMessage.textContent =
        result.message || 'Unable to log out.';
      return;
    }

    currentUser = null;
    showLogin();
    accountMessage.textContent = 'You have successfully logged out.';

  } catch (error) {
    accountMessage.textContent =
      'Unable to connect to the server. Please try again.';
  } finally {
    logoutBtn.disabled = false;
  }
});


// RESTORE LOGIN STATUS WHEN WEBSITE LOADS
checkSession();
