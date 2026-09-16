<script setup>
import { ref, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useSession } from "../session";
import api from "../api";
import BrandLogo from "../components/BrandLogo.vue";
const route = useRoute(),
  router = useRouter(),
  session = useSession();
const busy = ref(false),
  error = ref("");
async function verify() {
  if (busy.value) return;
  const { id, hash, expires, signature } = route.query;
  if (
    !/^\d+$/.test(id || "") ||
    !/^[a-f0-9]{40}$/.test(hash || "") ||
    !/^\d+$/.test(expires || "") ||
    !/^[a-f0-9]{64}$/.test(signature || "")
  ) {
    error.value = "El enlace no es válido. Solicita otro desde tu cuenta.";
    return;
  }
  busy.value = true;
  error.value = "";
  try {
    await api.get(`/auth/email/verify/${id}/${hash}`, {
      params: { expires, signature },
    });
    await session.restore();
    await router.replace("/settings?verified=1");
  } catch {
    error.value =
      "No hemos podido confirmar el correo. El enlace puede haber caducado o pertenecer a otra cuenta. Comprueba con qué correo has iniciado sesión o solicita un enlace nuevo.";
  } finally {
    busy.value = false;
  }
}
async function switchAccount() {
  busy.value = true;
  try {
    await session.logout();
    await router.replace({
      name: "login",
      query: { redirect: route.fullPath },
    });
  } catch {
    error.value = "No se pudo cerrar la sesión. Inténtalo de nuevo.";
  } finally {
    busy.value = false;
  }
}
onMounted(verify);
</script>
<template>
  <main class="legal-page">
    <RouterLink class="brand" to="/"><BrandLogo /></RouterLink>
    <article>
      <h1>Confirmar mi correo</h1>
      <p v-if="busy" role="status">Estamos comprobando tu enlace…</p>
      <template v-else-if="error">
        <p class="error" role="alert">{{ error }}</p>
        <p>Sesión actual: {{ session.user?.email }}</p>
        <button class="button secondary" @click="verify">
          Volver a intentar
        </button>
        <button class="button secondary" @click="switchAccount">
          Entrar con otra cuenta
        </button>
        <p>
          <RouterLink to="/settings"
            >Solicitar un enlace nuevo desde mi cuenta</RouterLink
          >
        </p>
      </template>
    </article>
  </main>
</template>
