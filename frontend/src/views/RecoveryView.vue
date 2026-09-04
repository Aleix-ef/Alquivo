<script setup>
import { computed, ref } from "vue";
import { useRoute } from "vue-router";
import api, { csrf } from "../api";
import BrandLogo from "../components/BrandLogo.vue";
import ThemeToggle from "../components/ThemeToggle.vue";
const route = useRoute(),
  reset = computed(() => route.name === "reset"),
  done = ref(false),
  busy = ref(false),
  error = ref(""),
  form = ref({
    email: route.query.email || "",
    token: route.query.token || "",
    password: "",
    password_confirmation: "",
  });
async function submit() {
  if (busy.value) return;
  busy.value = true;
  error.value = "";
  try {
    await csrf();
    await api.post(
      reset.value ? "/auth/reset-password" : "/auth/forgot-password",
      form.value,
    );
    done.value = true;
  } catch (e) {
    error.value =
      e.response?.data?.message || "No se pudo completar la solicitud.";
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
        <p class="eyebrow">Acceso seguro</p>
        <h1>Recupera el control de tu cartera.</h1>
      </div>
    </section>
    <section class="auth-side">
      <div class="auth-tools"><ThemeToggle /></div>
      <form class="form" @submit.prevent="submit">
        <div class="auth-mobile-brand"><BrandLogo /></div>
        <p class="eyebrow">Cuenta</p>
        <h2>{{ reset ? "Nueva contraseña" : "Recuperar contraseña" }}</h2>
        <p v-if="done" class="success">
          {{
            reset
              ? "Contraseña actualizada. Ya puedes entrar."
              : "Si existe la cuenta, recibirás un enlace por email."
          }}
        </p>
        <template v-else
          ><p v-if="error" class="error" role="alert">{{ error }}</p>
          <label
            >Email<input
              v-model="form.email"
              type="email"
              autocomplete="email"
              required /></label
          ><label v-if="reset"
            >Nueva contraseña<input
              v-model="form.password"
              type="password"
              autocomplete="new-password"
              minlength="8"
              required /></label
          ><label v-if="reset"
            >Confirma la contraseña<input
              v-model="form.password_confirmation"
              type="password"
              autocomplete="new-password"
              required /></label
          ><button class="button primary full" :disabled="busy">
            {{
              busy
                ? "Un momento…"
                : reset
                  ? "Guardar contraseña"
                  : "Enviar enlace"
            }}
          </button></template
        >
        <p class="switch">
          <RouterLink to="/login">Volver al acceso</RouterLink>
        </p>
      </form>
    </section>
  </main>
</template>
