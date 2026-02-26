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
if (phoneForm) {
  phoneForm.addEventListener('submit', function (e) {
    e.preventDefault();

    const phoneInput = document.getElementById('phoneInput');
    const phone = phoneInput.value;

    // Отправляем запрос на сервер
    fetch('login.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: `action=send_code&phone=${encodeURIComponent(phone)}`
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Показываем код в консоли (для демонстрации)
        console.log('=================================');
        console.log('ВАШ КОД ПОДТВЕРЖДЕНИЯ:', data.code);
        console.log('=================================');
        alert('Код отправлен! Проверьте консоль браузера (F12)');

        // Переключаемся на форму ввода кода
        const phoneStep = document.getElementById('phoneStep');
        const codeStep = document.getElementById('codeStep');
        const code1 = document.getElementById('code1');

        if (phoneStep) phoneStep.classList.add('d-none');
        if (codeStep) codeStep.classList.remove('d-none');
        if (code1) code1.focus();
      }
    })
    .catch(error => {
      console.error('Ошибка:', error);
      alert('Произошла ошибка. Попробуйте еще раз.');
    });
  });
}

// Code input auto-focus
const codeInputs = document.querySelectorAll('.code-input');
if (codeInputs.length > 0) {
  codeInputs.forEach((input, index) => {
    input.addEventListener('input', function () {
      if (this.value.length === 1) {
        if (index < codeInputs.length - 1) {
          const nextInput = codeInputs[index + 1];
          if (nextInput) nextInput.focus();
        } else {
          // Последняя цифра введена - автоматически проверяем код
          verifyCode();
        }
      }
    });

    input.addEventListener('keydown', function (e) {
      if (e.key === 'Backspace' && this.value === '' && index > 0) {
        const prevInput = codeInputs[index - 1];
        if (prevInput) prevInput.focus();
      }
    });
  });
}

// Функция проверки кода
function verifyCode() {
  const code = Array.from(codeInputs).map(input => input.value).join('');

  if (code.length === 5) {
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
        // Успешный вход - переходим в профиль
        window.location.href = data.redirect;
      } else {
        alert(data.message || 'Неверный код');
        // Очищаем поля
        codeInputs.forEach(input => input.value = '');
        if (codeInputs[0]) codeInputs[0].focus();
      }
    })
    .catch(error => {
      console.error('Ошибка:', error);
      alert('Произошла ошибка. Попробуйте еще раз.');
    });
  }
}

// Resend code button
const resendBtn = document.getElementById('resendBtn');
if (resendBtn && codeInputs.length > 0) {
  resendBtn.addEventListener('click', function () {
    const phoneInput = document.getElementById('phoneInput');
    const phone = phoneInput.value;

    // Повторно запрашиваем код
    fetch('login.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: `action=send_code&phone=${encodeURIComponent(phone)}`
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        console.log('=================================');
        console.log('НОВЫЙ КОД ПОДТВЕРЖДЕНИЯ:', data.code);
        console.log('=================================');
        alert('Новый код отправлен! Проверьте консоль браузера (F12)');
        codeInputs.forEach(input => input.value = '');
        if (codeInputs[0]) codeInputs[0].focus();
      }
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
      questions[index].classList.toggle('active');
      answers[index].classList.toggle('active');
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
