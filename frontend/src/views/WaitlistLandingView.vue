<script setup>
import MarketingView from "./MarketingView.vue";
import { legalOperator } from "../content/legalOperator.js";
import {
  waitlistConsent,
  waitlistPropertyCounts,
  waitlistSuccess,
} from "../waitlist/contract.js";
defineProps({ settings: { type: Object, required: true } });
</script>

<template>
  <a class="waitlist-skip" href="#contenido">Saltar al contenido</a>
  <MarketingView waitlist-mode>
    <template #closing>
      <section
        id="solicitud"
        class="waitlist-section"
        aria-labelledby="waitlist-title"
      >
        <div class="waitlist-intro">
          <p class="marketing-eyebrow">Sé de los primeros en probar Alquivo</p>
          <h2 id="waitlist-title">
            Menos Excel.<br />Más control de tus alquileres.
          </h2>
          <p>
            Deja tu email y te avisaremos cuando puedas entrar en la beta
            gratuita.
          </p>
          <ul>
            <li>Hasta 10 inmuebles durante la beta.</li>
            <li>Gestión de alquileres y ayuda de Alquivo AI.</li>
            <li>Sin tarjeta ni compromiso de compra.</li>
          </ul>
          <p class="waitlist-note">
            Es una versión en pruebas: queremos escuchar qué te ayuda y qué
            debemos mejorar.
          </p>
        </div>
        <div class="waitlist-card">
          <h3>Solicita acceso a la beta</h3>
          <p>
            Solo necesitamos tu email. El nombre y el número de inmuebles son
            opcionales.
          </p>
          <form
            id="waitlist-form"
            class="waitlist-form-area"
            action="/api/waitlist"
            method="post"
            :data-site-key="settings.siteKey"
            :data-consent-version="waitlistConsent.version"
            :data-preview="settings.preview ? 'true' : 'false'"
            aria-describedby="waitlist-form-note"
          >
            <fieldset id="waitlist-fields">
              <legend class="waitlist-sr-only">Tu solicitud de acceso</legend>
              <label class="waitlist-field" for="waitlist-email">
                Email
                <input
                  id="waitlist-email"
                  name="email"
                  type="email"
                  autocomplete="email"
                  inputmode="email"
                  maxlength="254"
                  placeholder="tu@email.com"
                  required
                />
              </label>
              <div class="waitlist-optional-fields">
                <label class="waitlist-field" for="waitlist-name">
                  <span
                    >Nombre
                    <span class="waitlist-optional">(opcional)</span></span
                  >
                  <input
                    id="waitlist-name"
                    name="name"
                    type="text"
                    autocomplete="given-name"
                    maxlength="100"
                    placeholder="Tu nombre"
                  />
                </label>
                <label class="waitlist-field" for="waitlist-properties">
                  <span
                    >Inmuebles
                    <span class="waitlist-optional">(opcional)</span></span
                  >
                  <select id="waitlist-properties" name="property_count">
                    <option value="">Selecciona</option>
                    <option
                      v-for="option in waitlistPropertyCounts"
                      :key="option.value"
                      :value="option.value"
                    >
                      {{ option.label }}
                    </option>
                  </select>
                </label>
              </div>
              <div class="waitlist-honeypot" aria-hidden="true" inert>
                <label for="waitlist-website">No rellenes este campo</label>
                <input
                  id="waitlist-website"
                  name="website"
                  type="text"
                  tabindex="-1"
                  autocomplete="off"
                  maxlength="200"
                />
              </div>
              <label class="waitlist-consent" for="waitlist-consent">
                <input
                  id="waitlist-consent"
                  name="consent"
                  type="checkbox"
                  required
                />
                <span
                  >{{ waitlistConsent.text }}
                  <a href="/privacidad#lista-espera"
                    >Información de privacidad</a
                  >.
                </span>
              </label>
              <div id="waitlist-challenge"></div>
              <p
                id="waitlist-error"
                class="waitlist-error"
                role="alert"
                tabindex="-1"
                hidden
              ></p>
              <button
                id="waitlist-submit"
                class="button button-large"
                type="submit"
                :disabled="settings.preview"
              >
                Solicitar acceso gratis
              </button>
              <p id="waitlist-form-note" class="waitlist-form-note">
                {{
                  settings.preview
                    ? "Vista previa: el envío no está activado."
                    : "Sin tarjeta. Sin cobros. Solo noticias sobre tu acceso."
                }}
              </p>
            </fieldset>
            <noscript>
              <p>
                Activa JavaScript para enviar el formulario con protección
                antispam, o escribe a
                <a :href="`mailto:${legalOperator.email}`">{{
                  legalOperator.email
                }}</a
                >.
              </p>
            </noscript>
          </form>
          <div
            id="waitlist-success"
            class="waitlist-success"
            role="status"
            hidden
          >
            <span class="waitlist-success-icon" aria-hidden="true">✓</span>
            <h3 id="waitlist-success-title" tabindex="-1">
              Solicitud recibida
            </h3>
            <p>{{ waitlistSuccess }}</p>
          </div>
          <p class="waitlist-privacy">
            <strong>Responsable:</strong> {{ legalOperator.name }}. Usaremos los
            datos para gestionar tu solicitud y avisarte de tu acceso a la beta,
            con tu consentimiento. No te añadimos a una lista de publicidad.
            Puedes retirarte escribiendo a
            <a :href="`mailto:${legalOperator.email}`">{{
              legalOperator.email
            }}</a
            >.
            <a href="/privacidad#lista-espera"
              >Más información sobre tus datos</a
            >.
          </p>
        </div>
      </section>
    </template>
    <template #legal>
      <nav class="legal-links" aria-label="Información legal">
        <a href="/privacidad">Privacidad</a
        ><a href="/aviso-legal">Aviso legal</a>
      </nav>
    </template>
  </MarketingView>
  <a
    class="waitlist-support"
    :href="`mailto:${legalOperator.email}`"
    aria-label="Contactar con soporte de Alquivo"
    >¿Te ayudamos?</a
  >
</template>
