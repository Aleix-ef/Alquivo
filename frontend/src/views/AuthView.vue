<script setup>
import { computed, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useSession } from "../session";
import BrandLogo from "../components/BrandLogo.vue";
import ThemeToggle from "../components/ThemeToggle.vue";
import { safeReturnPath } from "../authNavigation";
const route = useRoute(),
  router = useRouter(),
  s = useSession(),
  isRegister = computed(() => route.name === "register"),
  busy = ref(false),
  error = ref(""),
  form = ref({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
    portfolio_name: "Mi patrimonio",
    terms_accepted: false,
  });
async function submit() {
  if (busy.value) return;
  busy.value = true;
  error.value = "";
  try {
    isRegister.value ? await s.register(form.value) : await s.login(form.value);
    await router.replace(safeReturnPath(route.query.redirect));
  } catch (e) {
    error.value =
      e.response?.data?.message || "No hemos podido completar el acceso.";
  } finally {
    busy.value = false;
  }
}
</script>
<template>
  <main class="auth">
    <section class="story">
      <div class="brand"><BrandLogo light /></div>
      <div>
        <p class="eyebrow">Tu patrimonio, con claridad</p>
        <h1>El control de hoy, un mayor mañana.</h1>
        <p>
          Gestiona tus alquileres, entiende tu rentabilidad y haz crecer tu
          patrimonio desde un único lugar.
        </p>
      </div>
      <small>Controla hoy. Decide mejor mañana.</small>
    </section>
    <section class="auth-side">
      <div class="auth-tools"><ThemeToggle /></div>
      <form class="form" @submit.prevent="submit">
        <div class="auth-mobile-brand"><BrandLogo /></div>
        <p class="eyebrow">
          {{ isRegister ? "Empieza gratis" : "Bienvenido de nuevo" }}
        </p>
        <h2>{{ isRegister ? "Crea tu cartera" : "Accede a Nareo" }}</h2>
        <p>
          {{
            isRegister
              ? "Tu primer inmueble estará listo en menos de dos minutos."
              : "Continúa donde lo dejaste."
          }}
        </p>
        <p v-if="error" class="error" role="alert">{{ error }}</p>
        <label v-if="isRegister"
          >Nombre<input
            v-model="form.name"
            autocomplete="name"
            maxlength="100"
            required /></label
        ><label v-if="isRegister"
          >Nombre de la cartera<input
            v-model="form.portfolio_name"
            required /></label
        ><label
          >Email<input
            v-model="form.email"
            type="email"
            autocomplete="email"
            required /></label
        ><label
          >Contraseña<input
            v-model="form.password"
            type="password"
            :autocomplete="isRegister ? 'new-password' : 'current-password'"
            :minlength="isRegister ? 8 : undefined"
            required /></label
        ><RouterLink v-if="!isRegister" class="forgot" to="/forgot-password"
          >He olvidado mi contraseña</RouterLink
        ><label v-if="isRegister"
          >Confirma la contraseña<input
            v-model="form.password_confirmation"
            type="password"
            autocomplete="new-password"
            required /></label
        ><label v-if="isRegister" class="check-label legal-check"
          ><input
            v-model="form.terms_accepted"
            type="checkbox"
            required
          />Acepto las
          <RouterLink to="/terms" target="_blank"
            >condiciones de uso</RouterLink
          >
          y la
          <RouterLink to="/privacy" target="_blank"
            >política de privacidad</RouterLink
          >.</label
        ><button class="button primary full" :disabled="busy">
          {{
            busy ? "Un momento…" : isRegister ? "Crear mi cartera" : "Entrar"
          }}
        </button>
        <p class="switch">
          {{ isRegister ? "¿Ya tienes cuenta?" : "¿Todavía no tienes cuenta?" }}
          <RouterLink
            :to="{
              path: isRegister ? '/login' : '/register',
              query: route.query.redirect
                ? { redirect: safeReturnPath(route.query.redirect) }
                : {},
            }"
            >{{ isRegister ? "Entrar" : "Crear cuenta" }}</RouterLink
          >
        </p>
      </form>
    </section>
  </main>
</template>
