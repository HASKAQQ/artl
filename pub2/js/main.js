// Load header and footer
function loadComponent(url, elementId) {
  if (document.getElementById(elementId)) {
    fetch(url)
      .then(response => response.text())
      .then(data => {
        document.getElementById(elementId).innerHTML = data;
      })
      .catch(error => console.error(`Ошибка загрузки ${url}:`, error));
  }
}

// Phone form submission
const phoneForm = document.getElementById('phoneForm');
const codeForm = document.getElementById('codeForm');
const phoneStep = document.getElementById('phoneStep');
const codeStep = document.getElementById('codeStep');
const verificationCodeInput = document.getElementById('verificationCode');
const smsHint = document.getElementById('smsHint');

if (phoneForm) {
  phoneForm.addEventListener('submit', function (e) {
    e.preventDefault();

    const phoneInput = document.getElementById('phoneInput');
    const phone = (phoneInput?.value || '').trim();

    fetch('login.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: `action=send_code&phone=${encodeURIComponent(phone)}`
    })
      .then(response => response.json())
      .then(data => {
        if (!data.success) {
          alert(data.message || 'Не удалось отправить код.');
          return;
        }

        if (phoneStep) phoneStep.classList.add('d-none');
        if (codeStep) codeStep.classList.remove('d-none');
        if (verificationCodeInput) verificationCodeInput.focus();
        if (smsHint && data.message) smsHint.textContent = data.message;
      })
      .catch(error => {
        console.error('Ошибка:', error);
        alert('Произошла ошибка. Попробуйте еще раз.');
      });
  });
}

if (verificationCodeInput) {
  verificationCodeInput.addEventListener('input', function () {
    const digitsOnly = this.value.replace(/\D/g, '').slice(0, 5);
    this.value = digitsOnly;
  });
}

function verifyCode() {
  const code = (verificationCodeInput?.value || '').trim();

  if (code.length !== 5) {
    alert('Введите 5 цифр из SMS.');
    return;
  }

  fetch('login.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: `action=verify_code&code=${encodeURIComponent(code)}`
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        window.location.href = data.redirect;
      } else {
        alert(data.message || 'Неверный код');
        if (verificationCodeInput) {
          verificationCodeInput.value = '';
          verificationCodeInput.focus();
        }
      }
    })
    .catch(error => {
      console.error('Ошибка:', error);
      alert('Произошла ошибка. Попробуйте еще раз.');
    });
}

if (codeForm) {
  codeForm.addEventListener('submit', function (e) {
    e.preventDefault();
    verifyCode();
  });
}

// Resend code button
const resendBtn = document.getElementById('resendBtn');
if (resendBtn) {
  resendBtn.addEventListener('click', function () {
    const phoneInput = document.getElementById('phoneInput');
    const phone = (phoneInput?.value || '').trim();

    fetch('login.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: `action=send_code&phone=${encodeURIComponent(phone)}`
    })
      .then(response => response.json())
      .then(data => {
        if (!data.success) {
          alert(data.message || 'Не удалось отправить код.');
          return;
        }

        alert(data.message || 'Новый код отправлен в SMS.');
        if (verificationCodeInput) {
          verificationCodeInput.value = '';
          verificationCodeInput.focus();
        }
        if (smsHint && data.message) smsHint.textContent = data.message;
      })
      .catch(error => {
        console.error('Ошибка:', error);
        alert('Произошла ошибка. Попробуйте еще раз.');
      });
  });
}

function toggleSection(sectionName) {
  const content = document.getElementById(sectionName + 'Content');
  const arrow = document.getElementById(sectionName + 'Arrow');

  if (content && arrow) {
    if (content.style.display === 'none') {
      content.style.display = 'block';
      arrow.textContent = '▼';
    } else {
      content.style.display = 'none';
      arrow.textContent = '▶';
    }
  }
}

// Функции для модальных окон
function openPortfolioModal(card) {
  const modal = document.getElementById('portfolioModal');
  const dropdown = document.getElementById('portfolioModalDropdown');
  if (modal) modal.style.display = 'flex';
  if (dropdown) dropdown.style.display = 'flex';
}

function openServiceModal(card) {
  const modal = document.getElementById('serviceModal');
  const dropdown = document.getElementById('serviceModalDropdown');
  if (modal) modal.style.display = 'flex';
  if (dropdown) dropdown.style.display = 'flex';
}

function closeModalOnOverlay(event, modalId) {
  if (event.target.classList.contains('modal-overlay')) {
    const modal = document.getElementById(modalId);
    if (modal) modal.style.display = 'none';
  }
}

