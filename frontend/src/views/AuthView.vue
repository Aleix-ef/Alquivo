<script setup>
import { computed, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useSession } from "../session";
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
  busy.value = true;
  error.value = "";
  try {
    isRegister.value ? await s.register(form.value) : await s.login(form.value);
    router.push("/dashboard");
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
      <div class="brand"><b class="mark">I</b><span>InmoGest</span></div>
      <div>
        <p class="eyebrow">Tu patrimonio, con claridad</p>
        <h1>Deja atrás las hojas de cálculo.</h1>
        <p>
          Valor, rentabilidad y alquileres. Todo lo importante de tu cartera en
          un solo lugar.
        </p>
      </div>
      <small>Controla hoy. Decide mejor mañana.</small>
    </section>
    <section class="auth-side">
      <form class="form" @submit.prevent="submit">
        <p class="eyebrow">
          {{ isRegister ? "Empieza gratis" : "Bienvenido de nuevo" }}
        </p>
        <h2>{{ isRegister ? "Crea tu cartera" : "Accede a InmoGest" }}</h2>
        <p>
          {{
            isRegister
              ? "Tu primer inmueble estará listo en menos de dos minutos."
              : "Continúa donde lo dejaste."
          }}
        </p>
        <p v-if="error" class="error">{{ error }}</p>
        <label v-if="isRegister"
          >Nombre<input v-model="form.name" required /></label
        ><label v-if="isRegister"
          >Nombre de la cartera<input
            v-model="form.portfolio_name"
            required /></label
        ><label>Email<input v-model="form.email" type="email" required /></label
        ><label
          >Contraseña<input
            v-model="form.password"
            type="password"
            required /></label
        ><RouterLink v-if="!isRegister" class="forgot" to="/forgot-password"
          >He olvidado mi contraseña</RouterLink
        ><label v-if="isRegister"
          >Confirma la contraseña<input
            v-model="form.password_confirmation"
            type="password"
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
          <RouterLink :to="isRegister ? '/login' : '/register'">{{
            isRegister ? "Entrar" : "Crear cuenta"
          }}</RouterLink>
        </p>
      </form>
    </section>
  </main>
</template>
