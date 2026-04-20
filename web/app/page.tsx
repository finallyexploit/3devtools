// @ts-nocheck
'use client'

import { useState, useEffect, useRef, useCallback, useMemo } from 'react'
import gsap from 'gsap'
import { useGSAP } from '@gsap/react'
import { fill, getFeatures, getStoreProducts, LANG_OPTIONS, LOCALES, TRANSLATIONS } from './i18n'

gsap.registerPlugin(useGSAP)

const API = 'api.php'
const CLEAR = 'opacity,visibility,transform'
let csrfTokenValue = ''

function syncCsrfToken(data) {
  const token = typeof data?.csrf_token === 'string' ? data.csrf_token : ''
  if (token) csrfTokenValue = token
  return data
}

const GITHUB_LINKS = [
  ['https://github.com/sky7ovecoder', 'hauntz'],
  ['https://github.com/ostuch1337', 'ostuch'],
  ['https://github.com/Kyba1337', 'kyba'],
]

const CURRENCY_META = {
  usd: { code: 'USD', symbol: '$', rate: 1, digits: 2 },
  eur: { code: 'EUR', symbol: '€', rate: 0.92, digits: 2 },
  rub: { code: 'RUB', symbol: '₽', rate: 92, digits: 0 },
  uah: { code: 'UAH', symbol: '₴', rate: 39, digits: 0 },
  byn: { code: 'BYN', symbol: 'Br', rate: 3.28, digits: 2 },
  ils: { code: 'ILS', symbol: '₪', rate: 3.68, digits: 2 },
}

const CURRENCY_ORDER = ['usd', 'eur', 'rub', 'uah', 'byn', 'ils']

const PRODUCT_PRICES = {
  starter: 9,
  standard: 15,
  pro: 40,
}

function convertFromUsd(amount, currency = 'usd') {
  const n = Number(amount || 0)
  const meta = CURRENCY_META[currency] || CURRENCY_META.usd
  return n * meta.rate
}

function formatMoney(amount, currency = 'usd', locale = 'ru-RU') {
  const meta = CURRENCY_META[currency] || CURRENCY_META.usd
  return new Intl.NumberFormat(locale, {
    style: 'currency',
    currency: meta.code,
    minimumFractionDigits: meta.digits,
    maximumFractionDigits: meta.digits,
  }).format(convertFromUsd(amount, currency))
}

function getCurrencyLabel(currency, t) {
  const labels = {
    usd: t('currencyUsd'),
    eur: t('currencyEur'),
    rub: t('currencyRub'),
    uah: t('currencyUah'),
    byn: t('currencyByn'),
    ils: t('currencyIls'),
  }
  return labels[currency] || currency.toUpperCase()
}

async function api(action, data = {}, method = 'POST') {
  const opts: any = {
    method,
    credentials: 'include',
    headers: { 'Content-Type': 'application/json' },
  }
  if (csrfTokenValue) opts.headers['X-CSRF-Token'] = csrfTokenValue
  const url =
    method === 'GET'
      ? `${API}?action=${action}&${new URLSearchParams(data)}`
      : API

  if (method === 'POST') opts.body = JSON.stringify({ action, ...data })

  const r = await fetch(url, opts)
  if (!r.ok) throw new Error(`HTTP ${r.status}`)
  return syncCsrfToken(await r.json())
}

function padId(id) {
  return String(id || 0).padStart(5, '0')
}

function safeAvatarPath(file) {
  return /^[a-zA-Z0-9._-]{1,255}$/.test(String(file || '')) ? `uploads/${file}` : 'uploads/default.png'
}

