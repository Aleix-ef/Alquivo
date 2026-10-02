<script setup>
import { computed, ref } from "vue";
import { useRoute } from "vue-router";
import { useI18n } from "vue-i18n";
import api, { csrf } from "../api";
import BrandLogo from "../components/BrandLogo.vue";
const { t } = useI18n({ useScope: "global" });
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
    error.value = e.response?.data?.message || t("recovery.requestError");
  } finally {
    busy.value = false;
  }
}
</script>
<template>
  <main class="auth">
    <section class="story">
      <RouterLink class="brand" to="/" :aria-label="$t('common.backToHome')"
        ><BrandLogo light
      /></RouterLink>
      <div>
        <p class="eyebrow">{{ $t("recovery.secureAccess") }}</p>
        <h1>{{ $t("recovery.story") }}</h1>
      </div>
    </section>
    <section class="auth-side">
      <form class="form" @submit.prevent="submit">
        <RouterLink
          class="auth-mobile-brand"
          to="/"
          :aria-label="$t('common.backToHome')"
          ><BrandLogo
        /></RouterLink>
        <p class="eyebrow">{{ $t("recovery.account") }}</p>
        <h2>
          {{
            reset ? $t("recovery.newPassword") : $t("recovery.recoverPassword")
          }}
        </h2>
        <p v-if="done" class="success">
          {{
            reset ? $t("recovery.passwordUpdated") : $t("recovery.sentIfExists")
          }}
        </p>
        <template v-else
          ><p v-if="error" class="error" role="alert">{{ error }}</p>
          <label
            >{{ $t("common.email")
            }}<input
              v-model="form.email"
              type="email"
              autocomplete="email"
              required /></label
          ><label v-if="reset"
            >{{ $t("recovery.newPassword")
            }}<input
              v-model="form.password"
              type="password"
              autocomplete="new-password"
              minlength="8"
              required /></label
          ><label v-if="reset"
            >{{ $t("common.confirmPassword")
            }}<input
              v-model="form.password_confirmation"
              type="password"
              autocomplete="new-password"
              required /></label
          ><button class="button primary full" :disabled="busy">
            {{
              busy
                ? $t("common.wait")
                : reset
                  ? $t("recovery.savePassword")
                  : $t("recovery.sendLink")
            }}
          </button></template
        >
        <p class="switch">
          <RouterLink to="/login">{{ $t("recovery.backToLogin") }}</RouterLink>
        </p>
      </form>
    </section>
  </main>
</template>
