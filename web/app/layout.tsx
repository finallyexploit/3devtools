import type { Metadata, Viewport } from 'next'
import '../styles/globals.css'
import '../styles/theme.css'

export const metadata: Metadata = {
  title: 'Soulware — Premium Software',
  description: 'Soulware premium software platform',
}

export const viewport: Viewport = {
  width: 'device-width',
  initialScale: 1,
  maximumScale: 5,
  userScalable: true,
  themeColor: '#040406',
}

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode
}>) {
  return (
    <html lang="ru">
      <head>
        <meta charSet="UTF-8" />
        <meta httpEquiv="Content-Security-Policy" content="default-src 'self'; base-uri 'self'; form-action 'self'; object-src 'none'; frame-ancestors 'none'; img-src 'self' data: blob: https:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; connect-src 'self'; font-src 'self' data: https:; media-src 'self' data:; upgrade-insecure-requests" />
      </head>
      <body>
        <a className="skip-link" href="#main-content">Skip to content</a>
        <main id="main-content">{children}</main>
      </body>
    </html>
  )
}