function fmtDate(str, locale = 'ru-RU') {
  if (!str) return ''
  return new Intl.DateTimeFormat(locale, { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(str))
}

function fmtDateTime(str, locale = 'ru-RU') {
  if (!str) return ''
  return new Intl.DateTimeFormat(locale, {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(str))
}

function isActive(expiresAt) {
  return new Date(expiresAt) > new Date()
}

function reveal(scope, selector, vars = {}) {
  const items = gsap.utils.toArray(selector, scope)
  if (!items.length) return
  return gsap.fromTo(
    items,
    { autoAlpha: 0, y: 22 },
    {
      autoAlpha: 1,
      y: 0,
      duration: 0.7,
      ease: 'power3.out',
      stagger: 0.06,
      overwrite: 'auto',
      clearProps: CLEAR,
      ...vars,
    }
  )
}

function BotCheck({ onPass }) {
  const zoneRef = useRef(null)
  const [pct, setPct] = useState(0)
  const [done, setDone] = useState(false)
  const [icoPos, setIcoPos] = useState({ left: '50%', top: '50%' })
  const mvRef = useRef(0)
  const doneRef = useRef(false)
  const need = 70

  useEffect(() => {
    const handler = (e) => {
      if (doneRef.current) return
      const r = zoneRef.current?.getBoundingClientRect()
      if (!r) return
      const inside = e.clientX >= r.left && e.clientX <= r.right && e.clientY >= r.top && e.clientY <= r.bottom
      if (inside) {
        setIcoPos({ left: `${e.clientX - r.left}px`, top: `${e.clientY - r.top}px` })
        mvRef.current = Math.min(mvRef.current + 2, need)
        setPct(Math.round((mvRef.current / need) * 100))
        if (mvRef.current >= need && !doneRef.current) {
          doneRef.current = true
          setDone(true)
          setTimeout(onPass, 900)
        }
      } else {
        mvRef.current = Math.max(mvRef.current - 0.4, 0)
        setPct(Math.round((mvRef.current / need) * 100))
      }
    }
    document.addEventListener('mousemove', handler)
    return () => document.removeEventListener('mousemove', handler)
  }, [onPass])

  return null
}

function ExtendDialog({ open, subText, onExtend, onNew, onClose, t }) {
  const rootRef = useRef(null)

  useGSAP(
    () => {
      if (!open) return
      gsap.fromTo(rootRef.current, { autoAlpha: 0 }, { autoAlpha: 1, duration: 0.18, ease: 'power2.out' })
      gsap.fromTo(
        '.dial-box',
        { autoAlpha: 0, y: 18, scale: 0.985 },
        { autoAlpha: 1, y: 0, scale: 1, duration: 0.42, ease: 'power3.out', clearProps: CLEAR }
      )
    },
    { scope: rootRef, dependencies: [open], revertOnUpdate: true }
  )

  return (
    <div ref={rootRef} className={`dial-ov${open ? ' open' : ''}`}>
      <div className="dial-box">
        <div className="dial-title">{t('activeSubTitle')}</div>
        <div className="dial-sub">{subText}</div>
        <div className="dial-acts">
          <button className="bp" onClick={onExtend}>{t('extend')}</button>
          <button className="bg2" onClick={onNew}>{t('createNew')}</button>
        </div>
        <button className="dial-cancel" onClick={onClose}>{t('cancel')}</button>
      </div>
    </div>
  )
}


function ToastStack({ items, onClose, t }) {
  const rootRef = useRef(null)

  useGSAP(
    () => {
      if (!items.length) return
      gsap.fromTo(
        rootRef.current?.querySelectorAll('.toast-item') || [],
        { autoAlpha: 0, x: 26, y: 10, scale: 0.985 },
        { autoAlpha: 1, x: 0, y: 0, scale: 1, duration: 0.42, stagger: 0.05, ease: 'power3.out', clearProps: CLEAR }
      )
    },
    { scope: rootRef, dependencies: [items.map((item) => item.id).join('|')], revertOnUpdate: true }
  )

  if (!items.length) return null

  return (
    <div ref={rootRef} className="toast-stack" aria-live="polite" aria-atomic="true">
      {items.map((item) => (
        <div key={item.id} className={`toast-item ${item.type || 'ok'}`}>
          <div className={`toast-ico ${item.type || 'ok'}`}>
            {item.type === 'err' ? (
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <circle cx="12" cy="12" r="10" />
                <path d="M15 9l-6 6M9 9l6 6" />
              </svg>
            ) : (
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M20 6L9 17l-5-5" />
              </svg>
            )}
          </div>
          <div className="toast-body">
            <div className="toast-title">{item.title || t('siteNotice')}</div>
            <div className="toast-text">{item.text}</div>
          </div>
          <button className="toast-close" onClick={() => onClose(item.id)} aria-label={t('toastClose')}>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M18 6L6 18M6 6l12 12" />
            </svg>
          </button>
        </div>
      ))}
    </div>
  )
}

function ConfirmDialog({ open, title, text, confirmText, cancelText, onConfirm, onClose }) {
  const rootRef = useRef(null)

  useGSAP(
    () => {
      if (!open) return
      gsap.fromTo(rootRef.current, { autoAlpha: 0 }, { autoAlpha: 1, duration: 0.18, ease: 'power2.out' })
      gsap.fromTo(
        '.confirm-box',
        { autoAlpha: 0, y: 18, scale: 0.985 },
        { autoAlpha: 1, y: 0, scale: 1, duration: 0.38, ease: 'power3.out', clearProps: CLEAR }
      )
    },
    { scope: rootRef, dependencies: [open, title, text], revertOnUpdate: true }
  )

  return (
    <div ref={rootRef} className={`confirm-ov${open ? ' open' : ''}`}>
      <div className="confirm-box">
        <div className="confirm-icon danger">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <path d="M12 9v4" />
            <path d="M12 17h.01" />
            <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
          </svg>
        </div>
        <div className="confirm-title">{title}</div>
        <div className="confirm-text">{text}</div>
        <div className="confirm-actions">
          <button className="bg2" onClick={onClose}>{cancelText}</button>
          <button className="danger-btn" onClick={onConfirm}>{confirmText}</button>
        </div>
      </div>
    </div>
  )
}

function ResultDialog({ open, title, text, licenseKey, onCopy, onClose, t }) {
  const rootRef = useRef(null)

  useGSAP(
    () => {
      if (!open) return
      gsap.fromTo(rootRef.current, { autoAlpha: 0 }, { autoAlpha: 1, duration: 0.18, ease: 'power2.out' })
      gsap.fromTo(
        '.result-box',
        { autoAlpha: 0, y: 20, scale: 0.982 },
        { autoAlpha: 1, y: 0, scale: 1, duration: 0.42, ease: 'power3.out', clearProps: CLEAR }
      )
      gsap.fromTo(
        '.result-key-wrap',
        { autoAlpha: 0, y: 12 },
        { autoAlpha: 1, y: 0, duration: 0.34, ease: 'power2.out', delay: 0.08, clearProps: CLEAR }
      )
    },
    { scope: rootRef, dependencies: [open, title, text, licenseKey], revertOnUpdate: true }
  )

  return (
    <div ref={rootRef} className={`result-ov${open ? ' open' : ''}`}>
      <div className="result-box">
        <div className="confirm-icon ok">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <path d="M20 6L9 17l-5-5" />
          </svg>
        </div>
        <div className="confirm-title">{title}</div>
        <div className="confirm-text">{text}</div>

        {licenseKey && (
          <div className="result-key-wrap">
            <div className="result-key-label">{t('licenseKey')}</div>
            <div className="result-key-box">
              <code>{licenseKey}</code>
              <button className="ia" onClick={() => onCopy(licenseKey)}>{t('copyKey')}</button>
            </div>
          </div>
        )}

        <button className="bp result-ok" onClick={onClose}>{t('nice')}</button>
      </div>
    </div>
  )
}

function MainTab({ onGoToStore, t }) {
  const rootRef = useRef(null)
  const features = useMemo(() => getFeatures(t), [t])

  useGSAP(
    () => {
      const tl = gsap.timeline({ defaults: { ease: 'power3.out' } })
      tl.fromTo('.ht-hero', { autoAlpha: 0, y: 34 }, { autoAlpha: 1, y: 0, duration: 0.82, clearProps: CLEAR })
        .fromTo(
          '.ht-hero .outline, .ht-hero .hi',
          { autoAlpha: 0, x: -24 },
          { autoAlpha: 1, x: 0, duration: 0.62, stagger: 0.08, clearProps: CLEAR },
          '-=0.46'
        )
        .fromTo('.hs, .ha', { autoAlpha: 0, y: 20 }, { autoAlpha: 1, y: 0, duration: 0.55, stagger: 0.08, clearProps: CLEAR }, '-=0.28')
        .fromTo('.sc', { autoAlpha: 0, y: 22 }, { autoAlpha: 1, y: 0, duration: 0.6, stagger: 0.08, clearProps: CLEAR }, '-=0.15')
        .fromTo('.sep, .feat-card', { autoAlpha: 0, y: 18 }, { autoAlpha: 1, y: 0, duration: 0.54, stagger: 0.05, clearProps: CLEAR }, '-=0.18')
    },
    { scope: rootRef }
  )

  return (
    <div ref={rootRef} style={{ padding: '32px 0 80px' }}>
      <h1 className="ht-hero" data-gsap="hero">
        {t('mainTitle1')}
        <br />
        <span className="outline">{t('mainTitle2')}</span>
        <span className="hi">{t('mainTitle3')}</span>
      </h1>

      <p className="hs" data-gsap="hero">
        {t('heroDesc').split('\n').map((line, i) => (
          <span key={i}>
            {line}
            {i === 0 && <br />}
          </span>
        ))}
      </p>

      <div className="ha" data-gsap="hero">
        <button className="bp" onClick={onGoToStore}>
          {t('exploreStore')}
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
            <path d="M5 12h14M12 5l7 7-7 7" />
          </svg>
        </button>
        <a href="https://t.me/sakurov1337" target="_blank" rel="noreferrer" className="bg2">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12L8.32 13.617l-2.96-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.828.942z" />
          </svg>
          {t('telegram')}
        </a>
      </div>

      <div className="cr">
        <div className="sc" data-gsap="card">
          <div className="sl"><span className="tdot g" />{t('techSupport')}</div>
          <div className="sv">24/7 <span className="u">{t('online')}</span></div>
          <div className="sd">{t('supportAnswer')}</div>
          <div style={{ marginTop: '16px', display: 'flex', gap: '5px', alignItems: 'flex-end' }}>
            {[3, 5, 4, 6, 4, 5, 6].map((h, i) => (
              <div
                key={i}
                style={{
                  flex: 1,
                  height: `${h * 4}px`,
                  borderRadius: '3px',
                  background: i === 6 ? 'var(--a)' : 'var(--b2)',
                  boxShadow: i === 6 ? '0 0 8px var(--a)' : 'none',
                }}
              />
            ))}
          </div>
        </div>

        <div className="sc" data-gsap="card">
          <div className="sl"><span className="tdot g" />{t('liveDebugger')}</div>
          <div className="sv">0 <span className="u">{t('errors')}</span></div>
          <div className="sd">{t('autoFix')}</div>
          <div style={{ marginTop: '16px', display: 'flex', flexDirection: 'column', gap: '5px' }}>
            {[t('syntaxCheck'), t('logicScan'), t('perfAudit')].map((item, i) => (
              <div key={i} style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                <div style={{ width: '5px', height: '5px', borderRadius: '50%', background: 'var(--a)', boxShadow: '0 0 6px var(--a)', flexShrink: 0 }} />
                <span style={{ fontFamily: 'var(--fm)', fontSize: '9px', color: 'var(--m2)', textTransform: 'uppercase', letterSpacing: '.1em' }}>{item}</span>
                <div style={{ flex: 1, height: '1px', background: 'var(--b2)' }} />
                <span style={{ fontFamily: 'var(--fm)', fontSize: '9px', color: 'var(--a)' }}>OK</span>
              </div>
            ))}
          </div>
        </div>

        <div className="sc" data-gsap="card">
          <div className="sl"><span className="tdot g" />{t('qualityCode')}</div>
          <div className="sv">100<span className="u">%</span></div>
          <div className="sd">{t('enterpriseStandard')}</div>
          <div style={{ marginTop: '16px' }}>
            <div style={{ height: '3px', background: 'var(--b)', borderRadius: '3px', overflow: 'hidden' }}>
              <div style={{ height: '100%', width: '100%', background: 'var(--a)', borderRadius: '3px', boxShadow: '0 0 8px var(--a)' }} />
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: '6px', fontFamily: 'var(--fm)', fontSize: '8px', color: 'var(--m)', textTransform: 'uppercase', letterSpacing: '.12em' }}>
              <span>0%</span>
              <span style={{ color: 'var(--a)' }}>100% {t('clean')}</span>
            </div>
          </div>
        </div>
      </div>

      <div className="sep" data-gsap="sep">{t('featureSection')}</div>

      <div className="feat-grid">
        {features.map((item) => (
          <div key={item.num} className="feat-card" data-gsap="feature">
            <div className="feat-num">{item.num}</div>
            <div className="feat-label">{item.label}</div>
            <div className="feat-desc">{item.desc}</div>
            <div className="feat-tag">{item.tag}</div>
          </div>
        ))}
      </div>
    </div>
  )
}

function StoreTab({ onBuy, t, locale, currency, onCurrencyChange }) {
  const rootRef = useRef(null)
  const products = useMemo(() => getStoreProducts(t), [t])

  useGSAP(
    () => {
      reveal(rootRef.current, '[data-gsap="store-head"], [data-gsap="product-card"]')
      gsap.fromTo(
        '.pp2, .ppr',
        { autoAlpha: 0, y: 10 },
        { autoAlpha: 1, y: 0, duration: 0.36, stagger: 0.04, ease: 'power2.out', clearProps: CLEAR }
      )
    },
    { scope: rootRef, dependencies: [currency, locale], revertOnUpdate: true }
  )

  return (
    <div ref={rootRef} style={{ padding: '60px 0 80px' }}>
      <div className="sh2" data-gsap="store-head">
        <h2 className="stit">{t('products')}</h2>
        <div className="curr-bar">
          {CURRENCY_ORDER.map((c) => (
            <button key={c} className={`cc${currency === c ? ' active' : ''}`} onClick={() => onCurrencyChange(c)}>
              {CURRENCY_META[c].symbol} {CURRENCY_META[c].code}
            </button>
          ))}
        </div>
      </div>

      <div className="pg">
        {products.map((p) => (
          <div key={p.id} className={`pc${p.featured ? ' ft' : ''}`} data-gsap="product-card">
            <div className="pt">
              {p.tier}
              {p.badge && <span className="pbadge">{p.badge}</span>}
            </div>
            <div className="pp2">{formatMoney(PRODUCT_PRICES[p.id], currency, locale)}</div>
            <div className="ppr">{p.period}</div>
            <ul className="pf">{p.features.map((f, i) => <li key={i}>{f}</li>)}</ul>
            <button
              className={p.btnClass}
              style={{ width: '100%', justifyContent: 'center' }}
              onClick={() => onBuy(PRODUCT_PRICES[p.id], p.id, p.name)}
            >
              {t('purchase')}
            </button>
          </div>
        ))}
      </div>
    </div>
  )
}