function savePortfolio() {
  alert('Портфолио сохранено!');
  const modal = document.getElementById('portfolioModal');
  const dropdown = document.getElementById('portfolioModalDropdown');
  if (modal) modal.style.display = 'none';
  if (dropdown) dropdown.style.display = 'none';
}

function deletePortfolio() {
  if (confirm('Удалить работу из портфолио?')) {
    alert('Работа удалена!');
    const modal = document.getElementById('portfolioModal');
    const dropdown = document.getElementById('portfolioModalDropdown');
    if (modal) modal.style.display = 'none';
    if (dropdown) dropdown.style.display = 'none';
  }
}

function saveService() {
  alert('Услуга сохранена!');
  const modal = document.getElementById('serviceModal');
  const dropdown = document.getElementById('serviceModalDropdown');
  if (modal) modal.style.display = 'none';
  if (dropdown) dropdown.style.display = 'none';
}

function deleteService() {
  if (confirm('Удалить услугу?')) {
    alert('Услуга удалена!');
    const modal = document.getElementById('serviceModal');
    const dropdown = document.getElementById('serviceModalDropdown');
    if (modal) modal.style.display = 'none';
    if (dropdown) dropdown.style.display = 'none';
  }
}

// Переключение роли
const roleButtons = document.querySelectorAll('.role-btn');
if (roleButtons.length > 0) {
  roleButtons.forEach(btn => {
    btn.addEventListener('click', function () {
      const switchUrl = this.getAttribute('data-switch-url');
      if (switchUrl) {
        window.location.href = switchUrl;
        return;
      }

      roleButtons.forEach(b => b.classList.remove('active'));
      this.classList.add('active');
    });
  });
}

// FAQ аккордеон
let questions = document.querySelectorAll('.question');
let answers = document.querySelectorAll('.answer');

if (questions.length > 0 && answers.length > 0) {
  questions.forEach((question, index) => {
    question.onclick = () => {
      const isActive = question.classList.toggle('active');
      const answer = answers[index];

      answer.classList.toggle('active', isActive);
      question.setAttribute('aria-expanded', isActive ? 'true' : 'false');
      answer.setAttribute('aria-hidden', isActive ? 'false' : 'true');

      if (isActive) {
        answer.style.maxHeight = `${answer.scrollHeight}px`;
      } else {
        answer.style.maxHeight = '0px';
      }
    };
  });
}

let menubtn = document.querySelector('.menu-btn');
let adminMenuCloseBtn = document.querySelector('.admin-menu-close');
let adminNavMenu = document.querySelector('.nav-menu');

if (menubtn && adminNavMenu) {
  menubtn.onclick = () => {
    adminNavMenu.classList.add('active');
  };
}

if (adminMenuCloseBtn && adminNavMenu) {
  adminMenuCloseBtn.onclick = () => {
    adminNavMenu.classList.remove('active');
  };
}

let ids = document.querySelectorAll('.id');
let admIdMenus = document.querySelectorAll('.adm-id-menu');

if (ids.length > 0 && admIdMenus.length > 0) {
  ids.forEach((id, index) => {
    id.onclick = () => {
      ids[index].classList.toggle('active');
      admIdMenus[index].classList.toggle('active');
    };
  });
}

// Header user dropdown
const headerAvatarBtn = document.getElementById('headerAvatarBtn');
const headerUserDropdown = document.getElementById('headerUserDropdown');
const headerUserMenu = document.getElementById('headerUserMenu');

if (headerAvatarBtn && headerUserDropdown && headerUserMenu) {
  headerAvatarBtn.addEventListener('click', function (e) {
    e.stopPropagation();
    headerUserDropdown.classList.toggle('show');
  });

  document.addEventListener('click', function (e) {
    if (!headerUserMenu.contains(e.target)) {
      headerUserDropdown.classList.remove('show');
    }
  });
}

// Admin header dropdown
const adminAvatarBtn = document.getElementById('adminAvatarBtn');
const adminUserDropdown = document.getElementById('adminUserDropdown');
const adminUserMenu = document.getElementById('adminUserMenu');

if (adminAvatarBtn && adminUserDropdown && adminUserMenu) {
  adminAvatarBtn.addEventListener('click', function (e) {
    e.stopPropagation();
    adminUserDropdown.classList.toggle('show');
  });

  document.addEventListener('click', function (e) {
    if (!adminUserMenu.contains(e.target)) {
      adminUserDropdown.classList.remove('show');
    }
  });
}
