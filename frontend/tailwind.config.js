/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{vue,js,ts,jsx,tsx}'],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        // ── Marca IntelbrasTech ──────────────────────────
        brand: {
          50:  '#eef2ff',
          100: '#e0e7ff',
          200: '#c7d2fe',
          300: '#a5b4fc',
          400: '#818cf8',
          500: '#6366f1',   // principal
          600: '#4f46e5',
          700: '#4338ca',
          800: '#3730a3',
          900: '#312e81',
          950: '#1e1b4b',
        },
        // ── Dark Mode Surface ────────────────────────────
        dark: {
          900: '#0a0a0f',   // fundo mais escuro
          850: '#0f0f18',   // fundo padrão
          800: '#14141f',   // sidebar
          750: '#1a1a2e',   // cards
          700: '#1e1e35',   // inputs
          600: '#252542',   // borders
          500: '#2e2e55',   // hover states
          400: '#3d3d6b',   // subtle
        },
        // ── Status Colors ────────────────────────────────
        success: { light: '#86efac', DEFAULT: '#22c55e', dark: '#16a34a' },
        warning: { light: '#fde68a', DEFAULT: '#f59e0b', dark: '#d97706' },
        danger:  { light: '#fca5a5', DEFAULT: '#ef4444', dark: '#dc2626' },
        info:    { light: '#93c5fd', DEFAULT: '#3b82f6', dark: '#2563eb' },
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif'],
        mono: ['JetBrains Mono', 'Fira Code', 'monospace'],
      },
      fontSize: {
        '2xs': ['0.65rem', { lineHeight: '0.9rem' }],
      },
      boxShadow: {
        'card-dark': '0 1px 3px rgba(0,0,0,0.4), 0 1px 2px rgba(0,0,0,0.6)',
        'glow':      '0 0 20px rgba(99,102,241,0.3)',
        'glow-sm':   '0 0 10px rgba(99,102,241,0.2)',
      },
      borderRadius: {
        'xl2': '1rem',
        'xl3': '1.5rem',
      },
      animation: {
        'fade-in':    'fadeIn 0.2s ease-in-out',
        'slide-up':   'slideUp 0.25s ease-out',
        'slide-down': 'slideDown 0.25s ease-out',
        'pulse-slow': 'pulse 3s cubic-bezier(0.4,0,0.6,1) infinite',
      },
      keyframes: {
        fadeIn:    { from: { opacity: 0 }, to: { opacity: 1 } },
        slideUp:   { from: { transform: 'translateY(8px)', opacity: 0 }, to: { transform: 'translateY(0)', opacity: 1 } },
        slideDown: { from: { transform: 'translateY(-8px)', opacity: 0 }, to: { transform: 'translateY(0)', opacity: 1 } },
      },
    },
  },
  plugins: [
    require('@tailwindcss/forms')({
      strategy: 'class',
    }),
  ],
}