function AuthTab({ onSuccess, t }) {
  const rootRef = useRef(null)
  const [tab, setTab] = useState('l')
  const [msg, setMsg] = useState(null)
  const [lu, setLu] = useState('')
  const [lp, setLp] = useState('')
  const [ru, setRu] = useState('')
  const [re, setRe] = useState('')
  const [rp, setRp] = useState('')
  const [forgotOpen, setForgotOpen] = useState(false)
  const [resetStep, setResetStep] = useState(1)
  const [fe, setFe] = useState('')
  const [fc, setFc] = useState('')
  const [fnp, setFnp] = useState('')
  const [fcp, setFcp] = useState('')

  useGSAP(
    () => {
      reveal(rootRef.current, '.auth-top, .atg, .fmsg, form .field, form .bp, .auth-footer', { stagger: 0.05 })
      gsap.fromTo('.atg .active', { scaleX: 0.98, autoAlpha: 0.85 }, { scaleX: 1, autoAlpha: 1, duration: 0.25, ease: 'power2.out', clearProps: CLEAR })
    },
    { scope: rootRef, dependencies: [tab, msg, forgotOpen, resetStep], revertOnUpdate: true }
  )

  function openForgot() {
    setForgotOpen(true)
    setResetStep(1)
    setMsg(null)
    setFe('')
    setFc('')
    setFnp('')
    setFcp('')
  }

  function closeForgot() {
    setForgotOpen(false)
    setResetStep(1)
    setMsg(null)
    setFc('')
    setFnp('')
    setFcp('')
  }

  async function doLogin(e) {
    e.preventDefault()
    setMsg(null)
    try {
      const d = await api('login', { username: lu, password: lp })
      if (d.status === 'success') onSuccess()
      else setMsg({ text: d.message || t('error'), type: 'err' })
    } catch {
      setMsg({ text: t('connectionError'), type: 'err' })
    }
  }

  async function doReg(e) {
    e.preventDefault()
    setMsg(null)
    try {
      const d = await api('register', { username: ru, email: re, password: rp })
      if (d.status === 'success') onSuccess()
      else setMsg({ text: d.message || t('error'), type: 'err' })
    } catch {
      setMsg({ text: t('connectionError'), type: 'err' })
    }
  }

  async function doForgotRequest(e) {
    e.preventDefault()
    setMsg(null)
    try {
      const d = await api('forgot_password_request', { email: fe })
      if (d.status === 'success') {
        setResetStep(2)
        setMsg({ text: t('resetCodeSent'), type: 'ok' })
      } else {
        setMsg({ text: d.message || t('error'), type: 'err' })
      }
    } catch {
      setMsg({ text: t('connectionError'), type: 'err' })
    }
  }

  async function doForgotReset(e) {
    e.preventDefault()
    setMsg(null)
    try {
      const d = await api('reset_password_by_code', { email: fe, code: fc, new_password: fnp, confirm_password: fcp })
      if (d.status === 'success') {
        setLp('')
        setForgotOpen(false)
        setResetStep(1)
        setFc('')
        setFnp('')
        setFcp('')
        setMsg({ text: t('passwordResetDone'), type: 'ok' })
      } else {
        setMsg({ text: d.message || t('error'), type: 'err' })
      }
    } catch {
      setMsg({ text: t('connectionError'), type: 'err' })
    }
  }

  return (
    <div ref={rootRef} className="aw">
      <div className="auth-top">
        <div className="auth-top-side">
          <div className="auth-big-label">
            {tab === 'l' ? <><span>{t('sign')}</span><br />{t('inDot')}</> : <><span>{t('new')}</span><br />{t('userDot')}</>}
          </div>
          <div className="auth-hint">
            {tab === 'l' ? t('signInHint') : t('createHint')}
          </div>
        </div>
        <div className="auth-top-divider" />
        <div className="auth-top-side auth-meta">
          <div className="auth-meta-block">
            <div className="auth-meta-muted">{t('platform')}</div>
            <div>GOD DEV</div>
            <div className="auth-meta-muted auth-meta-gap">{t('status')}</div>
            <div className="auth-meta-live">{t('onlineStatus')}</div>
          </div>
        </div>
      </div>

      <div className="atg">
        <button className={tab === 'l' ? 'active' : ''} onClick={() => { setTab('l'); setMsg(null); setForgotOpen(false); setResetStep(1) }}>{t('signIn')}</button>
        <button className={tab === 'r' ? 'active' : ''} onClick={() => { setTab('r'); setMsg(null); setForgotOpen(false); setResetStep(1) }}>{t('register')}</button>
      </div>

      {msg && (
        <div className={`fmsg ${msg.type}`}>
          {msg.type === 'err' ? (
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <circle cx="12" cy="12" r="10" />
              <line x1="15" y1="9" x2="9" y2="15" />
              <line x1="9" y1="9" x2="15" y2="15" />
            </svg>
          ) : (
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <polyline points="20 6 9 17 4 12" />
            </svg>
          )}
          {msg.text}
        </div>
      )}

      {tab === 'l' && !forgotOpen && (
        <form onSubmit={doLogin}>
          <div className="field">
            <label>{t('username')}</label>
            <input type="text" value={lu} onChange={(e) => setLu(e.target.value.slice(0, 100))} maxLength={100} placeholder="hauntz1337" autoComplete="username" />
          </div>
          <div className="field">
            <label>{t('password')}</label>
            <input type="password" value={lp} onChange={(e) => setLp(e.target.value.slice(0, 255))} maxLength={255} placeholder="••••••••" autoComplete="current-password" />
          </div>
          <button type="submit" className="bp full-btn">
            {t('signIn')} →
          </button>
          <div className="auth-footer">
            <span className="auth-link" onClick={openForgot}>{t('forgotPassword')}</span>
          </div>
          <div className="auth-footer">
            {t('noAccount')} <span className="auth-link" onClick={() => { setTab('r'); setMsg(null) }}>{t('create')}</span>
          </div>
        </form>
      )}

      {tab === 'l' && forgotOpen && (
        <form onSubmit={resetStep === 1 ? doForgotRequest : doForgotReset}>
          <div className="field">
            <label>{t('email')}</label>
            <input type="email" value={fe} onChange={(e) => setFe(e.target.value.slice(0, 100))} maxLength={100} placeholder="you@soulware.space" autoComplete="email" />
          </div>
          {resetStep === 2 && (
            <>
              <div className="field">
                <label>{t('resetCode')}</label>
                <input type="text" value={fc} onChange={(e) => setFc(e.target.value.replace(/\D/g, '').slice(0, 6))} placeholder="123456" inputMode="numeric" autoComplete="one-time-code" />
              </div>
              <div className="field">
                <label>{t('newPassword')}</label>
                <input type="password" value={fnp} onChange={(e) => setFnp(e.target.value.slice(0, 255))} maxLength={255} placeholder="••••••••" autoComplete="new-password" />
              </div>
              <div className="field">
                <label>{t('confirmPassword')}</label>
                <input type="password" value={fcp} onChange={(e) => setFcp(e.target.value.slice(0, 255))} maxLength={255} placeholder="••••••••" autoComplete="new-password" />
              </div>
            </>
          )}
          <button type="submit" className="bp full-btn">
            {resetStep === 1 ? `${t('sendCode')} →` : `${t('resetPassword')} →`}
          </button>
          {resetStep === 2 && (
            <div className="auth-footer">
              <span className="auth-link" onClick={() => { setResetStep(1); setMsg(null); setFc(''); setFnp(''); setFcp('') }}>{t('sendAgain')}</span>
            </div>
          )}
          <div className="auth-footer">
            <span className="auth-link" onClick={closeForgot}>{t('backToLogin')}</span>
          </div>
        </form>
      )}

      {tab === 'r' && (
        <form onSubmit={doReg}>
          <div className="field">
            <label>{t('username')}</label>
            <input type="text" value={ru} onChange={(e) => setRu(e.target.value.slice(0, 50))} maxLength={50} placeholder="hauntz1337" autoComplete="username" />
          </div>
          <div className="field">
            <label>{t('email')}</label>
            <input type="email" value={re} onChange={(e) => setRe(e.target.value.slice(0, 100))} maxLength={100} placeholder="you@soulware.space" autoComplete="email" />
          </div>
          <div className="field">
            <label>{t('password')}</label>
            <input type="password" value={rp} onChange={(e) => setRp(e.target.value.slice(0, 255))} maxLength={255} placeholder="••••••••" autoComplete="new-password" />
          </div>
          <button type="submit" className="bp full-btn">
            {t('createAccount')} →
          </button>
          <div className="auth-footer">
            {t('haveAccount')} <span className="auth-link" onClick={() => { setTab('l'); setMsg(null) }}>{t('login')}</span>
          </div>
        </form>
      )}
    </div>
  )
}


function TicketStatusPill({ status, t }) {
  const map = {
    open: { label: t('ticketOpen'), cls: 'open' },
    answered: { label: t('ticketAnswered'), cls: 'answered' },
    closed: { label: t('ticketClosed'), cls: 'closed' },
  }
  const meta = map[status] || map.open
  return <span className={`ticket-pill ${meta.cls}`}>{meta.label}</span>
}

