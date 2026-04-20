const profile = {
  age: 15,
  projects: 5,
  telegram: '@hauntz1337',
  telegramUrl: 'https://t.me/hauntz1337',
  discord: 'hauntz.adm',
  stack: ['Cybersec', 'C++', 'C#', 'PHP', 'JS', 'Next.js', 'Lua', 'HTML/CSS', 'React'],
  baseViews: 347
};

const copy = {
  ru: {
    titleLead: 'HAUNT',
    titleGhost: 'Z',
    role: 'cybersec / developer',
    intro: 'Увлекаюсь кибербезом и вебом.',
    subIntro: 'Мне 15. Пишу на C++, C#, PHP, JS, Next.js, Lua, HTML/CSS и React. Уже участвовал в 5 проектах как разработчик.',
    cta: 'Написать в Telegram',
    copyDiscord: 'Скопировать Discord',
    githubBtn: 'GitHub',
    openTelegram: 'Открыть Telegram',
    mainContact: 'main',
    secondContact: 'contact',
    tgNote: 'Быстрее всего отвечаю тут',
    dsNote: 'Можно написать или добавить',
    views: 'Просмотры',
    age: 'Возраст',
    projects: 'Проекты',
    telegram: 'Telegram',
    faq: [
      { q: 'Что тебе интересно?', a: 'Кибербез, веб и нормальный чистый код.' },
      { q: 'С чем ты работаешь?', a: 'C++, C#, PHP, JS, Next.js, Lua, HTML/CSS и React.' },
      { q: 'Опыт уже есть?', a: 'Да. Уже участвовал в 5 проектах как разработчик.' }
    ],
    copied: 'Discord скопирован'
  },
  en: {
    titleLead: 'HAUNT',
    titleGhost: 'Z',
    role: 'cybersec / developer',
    intro: "Into cybersecurity and web.",
    subIntro: 'I'm 15. I use C++, C#, PHP, JS, Next.js, Lua, HTML/CSS and React. I already have 5 projects where I worked as a developer.',
    cta: 'Message me on Telegram',
    copyDiscord: 'Copy Discord',
    githubBtn: 'GitHub',
    openTelegram: 'Open Telegram',
    mainContact: 'main',
    secondContact: 'contact',
    tgNote: 'Fastest way to reach me',
    dsNote: 'You can add or message me',
    views: 'Views',
    age: 'Age',
    projects: 'Projects',
    telegram: 'Telegram',
    faq: [
      { q: 'What are you into?', a: 'Cybersecurity, web and clean code.' },
      { q: 'What do you use?', a: 'C++, C#, PHP, JS, Next.js, Lua, HTML/CSS and React.' },
      { q: 'Do you already have experience?', a: 'Yeah. I have already worked on 5 projects as a developer.' }
    ],
    copied: 'Discord copied'
  },
  ua: {
    titleLead: 'HAUNT',
    titleGhost: 'Z',
    role: 'cybersec / developer',
    intro: 'Цікавлюсь кібербезпекою та вебом.',
    subIntro: 'Мені 15. Пишу на C++, C#, PHP, JS, Next.js, Lua, HTML/CSS і React. Уже брав участь у 5 проєктах як розробник.',
    cta: 'Написати в Telegram',
    copyDiscord: 'Скопіювати Discord',
    githubBtn: 'GitHub',
    openTelegram: 'Відкрити Telegram',
    mainContact: 'main',
    secondContact: 'contact',
    tgNote: 'Найшвидше відповідаю тут',
    dsNote: 'Можна написати або додати',
    views: 'Перегляди',
    age: 'Вік',
    projects: 'Проєкти',
    telegram: 'Telegram',
    faq: [
      { q: 'Що тобі цікаво?', a: 'Кібербез, веб і чистий код.' },
      { q: 'З чим ти працюєш?', a: 'C++, C#, PHP, JS, Next.js, Lua, HTML/CSS і React.' },
      { q: 'Досвід уже є?', a: 'Так. Уже брав участь у 5 проєктах як розробник.' }
    ],
    copied: 'Discord скопійовано'
  }
};

const state = {
  lang: detectLang(),
  views: 0
};

const els = {
  clock: document.getElementById('clock'),
  zone: document.getElementById('zone'),
  stack: document.getElementById('stack'),
  faq: document.getElementById('faq-list'),
  views: document.getElementById('views')
};

