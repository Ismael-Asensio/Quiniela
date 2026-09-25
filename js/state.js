/* ═══════════════════════════════════════════════════════════════
   QUINIELA MUNDIALISTA 2026 — Client-Side Logic
   V2: Apuestas con Dinero Real + Membresías + Dashboards
   ═══════════════════════════════════════════════════════════════ */

// ─── State ───
let allPartidos = [];
let dashboardData = null;
let rankingData = null;
let currentSection = 'predicciones';
let horaServidor = null;
let selectedRankingTabla = 0;
let miembroDashData = null;
let tesoreroDashData = null;
let championTeams = [];