function TicketsSection({ isAdmin, onToast, t, locale, currentUserId }) {
  const [tickets, setTickets] = useState([])
  const [filter, setFilter] = useState('')
  const [loading, setLoading] = useState(true)
  const [selected, setSelected] = useState(null)
  const [detailLoading, setDetailLoading] = useState(false)
  const [subject, setSubject] = useState('')
  const [category, setCategory] = useState('general')
  const [priority, setPriority] = useState('normal')
  const [message, setMessage] = useState('')
  const [reply, setReply] = useState('')

  const loadTickets = useCallback(async (keepSelected = true) => {
    setLoading(true)
    try {
      const d = await api('get_tickets', filter ? { status: filter } : {})
      if (d.status === 'success') {
        setTickets(d.tickets || [])
        if (keepSelected && selected?.ticket?.id) {
          const hit = (d.tickets || []).find((item) => Number(item.id) === Number(selected.ticket.id))
          if (!hit) setSelected(null)
        }
      } else {
        onToast(d.message || t('error'), 'err')
      }
    } catch {
      onToast(t('connectionError'), 'err')
    } finally {
      setLoading(false)
    }
  }, [filter, onToast, selected?.ticket?.id, t])

  useEffect(() => {
    loadTickets(false)
  }, [loadTickets])

  async function openTicket(ticketId) {
    setDetailLoading(true)
    try {
      const d = await api('get_ticket', { ticket_id: ticketId })
      if (d.status === 'success') {
        setSelected(d)
      } else {
        onToast(d.message || t('error'), 'err')
      }
    } catch {
      onToast(t('connectionError'), 'err')
    } finally {
      setDetailLoading(false)
    }
  }

  async function createTicket(e) {
    e.preventDefault()
    try {
      const d = await api('create_ticket', { subject, category, priority, message })
      if (d.status === 'success') {
        setSubject('')
        setCategory('general')
        setPriority('normal')
        setMessage('')
        onToast(t('ticketCreatedOk'), 'ok')
        await loadTickets(false)
        if (d.ticket_id) openTicket(d.ticket_id)
      } else {
        onToast(d.message || t('error'), 'err')
      }
    } catch {
      onToast(t('connectionError'), 'err')
    }
  }

  async function sendReply(e) {
    e.preventDefault()
    if (!selected?.ticket?.id || !reply.trim()) return
    try {
      const d = await api('reply_ticket', { ticket_id: selected.ticket.id, message: reply })
      if (d.status === 'success') {
        setReply('')
        onToast(t('ticketReplyOk'), 'ok')
        await openTicket(selected.ticket.id)
        await loadTickets()
      } else {
        onToast(d.message || t('error'), 'err')
      }
    } catch {
      onToast(t('connectionError'), 'err')
    }
  }

  async function setStatus(nextStatus) {
    if (!selected?.ticket?.id) return
    try {
      const d = await api('update_ticket_status', { ticket_id: selected.ticket.id, status: nextStatus })
      if (d.status === 'success') {
        onToast(t('ticketStatusSaved'), 'ok')
        await openTicket(selected.ticket.id)
        await loadTickets()
      } else {
        onToast(d.message || t('error'), 'err')
      }
    } catch {
      onToast(t('connectionError'), 'err')
    }
  }

  return (
    <div className="tickets-grid">
      {!isAdmin && (
        <div className="pcard">
          <div className="pch"><span className="pct">{t('createTicket')}</span></div>
          <form onSubmit={createTicket} className="ticket-form">
            <div className="ticket-form-grid">
              <div className="field">
                <label>{t('ticketSubject')}</label>
                <input value={subject} onChange={(e) => setSubject(e.target.value)} maxLength={140} placeholder={t('ticketSubjectPlaceholder')} />
              </div>
              <div className="field">
                <label>{t('ticketCategory')}</label>
                <select value={category} onChange={(e) => setCategory(e.target.value)} className="inline-select full-select">
                  <option value="general">{t('ticketCatGeneral')}</option>
                  <option value="payment">{t('ticketCatPayment')}</option>
                  <option value="license">{t('ticketCatLicense')}</option>
                  <option value="technical">{t('ticketCatTechnical')}</option>
                </select>
              </div>
              <div className="field">
                <label>{t('ticketPriority')}</label>
                <select value={priority} onChange={(e) => setPriority(e.target.value)} className="inline-select full-select">
                  <option value="low">{t('priorityLow')}</option>
                  <option value="normal">{t('priorityNormal')}</option>
                  <option value="high">{t('priorityHigh')}</option>
                </select>
              </div>
            </div>
            <div className="field">
              <label>{t('message')}</label>
              <textarea value={message} onChange={(e) => setMessage(e.target.value.slice(0, 5000))} maxLength={5000} rows={5} placeholder={t('ticketMessagePlaceholder')} />
            </div>
            <button className="bp" type="submit">{t('sendTicket')}</button>
          </form>
        </div>
      )}

      <div className="pcard">
        <div className="pch ticket-head">
          <span className="pct">{isAdmin ? t('allTickets') : t('myTickets')}</span>
          <div className="ticket-filters">
            {['', 'open', 'answered', 'closed'].map((item) => (
              <button key={item || 'all'} className={`cc${filter === item ? ' active' : ''}`} onClick={() => setFilter(item)}>
                {item ? t(`ticket${item[0].toUpperCase()}${item.slice(1)}`) : t('all')}
              </button>
            ))}
          </div>
        </div>

        {loading ? (
          <div className="es"><div className="ei">⌛</div>{t('loading')}</div>
        ) : !tickets.length ? (
          <div className="es"><div className="ei">🎫</div>{t('noTickets')}</div>
        ) : (
          <div className="ticket-list">
            {tickets.map((ticket) => (
              <button key={ticket.id} className={`ticket-row${selected?.ticket?.id === ticket.id ? ' active' : ''}`} onClick={() => openTicket(ticket.id)}>
                <div className="ticket-row-top">
                  <div className="ticket-subject">{ticket.subject}</div>
                  <TicketStatusPill status={ticket.status} t={t} />
                </div>
                <div className="ticket-meta">
                  <span>#{padId(ticket.id)}</span>
                  <span>{t('ticketCategory')}: {ticket.category}</span>
                  <span>{t('ticketPriority')}: {t(`priority${(ticket.priority || 'normal')[0].toUpperCase()}${(ticket.priority || 'normal').slice(1)}`)}</span>
                  {isAdmin && <span>{ticket.username}</span>}
                  <span>{fmtDateTime(ticket.last_message_at || ticket.updated_at || ticket.created_at, locale)}</span>
                </div>
              </button>
            ))}
          </div>
        )}
      </div>

      <div className="pcard ticket-detail-card">
        <div className="pch">
          <span className="pct">{t('ticketDialog')}</span>
          {selected?.ticket && <TicketStatusPill status={selected.ticket.status} t={t} />}
        </div>

        {!selected ? (
          <div className="es"><div className="ei">💬</div>{t('selectTicket')}</div>
        ) : detailLoading ? (
          <div className="es"><div className="ei">⌛</div>{t('loading')}</div>
        ) : (
          <>
            <div className="ticket-detail-top">
              <div>
                <div className="ticket-detail-subject">{selected.ticket.subject}</div>
                <div className="ticket-meta">
                  <span>#{padId(selected.ticket.id)}</span>
                  <span>{selected.ticket.username}</span>
                  <span>{fmtDateTime(selected.ticket.created_at, locale)}</span>
                </div>
              </div>
              <div className="ticket-actions">
                {selected.ticket.status !== 'closed' && <button className="bg2" onClick={() => setStatus('closed')}>{t('closeTicket')}</button>}
                {isAdmin && selected.ticket.status === 'closed' && <button className="bg2" onClick={() => setStatus('open')}>{t('reopenTicket')}</button>}
                {isAdmin && selected.ticket.status !== 'answered' && selected.ticket.status !== 'closed' && <button className="bp" onClick={() => setStatus('answered')}>{t('markAnswered')}</button>}
              </div>
            </div>

            <div className="ticket-thread">
              {(selected.messages || []).map((item) => {
                const mine = item.sender_role === 'admin' ? isAdmin : Number(item.user_id) === Number(currentUserId)
                return (
                  <div key={item.id} className={`ticket-msg ${mine ? 'mine' : ''} ${item.sender_role === 'admin' ? 'admin' : 'user'}`}>
                    <div className="ticket-msg-head">
                      <span>{item.sender_role === 'admin' ? t('adminReply') : item.username || t('userReply')}</span>
                      <span>{fmtDateTime(item.created_at, locale)}</span>
                    </div>
                    <div className="ticket-msg-body">{item.message}</div>
                  </div>
                )
              })}
            </div>

            {(selected.ticket.status !== 'closed' || isAdmin) && (
              <form onSubmit={sendReply} className="ticket-reply-form">
                <div className="field">
                  <label>{t('reply')}</label>
                  <textarea rows={4} value={reply} onChange={(e) => setReply(e.target.value.slice(0, 5000))} maxLength={5000} placeholder={t('replyPlaceholder')} />
                </div>
                <button className="bp" type="submit">{t('sendReply')}</button>
              </form>
            )}
          </>
        )}
      </div>
    </div>
  )
}

