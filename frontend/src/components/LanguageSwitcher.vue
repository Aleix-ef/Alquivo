<script setup>
import { computed } from "vue";
import { useI18n } from "vue-i18n";
import { useSession } from "../session.js";
import { setLocale } from "../i18n.js";

const { locale } = useI18n({ useScope: "global" });
const session = useSession();
const isAdmin = computed(() => session.serverConfirmedAdmin);
const options = [
  { code: "es", flag: "🇪🇸", label: "Español" },
  { code: "en", flag: "🇬🇧", label: "English" },
];
</script>

<template>
  <div
    v-if="isAdmin"
    class="language-switcher"
    role="group"
    :aria-label="$t('language.choose')"
  >
    <button
      v-for="option in options"
      :key="option.code"
      type="button"
      :lang="option.code"
      :aria-label="option.label"
      :aria-pressed="locale === option.code"
      :class="{ active: locale === option.code }"
      @click="setLocale(option.code, isAdmin)"
    >
      <span aria-hidden="true">{{ option.flag }}</span>
      <span>{{ option.label }}</span>
    </button>
  </div>
</template>
