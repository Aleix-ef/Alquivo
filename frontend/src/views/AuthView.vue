<script setup>
import { computed, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import { useSession } from "../session";
import BrandLogo from "../components/BrandLogo.vue";
import PrivacyNotice from "../components/PrivacyNotice.vue";
import { legalVersion } from "../content/legal.js";
import { safeReturnPath } from "../authNavigation";
import api from "../api";
const { t } = useI18n({ useScope: "global" });
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
    portfolio_name: t("nav.portfolio"),
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
      t("auth.accessError");
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
    error.value = e.response?.data?.message || t("auth.codeError");
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
        <p class="eyebrow">{{ $t("auth.storyEyebrow") }}</p>
        <h1>{{ $t("auth.storyTitle") }}</h1>
        <p>
          {{ $t("auth.storyBody") }}
        </p>
      </div>
      <small>{{ $t("auth.storyFoot") }}</small>
    </section>
    <section class="auth-side">
      <form class="form" @submit.prevent="submit">
        <RouterLink
          class="auth-mobile-brand"
          to="/"
          :aria-label="$t('common.backToHome')"
          ><BrandLogo
        /></RouterLink>
        <p class="eyebrow">
          {{ isRegister ? $t("common.startFree") : $t("auth.welcome") }}
        </p>
        <h2>
          {{ isRegister ? $t("auth.createPortfolio") : $t("auth.access") }}
        </h2>
        <p>
          {{ isRegister ? $t("auth.firstProperty") : $t("auth.continue") }}
        </p>
        <p v-if="isRegister">
          {{ $t("auth.betaIntro") }}
        </p>
        <p v-if="route.query.verified === '1'" class="success">
          {{ $t("auth.verified") }}
        </p>
        <p v-if="error" class="error" role="alert">{{ error }}</p>
        <template v-if="twoFactor">
          <h3>{{ $t("auth.verifyYou") }}</h3>
          <p v-if="s.twoFactorEmailUnavailable" class="error" role="alert">
            {{ $t("auth.emailUnavailable") }}
          </p>
          <p>
            <template v-if="challengeMethod === 'email'">
              {{ $t("auth.emailCodePrefix") }}
              <strong>{{
                s.twoFactorEmailHint || $t("auth.verifiedEmail")
              }}</strong>
              {{ $t("auth.emailCodeExpiry") }}
            </template>
            <template v-else-if="challengeMethod === 'recovery'">
              {{ $t("auth.recoveryHint") }}
            </template>
            <template v-else>
              {{ $t("auth.authenticatorHint") }}
            </template>
          </p>
          <label>
            {{
              challengeMethod === "recovery"
                ? $t("auth.recoveryCode")
                : $t("auth.accessCode")
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
            {{ $t("auth.rememberDevice") }}
          </label>
          <p class="muted">{{ $t("auth.sharedDevice") }}</p>
          <button class="button primary full" :disabled="busy">
            {{ busy ? $t("auth.verifying") : $t("auth.verifyAndEnter") }}
          </button>
          <button
            v-if="challengeMethod === 'email'"
            class="button secondary full"
            type="button"
            :disabled="busy"
            @click="resendCode"
          >
            {{ busy ? $t("nav.sending") : $t("auth.sendAnother") }}
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
            {{ $t("auth.useRecovery") }}
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
            {{ $t("auth.backToCode") }}
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
            {{ $t("auth.backToCredentials") }}
          </button>
        </template>
        <template v-else>
          <label v-if="isRegister"
            >{{ $t("common.name")
            }}<input
              v-model="form.name"
              autocomplete="name"
              maxlength="100"
              required /></label
          ><label v-if="isRegister"
            >{{ $t("auth.portfolioName")
            }}<input v-model="form.portfolio_name" required /></label
          ><label
            >{{ $t("common.email")
            }}<input
              v-model="form.email"
              type="email"
              autocomplete="email"
              required /></label
          ><label
            >{{ $t("common.password")
            }}<input
              v-model="form.password"
              type="password"
              :autocomplete="isRegister ? 'new-password' : 'current-password'"
              :minlength="isRegister ? 8 : undefined"
              required /></label
          ><RouterLink
            v-if="!isRegister"
            class="forgot"
            to="/forgot-password"
            >{{ $t("auth.forgotPassword") }}</RouterLink
          ><label v-if="isRegister"
            >{{ $t("common.confirmPassword")
            }}<input
              v-model="form.password_confirmation"
              type="password"
              autocomplete="new-password"
              required /></label
          ><PrivacyNotice v-if="isRegister" />
          <p v-if="isRegister && $i18n.locale === 'en'" class="muted">
            {{ $t("legal.spanishOnly") }}
          </p>
          <label v-if="isRegister" class="check-label legal-check"
            ><input
              v-model="form.terms_accepted"
              type="checkbox"
              required
            /><span
              >{{ $t("auth.acceptPrefix") }}
              <RouterLink to="/terms" target="_blank" rel="noopener">{{
                $t("auth.terms")
              }}</RouterLink
              >{{ $t("auth.acceptSuffix") }}</span
            ></label
          ><button class="button primary full" :disabled="busy">
            {{
              busy
                ? $t("common.wait")
                : isRegister
                  ? $t("auth.createMyPortfolio")
                  : $t("common.login")
            }}
          </button>
          <p class="switch">
            {{ isRegister ? $t("auth.haveAccount") : $t("auth.noAccount") }}
            <RouterLink
              :to="{
                path: isRegister ? '/login' : '/register',
                query: route.query.redirect
                  ? { redirect: safeReturnPath(route.query.redirect) }
                  : {},
              }"
              >{{
                isRegister ? $t("common.login") : $t("auth.createAccount")
              }}</RouterLink
            >
          </p>
        </template>
      </form>
    </section>
  </main>
</template>
