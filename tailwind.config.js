import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                heading: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // SEAIT Primary Accent - Orange
                seait: {
                    50: '#FFF5F0',
                    100: '#FFE8DB',
                    200: '#FFD3C0',
                    300: '#FFB899',
                    400: '#FF8C5A',
                    500: '#FF6B35',
                    600: '#E5512A',
                    700: '#C44020',
                    800: '#993C1D',
                    900: '#662618',
                    950: '#331009',
                },
                // SEAIT Navy - Dark surfaces (replaces brand)
                navy: {
                    50: '#F2F5F8',
                    100: '#E3E8EF',
                    200: '#C5D1E0',
                    300: '#8B9DB5',
                    400: '#6B84A0',
                    500: '#4A6175',
                    600: '#3D5068',
                    700: '#2D4258',
                    800: '#1E2D3D',
                    850: '#162330',
                    900: '#101920',
                    950: '#080E14',
                },
                // Brand = Navy (semantic neutral/dark for text, surfaces)
                brand: {
                    50: '#F2F5F8',
                    100: '#E3E8EF',
                    200: '#C5D1E0',
                    300: '#8B9DB5',
                    400: '#6B84A0',
                    500: '#4A6175',
                    600: '#3D5068',
                    700: '#2D4258',
                    800: '#1E2D3D',
                    850: '#162330',
                    900: '#101920',
                    950: '#080E14',
                },
                // Accent = SEAIT Orange (warm CTA/highlight)
                accent: {
                    50: '#FFF5F0',
                    100: '#FFE8DB',
                    200: '#FFD3C0',
                    300: '#FFB899',
                    400: '#FF8C5A',
                    500: '#FF6B35',
                    600: '#E5512A',
                    700: '#C44020',
                    800: '#993C1D',
                    900: '#662618',
                    950: '#331009',
                },
                // Semantic colors
                success: {
                    50: '#f0fdf4',
                    100: '#dcfce7',
                    200: '#bbf7d0',
                    300: '#86efac',
                    400: '#4ade80',
                    500: '#22c55e',
                    600: '#16a34a',
                    700: '#15803d',
                    800: '#166534',
                    900: '#14532d',
                },
                warning: {
                    50: '#fffbeb',
                    100: '#fef3c7',
                    200: '#fde68a',
                    300: '#fcd34d',
                    400: '#fbbf24',
                    500: '#f59e0b',
                    600: '#d97706',
                    700: '#b45309',
                    800: '#92400e',
                    900: '#78350f',
                },
                danger: {
                    50: '#fef2f2',
                    100: '#fee2e2',
                    200: '#fecaca',
                    300: '#fca5a5',
                    400: '#f87171',
                    500: '#ef4444',
                    600: '#dc2626',
                    700: '#b91c1c',
                    800: '#991b1b',
                    900: '#7f1d1d',
                },
                info: {
                    50: '#eff6ff',
                    100: '#dbeafe',
                    200: '#bfdbfe',
                    300: '#93c5fd',
                    400: '#60a5fa',
                    500: '#3b82f6',
                    600: '#2563eb',
                    700: '#1d4ed8',
                    800: '#1e40af',
                    900: '#1e3a8a',
                },
            },
            boxShadow: {
                'card': '0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04)',
                'card-hover': '0 4px 12px rgba(0,0,0,0.08)',
                'card-lg': '0 8px 30px rgba(0,0,0,0.08)',
                'modal': '0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1)',
                'dropdown': '0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)',
            },
            borderRadius: {
                'card': '1rem',
                'card-sm': '0.5rem',
                'input': '0.75rem',
                'btn': '0.75rem',
                'badge': '9999px',
            },
            spacing: {
                '18': '4.5rem',
                '88': '22rem',
            },
            keyframes: {
                'fade-in': {
                    '0%': { opacity: '0' },
                    '100%': { opacity: '1' },
                },
                'fade-out': {
                    '0%': { opacity: '1' },
                    '100%': { opacity: '0' },
                },
                'slide-in-from-top': {
                    '0%': { transform: 'translateY(-10px)', opacity: '0' },
                    '100%': { transform: 'translateY(0)', opacity: '1' },
                },
                'slide-in-from-bottom': {
                    '0%': { transform: 'translateY(10px)', opacity: '0' },
                    '100%': { transform: 'translateY(0)', opacity: '1' },
                },
                'slide-up': {
                    '0%': { transform: 'translateY(12px)', opacity: '0' },
                    '100%': { transform: 'translateY(0)', opacity: '1' },
                },
                'slide-in-from-right': {
                    '0%': { transform: 'translateX(10px)', opacity: '0' },
                    '100%': { transform: 'translateX(0)', opacity: '1' },
                },
                'scale-in': {
                    '0%': { transform: 'scale(0.95)', opacity: '0' },
                    '100%': { transform: 'scale(1)', opacity: '1' },
                },
            },
            animation: {
                'fade-in': 'fade-in 200ms ease-out',
                'fade-out': 'fade-out 150ms ease-in',
                'slide-in-from-top': 'slide-in-from-top 200ms ease-out',
                'slide-in-from-bottom': 'slide-in-from-bottom 200ms ease-out',
                'slide-up': 'slide-up 300ms ease-out',
                'slide-in-from-right': 'slide-in-from-right 200ms ease-out',
                'scale-in': 'scale-in 150ms ease-out',
            },
            transitionDuration: {
                '200': '200ms',
                '300': '300ms',
            },
            transitionTimingFunction: {
                'ease-out': 'cubic-bezier(0.16, 1, 0.3, 1)',
                'ease-in-out': 'cubic-bezier(0.4, 0, 0.2, 1)',
            },
        },
    },

    plugins: [forms],
};