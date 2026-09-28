<script setup>
import { computed, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useSession } from "../session";
import BrandLogo from "../components/BrandLogo.vue";
import PrivacyNotice from "../components/PrivacyNotice.vue";
import { legalVersion } from "../content/legal.js";
import { safeReturnPath } from "../authNavigation";
import api from "../api";
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
    terms_version: legalVersion,
  });
const twoFactor = ref(false),
  code = ref(""),
  rememberDevice = ref(false),
  recoveryMode = ref(false),
  resendMessage = ref("");
const challengeMethod = computed(() =>
  recoveryMode.value ? "recovery" : s.twoFactorMethods[0] || "authenticator",
);
watch(
  () => route.name,
  () => {
    twoFactor.value = false;
    code.value = "";
    error.value = "";
    rememberDevice.value = false;
    recoveryMode.value = false;
  },
);
async function submit() {
  if (busy.value) return;
  busy.value = true;
  error.value = "";
  try {
    if (twoFactor.value) {
      const complete = await s.verifyTwoFactor(
        code.value,
        challengeMethod.value,
        rememberDevice.value,
      );
      code.value = "";
      if (!complete) {
        recoveryMode.value = false;
        error.value = "";
        return;
      }
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
async function resendCode() {
  if (busy.value) return;
  busy.value = true;
  error.value = "";
  resendMessage.value = "";
  try {
    resendMessage.value = (
      await api.post("/auth/two-factor/resend")
    ).data.message;
    s.twoFactorEmailUnavailable = false;
  } catch (e) {
    error.value = e.response?.data?.message || "No se pudo enviar el código.";
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
          <p v-if="s.twoFactorEmailUnavailable" class="error" role="alert">
            No hemos podido enviar el código. Prueba el autenticador o un código
            de recuperación.
          </p>
          <p>
            <template v-if="challengeMethod === 'email'">
              Te hemos enviado un código de 6 cifras a
              <strong>{{
                s.twoFactorEmailHint || "tu correo verificado"
              }}</strong
              >. Caduca en 5 minutos.
            </template>
            <template v-else-if="challengeMethod === 'recovery'">
              Introduce uno de tus códigos de recuperación. Solo se puede usar
              una vez.
            </template>
            <template v-else>
              Introduce el código de 6 cifras de tu aplicación de autenticación.
              Si acabas de usar ese código, espera a que cambie.
            </template>
          </p>
          <label>
            {{
              challengeMethod === "recovery"
                ? "Código de recuperación"
                : "Código de acceso"
            }}<input
              v-model.trim="code"
              :inputmode="challengeMethod === 'recovery' ? 'text' : 'numeric'"
              :autocomplete="
                challengeMethod === 'recovery' ? 'off' : 'one-time-code'
              "
              :maxlength="challengeMethod === 'recovery' ? 40 : 6"
              :pattern="challengeMethod === 'recovery' ? undefined : '[0-9]{6}'"
              required
          /></label>
          <label class="check-label remember-device">
            <input v-model="rememberDevice" type="checkbox" />
            Recordar este dispositivo durante 90 días
          </label>
          <p class="muted">No lo actives en un dispositivo compartido.</p>
          <button class="button primary full" :disabled="busy">
            {{ busy ? "Verificando…" : "Verificar y entrar" }}
          </button>
          <button
            v-if="challengeMethod === 'email'"
            class="button secondary full"
            type="button"
            :disabled="busy"
            @click="resendCode"
          >
            {{ busy ? "Enviando…" : "Enviar otro código" }}
          </button>
          <p v-if="resendMessage" class="success" role="status">
            {{ resendMessage }}
          </p>
          <button
            v-if="challengeMethod !== 'recovery'"
            class="button secondary full"
            type="button"
            :disabled="busy"
            @click="
              recoveryMode = true;
              code = '';
              error = '';
            "
          >
            Usar un código de recuperación
          </button>
          <button
            v-else
            class="button secondary full"
            type="button"
            :disabled="busy"
            @click="
              recoveryMode = false;
              code = '';
              error = '';
            "
          >
            Volver al código de verificación
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
          ><PrivacyNotice v-if="isRegister" /><label
            v-if="isRegister"
            class="check-label legal-check"
            ><input
              v-model="form.terms_accepted"
              type="checkbox"
              required
            /><span
              >Acepto las
              <RouterLink to="/terms" target="_blank" rel="noopener"
                >condiciones de uso</RouterLink
              >, incluido el acuerdo de encargo cuando corresponda. He leído el
              aviso de privacidad anterior.</span
            ></label
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
