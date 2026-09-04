<script setup>
import {
  LayoutDashboard,
  Building2,
  KeyRound,
  WalletCards,
  Wrench,
  CalendarDays,
  Files,
  Plus,
  LogOut,
  Settings,
  UsersRound,
  BarChart3,
} from "@lucide/vue";
import { useRouter } from "vue-router";
import { useSession } from "../session";
const s = useSession(),
  router = useRouter(),
  nav = [
    ["Resumen", "/dashboard", LayoutDashboard],
    ["Propiedades", "/properties", Building2],
    ["Alquileres", "/leases", KeyRound],
    ["Personas", "/contacts", UsersRound],
    ["Finanzas", "/finance", WalletCards],
    ["Informes", "/reports", BarChart3],
    ["Incidencias", "/issues", Wrench],
    ["Calendario", "/calendar", CalendarDays],
    ["Documentos", "/documents", Files],
  ];
async function logout() {
  await s.logout();
  router.push("/login");
}
</script>
<template>
  <div class="shell">
    <aside class="sidebar">
      <RouterLink class="brand" to="/dashboard"
        ><b class="mark">I</b><span>InmoGest</span></RouterLink
      >
      <div class="portfolio">
        <small>Cartera</small><strong>{{ s.portfolio?.name }}</strong>
      </div>
      <nav>
        <RouterLink v-for="[label, to, icon] in nav" :key="to" :to="to"
          ><component :is="icon" :size="18" /><span>{{
            label
          }}</span></RouterLink
        >
      </nav>
      <div class="user">
        <strong>{{ s.user?.name }}</strong
        ><small>{{ s.user?.email }}</small
        ><span class="user-actions">
          <RouterLink to="/settings" title="Configuración"
            ><Settings :size="17"
          /></RouterLink>
          <button title="Cerrar sesión" @click="logout">
            <LogOut :size="17" />
          </button>
        </span>
      </div>
    </aside>
    <section class="workspace">
      <header class="topbar">
        <RouterLink class="button primary" to="/properties?new=1"
          ><Plus :size="17" />Añadir</RouterLink
        >
      </header>
      <RouterView />
    </section>
  </div>
</template>
