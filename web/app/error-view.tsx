'use client'

import { useEffect, useState } from 'react'
import { TRANSLATIONS } from './i18n'

export default function ErrorView({ code }: { code?: string }) {
  const [lang, setLang] = useState('ru')

  useEffect(() => {
    const saved = window.localStorage.getItem('goddev-lang') || 'ru'
    if (TRANSLATIONS[saved]) setLang(saved)
  }, [])

  const t = (key: string) => TRANSLATIONS[lang]?.[key] || TRANSLATIONS.ru[key] || key

  return (
    <div
      style={{
        background: '#060606',
        color: '#e8e8e8',
        minHeight: '100vh',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        fontFamily: 'Inter, system-ui, sans-serif',
      }}
    >
      <div style={{ textAlign: 'center', padding: '24px' }}>
        <h1 style={{ fontSize: '4rem', color: '#c8f135' }}>{code || t('errorPageTitle')}</h1>
        <p>{code ? t('notFoundText') : t('tryLater')}</p>
      </div>
    </div>
  )
}