function detectLang() {
  const saved = localStorage.getItem('hauntz-lang');
  if (saved && copy[saved]) return saved;
  const raw = (navigator.language || 'en').toLowerCase();
  if (raw.startsWith('ru')) return 'ru';
  if (raw.startsWith('uk')) return 'ua';
  return 'en';
}

function getTimezoneLabel() {
  const offset = -new Date().getTimezoneOffset();
  const sign = offset >= 0 ? '+' : '-';
  const hours = String(Math.floor(Math.abs(offset) / 60)).padStart(2, '0');
  const minutes = Math.abs(offset) % 60;
  return `GMT${sign}${hours}${minutes ? `:${String(minutes).padStart(2, '0')}` : ''}`;
}

function updateClock() {
  const now = new Date();
  els.clock.textContent = now.toLocaleTimeString('en-GB', { hour12: false });
  els.zone.textContent = getTimezoneLabel();
}

function formatNumber(value, lang) {
  return new Intl.NumberFormat(lang === 'ua' ? 'uk-UA' : lang === 'ru' ? 'ru-RU' : 'en-US').format(value);
}

function getViews() {
  const key = 'hauntz-views';
  const sessionKey = 'hauntz-viewed-this-session';
  const current = Number(localStorage.getItem(key) || profile.baseViews);
  if (!sessionStorage.getItem(sessionKey)) {
    localStorage.setItem(key, String(current + 1));
    sessionStorage.setItem(sessionKey, '1');
    return current + 1;
  }
  return current;
}

function renderStack() {
  els.stack.innerHTML = profile.stack.map(item => `<span class="chip">${item}</span>`).join('');
}

function renderFAQ() {
  const text = copy[state.lang];
  els.faq.innerHTML = text.faq.map((item, i) => `
    <div class="faq-item${i === 0 ? ' is-open' : ''}">
      <button class="faq-trigger" type="button">
        <span class="faq-q">${item.q}</span>
        <span class="faq-plus">+</span>
      </button>
      <div class="faq-answer" style="max-height:${i === 0 ? '120px' : '0px'}">
        <p>${item.a}</p>
      </div>
    </div>
  `).join('');

  els.faq.querySelectorAll('.faq-item').forEach(item => {
    const trigger = item.querySelector('.faq-trigger');
    const answer = item.querySelector('.faq-answer');
    trigger.addEventListener('click', () => {
      const isOpen = item.classList.contains('is-open');
      els.faq.querySelectorAll('.faq-item').forEach(other => {
        other.classList.remove('is-open');
        other.querySelector('.faq-answer').style.maxHeight = '0px';
      });
      if (!isOpen) {
        item.classList.add('is-open');
        answer.style.maxHeight = `${answer.scrollHeight + 24}px`;
      }
    });
  });
}

function applyLanguage(lang) {
  state.lang = lang;
  localStorage.setItem('hauntz-lang', lang);
  document.documentElement.lang = lang === 'ua' ? 'uk' : lang;
  const text = copy[lang];

  document.querySelectorAll('[data-i18n]').forEach(node => {
    const key = node.dataset.i18n;
    if (text[key]) node.textContent = text[key];
  });

  document.querySelectorAll('.lang-btn').forEach(btn => {
    btn.classList.toggle('is-active', btn.dataset.lang === lang);
  });

  renderFAQ();
  renderStack();
  els.views.textContent = formatNumber(state.views, lang);
}

function showToast(message) {
  const el = document.createElement('div');
  el.className = 'toast';
  el.textContent = message;
  document.body.appendChild(el);
  requestAnimationFrame(() => {
    el.style.opacity = '1';
    el.style.transform = 'translateY(0)';
  });
  setTimeout(() => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(12px)';
    setTimeout(() => el.remove(), 220);
  }, 1500);
}

async function copyDiscord() {
  try {
    await navigator.clipboard.writeText(profile.discord);
    showToast(copy[state.lang].copied);
  } catch {
    showToast(profile.discord);
  }
}

function initActions() {
  document.querySelectorAll('.lang-btn').forEach(btn => {
    btn.addEventListener('click', () => applyLanguage(btn.dataset.lang));
  });
  document.getElementById('copy-discord').addEventListener('click', copyDiscord);
  document.getElementById('copy-discord-inline').addEventListener('click', copyDiscord);
}