function ConfigsSection({ onToast, t }) {
  const [configs, setConfigs] = useState([])
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [deleting, setDeleting] = useState(null)
  const [newName, setNewName] = useState('')
  const [newSlot, setNewSlot] = useState('1')
  const [loadedName, setLoadedName] = useState(null)

  const loadList = useCallback(async () => {
    setLoading(true)
    try {
      const d = await api('list_configs', {})
      if (d.status === 'success') {
        setConfigs(d.configs || [])
      } else {
        onToast(d.message || t('error'), 'err')
      }
    } catch {
      onToast(t('connectionError'), 'err')
    } finally {
      setLoading(false)
    }
  }, [onToast, t])

  useEffect(() => { loadList() }, [loadList])

  async function handleSave(e) {
    e.preventDefault()
    if (!newName.trim()) { onToast(t('cfgNameRequired'), 'err'); return }
    setSaving(true)
    try {
      const d = await api('save_config', {
        config_name: newName.trim(),
        slot: parseInt(newSlot, 10) || 1,
      })
      if (d.status === 'success') {
        onToast(t('cfgSaved'), 'ok')
        setNewName('')
        await loadList()
      } else {
        onToast(d.message || t('error'), 'err')
      }
    } catch {
      onToast(t('connectionError'), 'err')
    } finally {
      setSaving(false)
    }
  }

  async function handleLoad(cfg) {
    try {
      const d = await api('load_config', { config_id: cfg.id })
      if (d.status === 'success') {
        setLoadedName(cfg.config_name || cfg.name)
        onToast(t('cfgLoaded'), 'ok')
      } else {
        onToast(d.message || t('error'), 'err')
      }
    } catch {
      onToast(t('connectionError'), 'err')
    }
  }

  async function handleDelete(cfg) {
    setDeleting(cfg.id)
    try {
      const d = await api('delete_config', { config_id: cfg.id })
      if (d.status === 'success') {
        onToast(t('cfgDeleted'), 'ok')
        if (loadedName === (cfg.config_name || cfg.name)) setLoadedName(null)
        await loadList()
      } else {
        onToast(d.message || t('error'), 'err')
      }
    } catch {
      onToast(t('connectionError'), 'err')
    } finally {
      setDeleting(null)
    }
  }

  const SLOTS = ['1', '2', '3', '4', '5']

  return (
    <div>
      <div className="pcard">
        <div className="pch"><span className="pct">{t('cfgSaveNew')}</span></div>
        <form onSubmit={handleSave} className="ticket-form">
          <div className="ticket-form-grid">
            <div className="field">
              <label>{t('cfgName')}</label>
              <input
                value={newName}
                onChange={(e) => setNewName(e.target.value.slice(0, 64))}
                maxLength={64}
                placeholder={t('cfgNamePlaceholder')}
              />
            </div>
            <div className="field">
              <label>{t('cfgSlot')}</label>
              <select
                value={newSlot}
                onChange={(e) => setNewSlot(e.target.value)}
                className="inline-select full-select"
              >
                {SLOTS.map((s) => (
                  <option key={s} value={s}>{t('cfgSlotLabel')} {s}</option>
                ))}
              </select>
            </div>
          </div>
          <button className="bp" type="submit" disabled={saving}>
            {saving ? t('cfgSaving') : t('cfgSaveBtn')}
          </button>
        </form>
      </div>

      <div className="pcard">
        <div className="pch">
          <span className="pct">{t('cfgCloudList')}</span>
          <button className="bg2" style={{ padding: '7px 14px', fontSize: '8px' }} onClick={loadList}>
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <polyline points="23 4 23 10 17 10" />
              <polyline points="1 20 1 14 7 14" />
              <path d="M3.51 9a9 9 0 0114.36-3.36L23 10M1 14l5.13 4.36A9 9 0 0020.49 15" />
            </svg>
            {t('cfgRefresh')}
          </button>
        </div>

        {loading ? (
          <div className="es"><div className="ei">⌛</div>{t('loading')}</div>
        ) : configs.length === 0 ? (
          <div className="es"><div className="ei">☁️</div>{t('cfgEmpty')}</div>
        ) : (
          <div className="ticket-list">
            {configs.map((cfg) => {
              const name = cfg.config_name || cfg.name || '—'
              const isActive = loadedName === name
              return (
                <div
                  key={cfg.id}
                  className={`ticket-row${isActive ? ' active' : ''}`}
                  style={{ display: 'flex', alignItems: 'center', gap: '10px', cursor: 'default' }}
                >
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <div className="ticket-row-top">
                      <div className="ticket-subject" style={{ display: 'flex', alignItems: 'center', gap: '7px' }}>
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                          <path d="M18 20V10" /><path d="M12 20V4" /><path d="M6 20v-6" />
                        </svg>
                        {name}
                      </div>
                      {isActive && (
                        <span className="ticket-pill answered">{t('cfgActive')}</span>
                      )}
                    </div>
                    <div className="ticket-meta">
                      <span>#{String(cfg.id || 0).padStart(5, '0')}</span>
                      {cfg.slot > 0 && <span>{t('cfgSlot')} {cfg.slot}</span>}
                      {cfg.updated_at && <span>{fmtDateTime(cfg.updated_at)}</span>}
                    </div>
                  </div>

                  <div style={{ display: 'flex', gap: '6px', flexShrink: 0 }}>
                    <button
                      className="ia"
                      onClick={() => handleLoad(cfg)}
                      title={t('cfgLoad')}
                    >
                      <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" />
                        <polyline points="7 10 12 15 17 10" />
                        <line x1="12" y1="15" x2="12" y2="3" />
                      </svg>
                      {t('cfgLoad')}
                    </button>
                    <button
                      className="ia dn"
                      onClick={() => handleDelete(cfg)}
                      disabled={deleting === cfg.id}
                      title={t('cfgDelete')}
                    >
                      <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <polyline points="3 6 5 6 21 6" />
                        <path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6" />
                        <path d="M10 11v6M14 11v6" />
                      </svg>
                      {deleting === cfg.id ? '...' : t('cfgDelete')}
                    </button>
                  </div>
                </div>
              )
            })}
          </div>
        )}
      </div>
    </div>
  )
}

