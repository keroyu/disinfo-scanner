/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./resources/**/*.blade.php",
    "./resources/**/*.js",
    "./resources/**/*.vue",
  ],
  theme: {
    extend: {
      colors: {
        'tag-pan-green': '#10b981',
        'tag-pan-white': '#3b82f6',
        'tag-pan-red': '#ef4444',
        'tag-anti-communist': '#f97316',
        'tag-china-stance': '#e11d48',
      },
    },
  },
  plugins: [],
}
