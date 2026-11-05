const accountOverlay = document.getElementById('accountOverlay');

const loginTab = document.getElementById('loginTab');
const signupTab = document.getElementById('signupTab');

const loginForm = document.getElementById('loginForm');
const signupForm = document.getElementById('signupForm');

function openAccount() {
  accountOverlay.hidden = false;
  showLogin();
}

function closeAccount() {
  accountOverlay.hidden = true;
}

function showLogin() {
  loginForm.hidden = false;
  signupForm.hidden = true;

  loginTab.classList.add('active');
  signupTab.classList.remove('active');
}

function showSignup() {
  loginForm.hidden = true;
  signupForm.hidden = false;

  signupTab.classList.add('active');
  loginTab.classList.remove('active');
}

document.getElementById('accountBtn')
  .addEventListener('click', openAccount);

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
  if (e.target === accountOverlay) {
    closeAccount();
  }
});

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') closeAccount();
});