function initGsap() {
  if (!window.gsap) return;
  const gsap = window.gsap;
  gsap.set('.reveal', { opacity: 1 });
  const tl = gsap.timeline({ defaults: { ease: 'power3.out' } });
  tl.from('.topbar', { y: -18, opacity: 0, duration: 0.5 })
    .from('.title-solid', { y: 40, opacity: 0, duration: 0.7 }, '-=0.2')
    .from('.title-ghost', { y: 28, opacity: 0, duration: 0.75 }, '-=0.55')
    .from('.role, .lead, .sub', { y: 18, opacity: 0, stagger: 0.08, duration: 0.42 }, '-=0.4')
    .from('.chip', { scale: 0.92, opacity: 0, stagger: 0.03, duration: 0.22 }, '-=0.2')
    .from('.btn, .stat-box', { y: 10, opacity: 0, stagger: 0.05, duration: 0.3 }, '-=0.12')
    .from('.card', { x: 22, opacity: 0, stagger: 0.08, duration: 0.4 }, '-=0.25')
    .from('.faq-item', { y: 12, opacity: 0, stagger: 0.05, duration: 0.26 }, '-=0.18')
    .from('.footer span', { y: 10, opacity: 0, stagger: 0.04, duration: 0.2 }, '-=0.1');

  gsap.to('.title-ghost', {
    opacity: 0.55,
    duration: 2,
    repeat: -1,
    yoyo: true,
    ease: 'sine.inOut'
  });

  gsap.to('.card', {
    y: -5,
    duration: 2.4,
    repeat: -1,
    yoyo: true,
    stagger: 0.18,
    ease: 'sine.inOut'
  });
}

function initVeil() {
  const canvas = document.getElementById('veil');
  const ctx = canvas.getContext('2d');
  const blobs = [
    { x: 0.18, y: 0.18, r: 0.24, dx: 0.0005, dy: 0.0003, hue: 275 },
    { x: 0.78, y: 0.08, r: 0.3, dx: -0.00045, dy: 0.00025, hue: 265 },
    { x: 0.72, y: 0.78, r: 0.22, dx: -0.00035, dy: -0.00028, hue: 250 }
  ];

  function resize() {
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    canvas.width = Math.floor(window.innerWidth * dpr);
    canvas.height = Math.floor(window.innerHeight * dpr);
    canvas.style.width = `${window.innerWidth}px`;
    canvas.style.height = `${window.innerHeight}px`;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
  }

  function draw() {
    const w = window.innerWidth;
    const h = window.innerHeight;
    ctx.clearRect(0, 0, w, h);
    ctx.fillStyle = '#040404';
    ctx.fillRect(0, 0, w, h);

    ctx.globalCompositeOperation = 'lighter';
    blobs.forEach(blob => {
      blob.x += blob.dx;
      blob.y += blob.dy;
      if (blob.x < -0.1 || blob.x > 1.1) blob.dx *= -1;
      if (blob.y < -0.1 || blob.y > 1.1) blob.dy *= -1;

      const grad = ctx.createRadialGradient(
        blob.x * w,
        blob.y * h,
        0,
        blob.x * w,
        blob.y * h,
        blob.r * Math.max(w, h)
      );
      grad.addColorStop(0, `hsla(${blob.hue}, 92%, 58%, 0.24)`);
      grad.addColorStop(0.45, `hsla(${blob.hue}, 88%, 48%, 0.12)`);
      grad.addColorStop(1, `hsla(${blob.hue}, 88%, 28%, 0)`);
      ctx.fillStyle = grad;
      ctx.beginPath();
      ctx.arc(blob.x * w, blob.y * h, blob.r * Math.max(w, h), 0, Math.PI * 2);
      ctx.fill();
    });

    ctx.globalCompositeOperation = 'source-over';
    ctx.fillStyle = 'rgba(0,0,0,0.44)';
    ctx.fillRect(0, 0, w, h);
    requestAnimationFrame(draw);
  }

  resize();
  draw();
  window.addEventListener('resize', resize);
}

function init() {
  state.views = getViews();
  updateClock();
  setInterval(updateClock, 1000);
  initActions();
  applyLanguage(state.lang);
  initGsap();
  initVeil();
}

document.addEventListener('DOMContentLoaded', init);
