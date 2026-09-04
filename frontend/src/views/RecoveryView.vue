<script setup>
import { computed, ref } from "vue";
import { useRoute } from "vue-router";
import api from "../api";
const route = useRoute(),
  reset = computed(() => route.name === "reset"),
  done = ref(false),
  error = ref(""),
  form = ref({
    email: route.query.email || "",
    token: route.query.token || "",
    password: "",
    password_confirmation: "",
  });
async function submit() {
  error.value = "";
  try {
    await api.post(
      reset.value ? "/auth/reset-password" : "/auth/forgot-password",
      form.value,
    );
    done.value = true;
  } catch (e) {
    error.value =
      e.response?.data?.message || "No se pudo completar la solicitud.";
  }
}
</script>
<template>
  <main class="auth">
    <section class="story">
      <div class="brand"><b class="mark">I</b><span>InmoGest</span></div>
      <div>
        <p class="eyebrow">Acceso seguro</p>
        <h1>Recupera el control de tu cartera.</h1>
      </div>
    </section>
    <section class="auth-side">
      <form class="form" @submit.prevent="submit">
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
          ><p v-if="error" class="error">{{ error }}</p>
          <label
            >Email<input v-model="form.email" type="email" required /></label
          ><label v-if="reset"
            >Nueva contraseña<input
              v-model="form.password"
              type="password"
              required /></label
          ><label v-if="reset"
            >Confirma la contraseña<input
              v-model="form.password_confirmation"
              type="password"
              required /></label
          ><button class="button primary full">
            {{ reset ? "Guardar contraseña" : "Enviar enlace" }}
          </button></template
        >
        <p class="switch">
          <RouterLink to="/login">Volver al acceso</RouterLink>
        </p>
      </form>
    </section>
  </main>
</template>