function AdminSection({ onToast, t }) {
  const [stats, setStats] = useState(null)
  const [users, setUsers] = useState([])
  const [search, setSearch] = useState('')
  const [selectedId, setSelectedId] = useState(null)
  const [form, setForm] = useState(null)
  const [loading, setLoading] = useState(true)

  const loadDashboard = useCallback(async () => {
    try {
      const d = await api('admin_get_dashboard')
      if (d.status === 'success') setStats(d.stats || null)
    } catch {
      onToast(t('connectionError'), 'err')
    }
  }, [onToast, t])

  const loadUsers = useCallback(async (query = '') => {
    setLoading(true)
    try {
      const d = await api('admin_get_users', query ? { search: query } : {})
      if (d.status === 'success') {
        const nextUsers = d.users || []
        setUsers(nextUsers)
        const selected = nextUsers.find((item) => Number(item.id) === Number(selectedId)) || nextUsers[0] || null
        setSelectedId(selected?.id || null)
        setForm(selected ? {
          user_id: selected.id,
          username: selected.username || '',
          email: selected.email || '',
          balance: selected.balance || 0,
          is_admin: !!Number(selected.is_admin),
          admin_level: Number(selected.admin_level || 0),
          can_ban: !!Number(selected.can_ban),
          is_banned: !!Number(selected.is_banned),
          ban_reason: selected.ban_reason || '',
        } : null)
      } else {
        onToast(d.message || t('error'), 'err')
      }
    } catch {
      onToast(t('connectionError'), 'err')
    } finally {
      setLoading(false)
    }
  }, [onToast, selectedId, t])

  useEffect(() => {
    loadDashboard()
    loadUsers('')
  }, [loadDashboard, loadUsers])

  function selectUser(user) {
    setSelectedId(user.id)
    setForm({
      user_id: user.id,
      username: user.username || '',
      email: user.email || '',
      balance: user.balance || 0,
      is_admin: !!Number(user.is_admin),
      admin_level: Number(user.admin_level || 0),
      can_ban: !!Number(user.can_ban),
      is_banned: !!Number(user.is_banned),
      ban_reason: user.ban_reason || '',
    })
  }

  async function saveUser(e) {
    e.preventDefault()
    if (!form) return
    try {
      const d = await api('admin_update_user', form)
      if (d.status === 'success') {
        onToast(t('adminSaved'), 'ok')
        await loadDashboard()
        await loadUsers(search)
      } else {
        onToast(d.message || t('error'), 'err')
      }
    } catch {
      onToast(t('connectionError'), 'err')
    }
  }

  return (
    <div className="admin-wrap">
      <div className="pcard">
        <div className="pch"><span className="pct">{t('adminDashboard')}</span></div>
        <div className="admin-stats">
          <div className="admin-stat"><span>{t('usersTotal')}</span><strong>{stats?.users_total ?? '—'}</strong></div>
          <div className="admin-stat"><span>{t('bannedUsers')}</span><strong>{stats?.users_banned ?? '—'}</strong></div>
          <div className="admin-stat"><span>{t('activeLicenses')}</span><strong>{stats?.licenses_active ?? '—'}</strong></div>
          <div className="admin-stat"><span>{t('openTickets')}</span><strong>{stats?.tickets_open ?? '—'}</strong></div>
          <div className="admin-stat"><span>{t('waitingTickets')}</span><strong>{stats?.tickets_waiting ?? '—'}</strong></div>
          <div className="admin-stat"><span>{t('totalSales')}</span><strong>${Number(stats?.sales_total || 0).toFixed(2)}</strong></div>
        </div>
      </div>

      <div className="pcard">
        <div className="pch admin-head-row">
          <span className="pct">{t('usersManage')}</span>
          <form className="admin-search" onSubmit={(e) => { e.preventDefault(); loadUsers(search) }}>
            <input value={search} onChange={(e) => setSearch(e.target.value.slice(0, 100))} maxLength={100} placeholder={t('searchUsers')} />
            <button className="bg2" type="submit">{t('find')}</button>
          </form>
        </div>

        <div className="admin-grid">
          <div className="admin-users-list">
            {loading ? (
              <div className="es"><div className="ei">⌛</div>{t('loading')}</div>
            ) : users.length === 0 ? (
              <div className="es"><div className="ei">👤</div>{t('noUsers')}</div>
            ) : users.map((item) => (
              <button key={item.id} className={`admin-user-row${selectedId === item.id ? ' active' : ''}`} onClick={() => selectUser(item)}>
                <div className="ticket-row-top">
                  <div className="ticket-subject">{item.username}</div>
                  <div className="admin-user-badges">
                    {!!Number(item.is_admin) && <span className="mini-badge admin">ADMIN</span>}
                    {!!Number(item.is_banned) && <span className="mini-badge ban">BAN</span>}
                  </div>
                </div>
                <div className="ticket-meta">
                  <span>#{padId(item.id)}</span>
                  <span>{item.email}</span>
                </div>
                <div className="ticket-meta">
                  <span>{t('balance')}: ${Number(item.balance || 0).toFixed(2)}</span>
                  <span>{t('licenses')}: {item.active_licenses || 0}</span>
                  <span>{t('tickets')}: {item.open_tickets || 0}</span>
                </div>
              </button>
            ))}
          </div>

          <div className="admin-editor">
            {!form ? (
              <div className="es"><div className="ei">⚙️</div>{t('selectUserAdmin')}</div>
            ) : (
              <form onSubmit={saveUser} className="ticket-form">
                <div className="ticket-form-grid two-col">
                  <div className="field">
                    <label>{t('username')}</label>
                    <input value={form.username} onChange={(e) => setForm((v) => ({ ...v, username: e.target.value.slice(0, 50) }))} maxLength={50} />
                  </div>
                  <div className="field">
                    <label>{t('email')}</label>
                    <input value={form.email} onChange={(e) => setForm((v) => ({ ...v, email: e.target.value.slice(0, 100) }))} maxLength={100} />
                  </div>
                  <div className="field">
                    <label>{t('balance')}</label>
                    <input type="number" step="0.01" value={form.balance} onChange={(e) => setForm((v) => ({ ...v, balance: e.target.value }))} />
                  </div>
                  <div className="field">
                    <label>{t('adminLevel')}</label>
                    <input type="number" min="0" max="10" value={form.admin_level} onChange={(e) => setForm((v) => ({ ...v, admin_level: e.target.value }))} />
                  </div>
                </div>

                <div className="admin-toggle-grid">
                  <label className="admin-check"><input type="checkbox" checked={!!form.is_admin} onChange={(e) => setForm((v) => ({ ...v, is_admin: e.target.checked }))} /> <span>{t('isAdmin')}</span></label>
                  <label className="admin-check"><input type="checkbox" checked={!!form.can_ban} onChange={(e) => setForm((v) => ({ ...v, can_ban: e.target.checked }))} /> <span>{t('canBan')}</span></label>
                  <label className="admin-check"><input type="checkbox" checked={!!form.is_banned} onChange={(e) => setForm((v) => ({ ...v, is_banned: e.target.checked }))} /> <span>{t('isBanned')}</span></label>
                </div>

                <div className="field">
                  <label>{t('banReason')}</label>
                  <textarea rows={4} value={form.ban_reason} onChange={(e) => setForm((v) => ({ ...v, ban_reason: e.target.value.slice(0, 500) }))} maxLength={500} placeholder={t('banReasonPlaceholder')} />
                </div>

                <button className="bp" type="submit">{t('saveUser')}</button>
              </form>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}

function CabinetTab({ userData, onReload, onGoToStore, onToast, onAsk, t, locale, currency, onCurrencyChange }) {
  const rootRef = useRef(null)
  const { user, licenses, purchases } = userData
  const [section, setSection] = useState('account')
  const [nickVal, setNickVal] = useState(user.username)
  const [po, setPo] = useState('')
  const [pn, setPn] = useState('')
  const [pc2, setPc2] = useState('')
  const [pm, setPm] = useState(null)
  const [avatarSrc, setAvatarSrc] = useState(safeAvatarPath(user.avatar))

  const activeLics = useMemo(() => (licenses || []).filter((l) => isActive(l.expires_at)), [licenses])
  const hasLicense = activeLics.length > 0
  const isAdmin = !!Number(user.is_admin)

  useGSAP(
    () => {
      reveal(rootRef.current, '.cuc, .btn-dl, .cnav, .pcard, .ir, .lr, .hr2, .pf2, .es, .ticket-row, .ticket-msg, .admin-user-row', { stagger: 0.04 })
    },
    {
      scope: rootRef,
      dependencies: [section, hasLicense, licenses?.length || 0, purchases?.length || 0, isAdmin],
      revertOnUpdate: true,
    }
  )

  async function saveNick(e) {
    e.preventDefault()
    const d = await api('update_profile', { nickname: nickVal })
    onToast(d.status === 'success' ? t('savedOk') : d.message || t('error'), d.status === 'success' ? 'ok' : 'err')
    if (d.status === 'success') onReload()
  }

  async function chPass(e) {
    e.preventDefault()
    const d = await api('change_password', { old_password: po, new_password: pn, confirm_password: pc2 })
    setPm({
      text: d.status === 'success' ? t('passwordChanged') : `✗ ${d.message}`,
      type: d.status === 'success' ? 'ok' : 'err',
    })
  }

  async function doLogout() {
    await api('logout')
    onReload()
  }

  function handleCurrencyChange(nextCurrency) {
    onCurrencyChange(nextCurrency)
    onToast(t('currencyChanged'), 'ok')
  }

  function delAcc() {
    onAsk({
      title: t('deleteAccountTitle'),
      text: t('deleteAccountText'),
      confirmText: t('deleteNow'),
      cancelText: t('keepAccount'),
      onConfirm: async () => {
        const d = await api('delete_account')
        if (d.status === 'success') onReload()
        else onToast(d.message || t('error'), 'err')
      },
    })
  }

  function cpKey(el, key) {
    navigator.clipboard.writeText(key).then(() => {
      const prev = el.textContent
      el.textContent = t('copied')
      onToast(t('copiedKey'), 'ok')
      setTimeout(() => { el.textContent = prev }, 1500)
    }).catch(() => onToast(t('connectionError'), 'err'))
  }

  async function onAvatarChange(e) {
    const file = e.target.files?.[0]
    if (!file) return
    if (file.size > 2 * 1024 * 1024) {
      onToast(t('maxAvatar'), 'err')
      e.target.value = ''
      return
    }

    const rd = new FileReader()
    rd.onload = (ev) => setAvatarSrc(ev.target?.result)
    rd.readAsDataURL(file)

    try {
      const fd = new FormData()
      fd.append('avatar', file)
      const r = await fetch('api.php?action=update_avatar', {
        method: 'POST',
        body: fd,
        credentials: 'include',
        headers: csrfTokenValue ? { 'X-CSRF-Token': csrfTokenValue } : undefined,
      })
      const d = syncCsrfToken(await r.json())
      if (d.status === 'success') {
        onToast(t('savedOk'), 'ok')
        onReload()
      } else {
        onToast(d.message || t('error'), 'err')
      }
    } catch {
      onToast(t('connectionError'), 'err')
    } finally {
      e.target.value = ''
    }
  }

  const navItems = [
    {
      id: 'account',
      label: t('account'),
      badge: null,
      icon: (
        <>
          <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2" />
          <circle cx="12" cy="7" r="4" />
        </>
      ),
    },
    {
      id: 'licenses',
      label: t('licenses'),
      badge: activeLics.length > 0 ? activeLics.length : null,
      icon: (
        <>
          <rect x="2" y="3" width="20" height="14" rx="2" />
          <path d="M8 21h8M12 17v4" />
        </>
      ),
    },
    {
      id: 'tickets',
      label: t('tickets'),
      badge: null,
      icon: (
        <>
          <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />
        </>
      ),
    },
    {
      id: 'history',
      label: t('history'),
      badge: null,
      icon: <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />,
    },
    {
      id: 'security',
      label: t('security'),
      badge: null,
      icon: <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />,
    },
    {
      id: 'configs',
      label: t('cfgNav'),
      badge: null,
      icon: (
        <>
          <path d="M18 20V10" /><path d="M12 20V4" /><path d="M6 20v-6" />
        </>
      ),
    },
  ]

  if (isAdmin) {
    navItems.push({
      id: 'admin',
      label: t('adminPanel'),
      badge: 'A',
      icon: (
        <>
          <path d="M12 3l7 4v10l-7 4-7-4V7l7-4z" />
          <path d="M12 8v8M8 12h8" />
        </>
      ),
    })
  }

  return (
    <div ref={rootRef} className="cabl">
      <div className="csb">
        <div className="cuc">
          <form id="avf" onSubmit={(e) => e.preventDefault()}>
            <label className="avw" title="Change avatar">
              <img id="avp" src={avatarSrc} alt="avatar" />
              <div className="avo">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z" />
                  <circle cx="12" cy="13" r="4" />
                </svg>
              </div>
              <input type="file" name="avatar" className="afi" accept="image/*" onChange={onAvatarChange} />
            </label>
          </form>
          <div className="cu">{user.username}</div>
          <div className="ci2">#{padId(user.id)} · {t('member')}</div>
          <div className="cbal">
            {t('balance')}
            <div className="bv2">{formatMoney(user.balance || 0, currency, locale)}</div>
            <div className="bv3">{getCurrencyLabel(currency, t)}</div>
          </div>
        </div>

        {hasLicense && (
          <a href="files/loader/goddev_loader.rar" className="btn-dl" download>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" />
              <polyline points="7 10 12 15 17 10" />
              <line x1="12" y1="15" x2="12" y2="3" />
            </svg>
            {t('downloadLoader')}
          </a>
        )}

        <div className="cnav">
          {navItems.map((item) => (
            <button key={item.id} className={`cnb${section === item.id ? ' active' : ''}`} onClick={() => setSection(item.id)}>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">{item.icon}</svg>
              {item.label}
              {item.badge && <span className="cbadge">{item.badge}</span>}
            </button>
          ))}
        </div>
      </div>

      <div>
        {section === 'account' && (
          <div>
            <div className="pcard">
              <div className="pch"><span className="pct">{t('profileInfo')}</span></div>
              <form onSubmit={saveNick}>
                <div className="ir">
                  <div className="ii">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2" />
                      <circle cx="12" cy="7" r="4" />
                    </svg>
                  </div>
                  <div className="ib">
                    <div className="ik">{t('nickname')}</div>
                    <div className="iv"><input type="text" value={nickVal} onChange={(e) => setNickVal(e.target.value)} /></div>
                  </div>
                  <button type="submit" className="ia">{t('save')}</button>
                </div>
              </form>

              <div className="ir" style={{ opacity: 0.4, pointerEvents: 'none' }}>
                <div className="ii" style={{ fontFamily: 'var(--fm)', fontSize: '9px', color: 'var(--m2)' }}>ID</div>
                <div className="ib"><div className="ik">{t('userId')}</div><div className="iv">#{padId(user.id)}</div></div>
              </div>

              {user.email && (
                <div className="ir" style={{ opacity: 0.4, pointerEvents: 'none' }}>
                  <div className="ii">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                      <polyline points="22,6 12,13 2,6" />
                    </svg>
                  </div>
                  <div className="ib"><div className="ik">{t('email')}</div><div className="iv">{user.email}</div></div>
                </div>
              )}

              <div className="ir" style={{ opacity: 0.4, pointerEvents: 'none' }}>
                <div className="ii">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <rect x="3" y="11" width="18" height="11" rx="2" />
                    <path d="M7 11V7a5 5 0 0110 0v4" />
                  </svg>
                </div>
                <div className="ib"><div className="ik">{t('registered')}</div><div className="iv">{fmtDate(user.created_at, locale)}</div></div>
              </div>

              <div className="ir">
                <div className="ii">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <circle cx="12" cy="12" r="9" />
                    <path d="M3 12h18" />
                    <path d="M12 3a15 15 0 010 18" />
                    <path d="M12 3a15 15 0 000 18" />
                  </svg>
                </div>
                <div className="ib">
                  <div className="ik">{t('currency')}</div>
                  <div className="iv iv-stack">
                    <select className="inline-select" value={currency} onChange={(e) => handleCurrencyChange(e.target.value)}>
                      {CURRENCY_ORDER.map((item) => (
                        <option key={item} value={item}>{getCurrencyLabel(item, t)}</option>
                      ))}
                    </select>
                    <div className="iv-note">{t('currencyHint')}</div>
                  </div>
                </div>
              </div>
            </div>

            <div className="pcard">
              <div className="pch"><span className="pct" style={{ color: 'var(--red)' }}>{t('dangerZone')}</span></div>
              <div className="ir">
                <div className="ii" style={{ borderColor: 'rgba(241,53,53,.2)', background: 'rgba(241,53,53,.06)' }}>
                  <svg viewBox="0 0 24 24" fill="none" stroke="var(--red)" strokeWidth="2">
                    <polyline points="3 6 5 6 21 6" />
                    <path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6" />
                    <path d="M10 11v6M14 11v6" />
                    <path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2" />
                  </svg>
                </div>
                <div className="ib"><div className="ik">{t('deleteAccount')}</div><div className="iv" style={{ fontSize: '12px', color: 'var(--m2)' }}>{t('deleteAccountHint')}</div></div>
                <button className="ia dn" onClick={delAcc}>{t('delete')}</button>
              </div>
            </div>
          </div>
        )}

        {section === 'licenses' && (
          <div className="pcard">
            <div className="pch">
              <span className="pct">{t('licenses')}</span>
              <button className="bp" style={{ padding: '7px 14px', fontSize: '8px' }} onClick={onGoToStore}>{t('buyMore')}</button>
            </div>

            {!licenses || licenses.length === 0 ? (
              <div className="es"><div className="ei">🔑</div>{t('noLicenses')}</div>
            ) : licenses.slice(0, 10).map((l, i) => {
              const active = isActive(l.expires_at)
              return (
                <div className="lr" key={i}>
                  <div className="li">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <rect x="2" y="3" width="20" height="14" rx="2" />
                      <path d="M8 21h8M12 17v4" />
                    </svg>
                  </div>
                  <div className="lb">
                    <div className="ln">{l.product_name || 'GOD DEV'}</div>
                    <div className="le">{t('expires')}: {fmtDate(l.expires_at, locale)}</div>
                  </div>
                  <div className="lk" title={l.license_key} onClick={(e) => cpKey(e.currentTarget, l.license_key)}>
                    {(l.license_key || '').substring(0, 16)}...
                  </div>
                  <span className={`ls2 ${active ? 'on' : 'off'}`}>{active ? t('active') : t('expired')}</span>
                </div>
              )
            })}

            {hasLicense && (
              <div style={{ padding: '14px 20px', borderTop: '1px solid var(--b)' }}>
                <a href="files/loader/goddev_loader.exe" className="btn-dl" download>
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" />
                    <polyline points="7 10 12 15 17 10" />
                    <line x1="12" y1="15" x2="12" y2="3" />
                  </svg>
                  {t('downloadGodDevLoader')}
                </a>
              </div>
            )}
          </div>
        )}

        {section === 'tickets' && <TicketsSection isAdmin={isAdmin} onToast={onToast} t={t} locale={locale} currentUserId={user.id} />}

        {section === 'history' && (
          <div className="pcard">
            <div className="pch"><span className="pct">{t('purchaseHistory')}</span></div>
            {!purchases || purchases.length === 0 ? (
              <div className="es"><div className="ei">🛒</div>{t('noPurchases')}</div>
            ) : purchases.slice(0, 20).map((p, i) => (
              <div className="hr2" key={i}>
                <div className="ha2">{formatMoney(-(Number(p.amount) || 0), currency, locale)}</div>
                <div className="hb2">
                  <div className="ht2">{p.product_name || 'License'}</div>
                  <div className="hd2">{fmtDateTime(p.created_at, locale)}</div>
                </div>
                <span className="hbg ok">{t('done')}</span>
              </div>
            ))}
          </div>
        )}

        {section === 'security' && (
          <div>
            <div className="pcard">
              <div className="pch"><span className="pct">{t('changePassword')}</span></div>
              <form onSubmit={chPass}>
                <div className="pf2">
                  <label>{t('currentPassword')}</label>
                  <input type="password" value={po} onChange={(e) => setPo(e.target.value)} placeholder="••••••••" autoComplete="current-password" />
                  <label>{t('newPassword')}</label>
                  <input type="password" value={pn} onChange={(e) => setPn(e.target.value)} placeholder="••••••••" autoComplete="new-password" />
                  <label>{t('confirm')}</label>
                  <input type="password" value={pc2} onChange={(e) => setPc2(e.target.value)} placeholder="••••••••" autoComplete="new-password" />
                  {pm && <div className={`fmsg ${pm.type}`}>{pm.text}</div>}
                  <button type="submit" className="sb">{t('updatePassword')}</button>
                </div>
              </form>
            </div>

            <div className="pcard">
              <div className="pch"><span className="pct">{t('session')}</span></div>
              <div className="ir">
                <div className="ii">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <rect x="2" y="3" width="20" height="14" rx="2" />
                    <path d="M8 21h8M12 17v4" />
                  </svg>
                </div>
                <div className="ib">
                  <div className="ik">{t('currentSession')}</div>
                  <div className="iv" style={{ fontSize: '12px', color: 'var(--m2)' }}>{t('thisBrowser')} · {new Date().toLocaleDateString(locale)}</div>
                </div>
                <span className="ls2 on">{t('active')}</span>
              </div>
              <div className="ir">
                <div className="ib" />
                <button className="ia dn" onClick={doLogout}>{t('logOut')}</button>
              </div>
            </div>
          </div>
        )}

        {section === 'configs' && <ConfigsSection onToast={onToast} t={t} />}

        {section === 'admin' && isAdmin && <AdminSection onToast={onToast} t={t} />}
      </div>
    </div>
  )
}

function LoadingScreen({ t }) {
  const rootRef = useRef(null)

  useGSAP(
    () => {
      gsap.to('.loading-label', { opacity: 0.35, repeat: -1, yoyo: true, duration: 0.9, ease: 'power1.inOut' })
    },
    { scope: rootRef }
  )

  return (
    <div ref={rootRef} style={{ position: 'fixed', inset: 0, background: 'var(--bg)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 8999 }}>
      <div className="loading-label" style={{ fontFamily: 'var(--fm)', fontSize: '10px', color: 'var(--m2)', textTransform: 'uppercase', letterSpacing: '.2em' }}>
        {t('loading')}
      </div>
    </div>
  )
}

export default function Home() {
  const shellRef = useRef(null)
  const [userData, setUserData] = useState(null)
  const [activeTab, setActiveTab] = useState('main')
  const [botPassed, setBotPassed] = useState(false)
  const [lang, setLang] = useState('ru')
  const [currency, setCurrency] = useState('usd')
  const [extDial, setExtDial] = useState({ open: false, text: '', pBuy: null })
  const [toasts, setToasts] = useState([])
  const [confirmDial, setConfirmDial] = useState({ open: false, title: '', text: '', confirmText: '', cancelText: '', onConfirm: null })
  const [resultDial, setResultDial] = useState({ open: false, title: '', text: '', licenseKey: '' })
  const toastTimersRef = useRef([])
  const locale = LOCALES[lang] || LOCALES.ru
  const t = useCallback((key) => {
    const CFG_FALLBACKS: Record<string, string> = {
      cfgNav: 'Configs',
      cfgSaveNew: 'Save Config',
      cfgName: 'Config name',
      cfgNamePlaceholder: 'my_config',
      cfgNameRequired: 'Enter a config name',
      cfgSlot: 'Slot',
      cfgSlotLabel: 'Slot',
      cfgSaveBtn: 'Save to Cloud',
      cfgSaving: 'Saving…',
      cfgSaved: 'Config saved',
      cfgCloudList: 'Cloud Configs',
      cfgRefresh: 'Refresh',
      cfgEmpty: 'No configs yet',
      cfgLoad: 'Load',
      cfgLoaded: 'Config loaded',
      cfgDelete: 'Delete',
      cfgDeleted: 'Config deleted',
      cfgActive: 'Active',
    }
    return TRANSLATIONS[lang]?.[key] || TRANSLATIONS.ru[key] || CFG_FALLBACKS[key] || key
  }, [lang])

  useGSAP(
    () => {
      reveal(shellRef.current, 'header .logo, header .gh-links, header nav, header .n-sup, footer, .mob-nav', {
        y: 16,
        duration: 0.55,
        stagger: 0.04,
      })
    },
    { scope: shellRef }
  )

  useGSAP(
    () => {
      const active = shellRef.current?.querySelector('.tab.active')
      if (!active) return
      gsap.fromTo(
        active,
        { autoAlpha: 0, y: 10 },
        { autoAlpha: 1, y: 0, duration: 0.32, ease: 'power2.out', clearProps: CLEAR }
      )
    },
    {
      scope: shellRef,
      dependencies: [activeTab, userData === null, !!(userData && userData.user)],
      revertOnUpdate: true,
    }
  )

  useEffect(() => {
    const isMobile = /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent)
    if (isMobile) setBotPassed(true)

    const savedLang = window.localStorage.getItem('goddev-lang')
    const savedCurrency = window.localStorage.getItem('goddev-currency')
    if (savedLang && TRANSLATIONS[savedLang]) setLang(savedLang)
    if (savedCurrency && CURRENCY_META[savedCurrency]) setCurrency(savedCurrency)
  }, [])

  useEffect(() => {
    window.localStorage.setItem('goddev-lang', lang)
    document.documentElement.lang = lang
  }, [lang])

  useEffect(() => {
    window.localStorage.setItem('goddev-currency', currency)
  }, [currency])

  useEffect(() => () => {
    toastTimersRef.current.forEach((id) => clearTimeout(id))
  }, [])

  const removeToast = useCallback((id) => {
    setToasts((list) => list.filter((item) => item.id !== id))
  }, [])

  const pushToast = useCallback((text, type = 'ok', title = '') => {
    const id = `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`
    setToasts((list) => [...list, { id, text, type, title }])
    const timer = window.setTimeout(() => {
      setToasts((list) => list.filter((item) => item.id !== id))
    }, 4200)
    toastTimersRef.current.push(timer)
  }, [])

  const askConfirm = useCallback((cfg) => {
    setConfirmDial({
      open: true,
      title: cfg.title,
      text: cfg.text,
      confirmText: cfg.confirmText || t('confirm'),
      cancelText: cfg.cancelText || t('cancel'),
      onConfirm: cfg.onConfirm || null,
    })
  }, [t])

  const closeConfirm = useCallback(() => {
    setConfirmDial((prev) => ({ ...prev, open: false }))
  }, [])

  const runConfirm = useCallback(async () => {
    const fn = confirmDial.onConfirm
    closeConfirm()
    if (!fn) return
    try {
      await fn()
    } catch {
      pushToast(t('connectionError'), 'err')
    }
  }, [closeConfirm, confirmDial.onConfirm, pushToast, t])

  const showResult = useCallback((title, text, licenseKey = '') => {
    setResultDial({ open: true, title, text, licenseKey })
  }, [])

  const copyValue = useCallback((value) => {
    navigator.clipboard.writeText(value).then(() => {
      pushToast(t('copiedKey'), 'ok')
    }).catch(() => pushToast(t('connectionError'), 'err'))
  }, [pushToast, t])

  const loadUser = useCallback(() => {
    setUserData(null)
    api('get_user_info', {}, 'GET')
      .then((d) => setUserData(d.status === 'success' ? d : false))
      .catch(() => setUserData(false))
  }, [])

  useEffect(() => {
    loadUser()
  }, [loadUser])

  useEffect(() => {
    const remoteCurrency = userData?.user?.preferred_currency
    if (!remoteCurrency || !CURRENCY_META[remoteCurrency]) return
    const savedCurrency = window.localStorage.getItem('goddev-currency')
    if (!savedCurrency) setCurrency(remoteCurrency)
  }, [userData])

  function handleBotPass() {
    setBotPassed(true)
  }

  function switchTab(id) {
    setActiveTab(id)
  }

  function handleAuthSuccess() {
    loadUser()
    setActiveTab('main')
  }

  async function handleBuy(price, type, name) {
    try {
      const d = await api('buy', { price, product_type: type, product_name: name })
      if (d.status === 'has_active') {
        setExtDial({
          open: true,
          text: fill(t('activeSubPrompt'), { name: d.product_name }),
          pBuy: { price, type, name },
        })
      } else if (d.status === 'success') {
        showResult(t('purchaseSuccessTitle'), t('purchaseSuccessText'), d.license_key || '')
        loadUser()
      } else {
        pushToast(d.message || t('error'), 'err')
      }
    } catch {
      pushToast(t('connectionError'), 'err')
    }
  }

  async function doExtend(isNew) {
    const pb = extDial.pBuy
    setExtDial({ open: false, text: '', pBuy: null })
    if (!pb) return

    try {
      const d = await api('buy_extend', {
        price: pb.price,
        product_type: pb.type,
        product_name: pb.name,
        extend: isNew ? 'new' : 'extend',
      })

      if (d.status === 'success') {
        if (isNew) {
          showResult(t('newLicenseTitle'), t('newLicenseText'), d.license_key || '')
        } else {
          showResult(t('extendedTitle'), t('extendedText'))
        }
        loadUser()
      } else {
        pushToast(d.message || t('error'), 'err')
      }
    } catch {
      pushToast(t('connectionError'), 'err')
    }
  }

  const isLoggedIn = !!(userData && userData.user)
  const isLoading = userData === null

  return (
    <>

      {!botPassed && <BotCheck onPass={handleBotPass} />}
      {isLoading && botPassed && <LoadingScreen t={t} />}

      <ExtendDialog
        open={extDial.open}
        subText={extDial.text}
        onExtend={() => doExtend(false)}
        onNew={() => doExtend(true)}
        onClose={() => setExtDial({ open: false, text: '', pBuy: null })}
        t={t}
      />

      <ToastStack items={toasts} onClose={removeToast} t={t} />

      <ConfirmDialog
        open={confirmDial.open}
        title={confirmDial.title}
        text={confirmDial.text}
        confirmText={confirmDial.confirmText}
        cancelText={confirmDial.cancelText}
        onConfirm={runConfirm}
        onClose={closeConfirm}
      />

      <ResultDialog
        open={resultDial.open}
        title={resultDial.title}
        text={resultDial.text}
        licenseKey={resultDial.licenseKey}
        onCopy={copyValue}
        onClose={() => setResultDial({ open: false, title: '', text: '', licenseKey: '' })}
        t={t}
      />

      <div ref={shellRef} style={{ display: 'flex', flexDirection: 'column', height: '100vh', overflow: 'hidden' }}>
        <header>
          <div className="logo">
            <span className="god">GOD</span> DEV
          </div>

          <div className="hdr-r">
            <div className="lang-switch">
              <select value={lang} onChange={(e) => setLang(e.target.value)} aria-label="Language">
                {LANG_OPTIONS.map((item) => (
                  <option key={item.code} value={item.code}>{item.label}</option>
                ))}
              </select>
            </div>
            <div className="gh-links">
              {GITHUB_LINKS.map(([href, name]) => (
                <a key={name} href={href} target="_blank" rel="noreferrer" className="ghl">
                  <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 0C5.374 0 0 5.373 0 12c0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23A11.509 11.509 0 0112 5.803c1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576C20.566 21.797 24 17.3 24 12c0-6.627-5.373-12-12-12z" />
                  </svg>
                  {name}
                </a>
              ))}
            </div>

            <nav style={{ display: 'flex', gap: '3px' }}>
              <button className={`nb${activeTab === 'main' ? ' active' : ''}`} onClick={() => switchTab('main')}>{t('navMain')}</button>
              <button className={`nb${activeTab === 'market' ? ' active' : ''}`} onClick={() => switchTab('market')}>{t('navStore')}</button>
              {!isLoading && (
                isLoggedIn
                  ? <button className={`nb${activeTab === 'profile' ? ' active' : ''}`} onClick={() => switchTab('profile')}>{t('navCabinet')}</button>
                  : <button className={`nb${activeTab === 'auth' ? ' active' : ''}`} onClick={() => switchTab('auth')}>{t('navJoin')}</button>
              )}
            </nav>

            <a href="https://t.me/sakurov1337" target="_blank" rel="noreferrer" className="n-sup">
              <svg viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12L8.32 13.617l-2.96-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.828.942z" />
              </svg>
              {t('support')}
            </a>
          </div>
        </header>

        <div className="scroll-area">
          <div className={`tab${activeTab === 'main' ? ' active' : ''} sh`}>
            <MainTab onGoToStore={() => switchTab('market')} t={t} />
          </div>

          <div className={`tab${activeTab === 'market' ? ' active' : ''} sh`}>
            <StoreTab onBuy={handleBuy} t={t} locale={locale} currency={currency} onCurrencyChange={setCurrency} />
          </div>

          {!isLoading && !isLoggedIn && (
            <div className={`tab${activeTab === 'auth' ? ' active' : ''} sh`}>
              <AuthTab onSuccess={handleAuthSuccess} t={t} />
            </div>
          )}

          {!isLoading && isLoggedIn && (
            <div className={`tab${activeTab === 'profile' ? ' active' : ''} sh`}>
              <CabinetTab
                userData={userData}
                onReload={() => { loadUser(); setActiveTab('main') }}
                onGoToStore={() => switchTab('market')}
                onToast={pushToast}
                onAsk={askConfirm}
                t={t}
                locale={locale}
                currency={currency}
                onCurrencyChange={setCurrency}
              />
            </div>
          )}

          <div className="mob-spacer" />
        </div>

        <footer>
          <div className="fc">{t('footer')}</div>
          <span style={{ fontFamily: 'var(--fm)', fontSize: '8px', color: 'var(--m)', textTransform: 'uppercase', letterSpacing: '.14em' }}>
            hauntz · ostuch · kyba
          </span>
        </footer>
      </div>

      <nav className="mob-nav">
        <button className={`mob-nb${activeTab === 'main' ? ' active' : ''}`} onClick={() => switchTab('main')}>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
            <polyline points="9 22 9 12 15 12 15 22" />
          </svg>
          {t('navMain')}
        </button>

        <button className={`mob-nb${activeTab === 'market' ? ' active' : ''}`} onClick={() => switchTab('market')}>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <circle cx="9" cy="21" r="1" />
            <circle cx="20" cy="21" r="1" />
            <path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6" />
          </svg>
          {t('navStore')}
        </button>

        {!isLoading && (
          isLoggedIn ? (
            <button className={`mob-nb${activeTab === 'profile' ? ' active' : ''}`} onClick={() => switchTab('profile')}>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2" />
                <circle cx="12" cy="7" r="4" />
              </svg>
              {t('navCabinet')}
            </button>
          ) : (
            <button className={`mob-nb${activeTab === 'auth' ? ' active' : ''}`} onClick={() => switchTab('auth')}>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4" />
                <polyline points="10 17 15 12 10 7" />
                <line x1="15" y1="12" x2="3" y2="12" />
              </svg>
              {t('navJoin')}
            </button>
          )
        )}

        <a href="https://t.me/sakurov1337" target="_blank" rel="noreferrer" className="mob-nb">
          <svg viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12L8.32 13.617l-2.96-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.828.942z" />
          </svg>
          {t('support')}
        </a>
      </nav>
    </>
  )
}