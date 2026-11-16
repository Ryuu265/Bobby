/* =====================================================================
   QENGAMESTATION — TAILWIND CONFIG
   Peta design tokens (tokens.css) ke kelas utilitas Tailwind.
   Dimuat SETELAH script Tailwind Play CDN di layout, dan sebelum </head>.
   Semua warna memakai rgb(var(--xxx-rgb) / <alpha-value>) supaya
   modifier transparansi (mis. bg-cyan/10) tetap berfungsi.
   ===================================================================== */
tailwind.config = {
  darkMode: "class",
  theme: {
    container: { center: true, padding: "1rem", screens: { "2xl": "1200px" } },
    extend: {
      colors: {
        // Netral gelap
        ink: {
          900: "rgb(var(--ink-900-rgb) / <alpha-value>)",
          850: "rgb(var(--ink-850-rgb) / <alpha-value>)",
          800: "rgb(var(--ink-800-rgb) / <alpha-value>)",
          700: "rgb(var(--ink-700-rgb) / <alpha-value>)",
          600: "rgb(var(--ink-600-rgb) / <alpha-value>)",
          500: "rgb(var(--ink-500-rgb) / <alpha-value>)",
        },
        // Aksen neon teredam
        cyan: { DEFAULT: "rgb(var(--cyan-rgb) / <alpha-value>)", soft: "rgba(79,209,224,0.12)" },
        violet: { DEFAULT: "rgb(var(--violet-rgb) / <alpha-value>)", soft: "rgba(139,124,246,0.12)" },
        pink: { DEFAULT: "rgb(var(--pink-rgb) / <alpha-value>)", soft: "rgba(232,121,168,0.12)" },
        // Semantik
        success: "rgb(var(--success-rgb) / <alpha-value>)",
        warning: "rgb(var(--warning-rgb) / <alpha-value>)",
        danger: "rgb(var(--danger-rgb) / <alpha-value>)",
        info: "rgb(var(--info-rgb) / <alpha-value>)",
        // Teks
        text: {
          strong: "var(--text-strong)",
          DEFAULT: "rgb(var(--text-primary-rgb) / <alpha-value>)",
          secondary: "rgb(var(--text-secondary-rgb) / <alpha-value>)",
          muted: "var(--text-muted)",
        },
      },
      borderColor: {
        soft: "var(--border-soft)",
        medium: "var(--border-medium)",
        strong: "var(--border-strong)",
      },
      borderRadius: {
        sm: "var(--radius-sm)", // 12px
        md: "var(--radius-md)", // 14px
        lg: "var(--radius-lg)", // 16px
        xl: "var(--radius-xl)", // 20px
      },
      fontFamily: {
        body: ["Poppins", "Inter", "system-ui", "sans-serif"],
        display: ["Rajdhani", "Orbitron", "Poppins", "sans-serif"],
        mono: ["JetBrains Mono", "ui-monospace", "Consolas", "monospace"],
      },
      boxShadow: {
        // Glow teredam, opacity <= 0.25
        glow: "var(--glow-cyan)",
        "glow-violet": "var(--glow-violet)",
        card: "var(--shadow-card)",
        pop: "var(--shadow-pop)",
      },
      transitionDuration: { fast: "200ms", base: "250ms", slow: "300ms" },
      spacing: { 18: "4.5rem", 88: "22rem", 116: "29rem" },
      maxWidth: { page: "1200px", narrow: "780px" },
    },
  },
  corePlugins: { preflight: true },
};
