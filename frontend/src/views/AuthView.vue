<script setup>
import { computed, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useSession } from "../session";
import BrandLogo from "../components/BrandLogo.vue";
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
const twoFactor = ref(false),
  code = ref("");
watch(
  () => route.name,
  () => {
    twoFactor.value = false;
    code.value = "";
    error.value = "";
  },
);
async function submit() {
  if (busy.value) return;
  busy.value = true;
  error.value = "";
  try {
    if (twoFactor.value) {
      await s.verifyTwoFactor(code.value);
      code.value = "";
    } else if (isRegister.value) {
      await s.register(form.value);
    } else if (!(await s.login(form.value))) {
      form.value.password = "";
      twoFactor.value = true;
      return;
    }
    await router.replace(safeReturnPath(route.query.redirect));
  } catch (e) {
    error.value =
      Object.values(e.response?.data?.errors || {}).flat()[0] ||
      e.response?.data?.message ||
      "No hemos podido completar el acceso.";
  } finally {
    busy.value = false;
  }
}
</script>
<template>
  <main class="auth">
    <section class="story">
      <RouterLink
        class="brand"
        to="/"
        aria-label="Volver a la landing de Alquivo"
        ><BrandLogo light
      /></RouterLink>
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
      <form class="form" @submit.prevent="submit">
        <RouterLink
          class="auth-mobile-brand"
          to="/"
          aria-label="Volver a la landing de Alquivo"
          ><BrandLogo
        /></RouterLink>
        <p class="eyebrow">
          {{ isRegister ? "Empieza gratis" : "Bienvenido de nuevo" }}
        </p>
        <h2>{{ isRegister ? "Crea tu cartera" : "Accede a Alquivo" }}</h2>
        <p>
          {{
            isRegister
              ? "Empieza añadiendo tu primer inmueble."
              : "Continúa donde lo dejaste."
          }}
        </p>
        <p v-if="isRegister">
          Estás entrando en la beta de Alquivo. El registro está abierto: puedes
          gestionar inmuebles, alquileres, cobros y documentos, y contarnos qué
          mejorarías.
        </p>
        <p v-if="route.query.verified === '1'" class="success">
          Correo confirmado. Ya puedes entrar.
        </p>
        <p v-if="error" class="error" role="alert">{{ error }}</p>
        <template v-if="twoFactor">
          <h3>Verifica que eres tú</h3>
          <p>
            Introduce el código de tu aplicación de autenticación o uno de tus
            códigos de recuperación. El acceso caduca a los 5 minutos.
          </p>
          <label
            >Código de acceso<input
              v-model.trim="code"
              autocomplete="one-time-code"
              maxlength="40"
              required
          /></label>
          <button class="button primary full" :disabled="busy">
            {{ busy ? "Verificando…" : "Verificar y entrar" }}
          </button>
          <button
            class="button secondary full"
            type="button"
            :disabled="busy"
            @click="
              twoFactor = false;
              code = '';
              error = '';
            "
          >
            Volver al correo y contraseña
          </button>
        </template>
        <template v-else>
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
            {{
              isRegister ? "¿Ya tienes cuenta?" : "¿Todavía no tienes cuenta?"
            }}
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
        </template>
      </form>
    </section>
  </main>
</template>
