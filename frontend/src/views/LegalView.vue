<script setup>
import { computed } from "vue";
import { useRoute } from "vue-router";
import BrandLogo from "../components/BrandLogo.vue";
import LegalLinks from "../components/LegalLinks.vue";
import {
  legalPages,
  legalVersion,
  legalUpdatedAt,
  legalDraft,
  operator,
  pendingValue,
} from "../content/legal.js";
const route = useRoute();
const page = computed(() =>
  legalPages.find((item) => item.name === route.name),
);
function printPage() {
  window.print();
}
</script>

<template>
  <div class="legal-page">
    <header class="legal-header">
      <RouterLink class="brand" to="/" aria-label="Alquivo, volver al inicio"
        ><BrandLogo
      /></RouterLink>
      <div class="marketing-header-actions">
        <RouterLink class="button secondary" to="/login">{{
          $t("common.login")
        }}</RouterLink>
      </div>
    </header>
    <main v-if="page" class="legal-content">
      <p v-if="$i18n.locale === 'en'" class="legal-note" role="note">
        {{ $t("legal.spanishOnly") }}
      </p>
      <p class="eyebrow">
        Información legal · Versión
        <time :datetime="legalUpdatedAt">{{ legalVersion }}</time>
      </p>
      <h1>{{ page.title }}</h1>
      <p class="legal-summary">{{ page.summary }}</p>
      <p v-if="legalDraft" class="legal-note" role="note">
        Borrador previo a la apertura de la beta. Faltan datos del titular,
        proveedores o condiciones de conservación y la revisión final. Los
        campos pendientes están señalados; este texto todavía no es la versión
        definitiva para publicar.
      </p>
      <nav class="legal-index" aria-label="Contenido de esta página">
        <strong>En esta página</strong>
        <ol>
          <li v-for="section in page.sections" :key="section.id">
            <a :href="`#${section.id}`">{{
              section.title.replace(/^\d+\. /, "")
            }}</a>
          </li>
        </ol>
      </nav>
      <article :aria-label="page.title">
        <section
          v-for="section in page.sections"
          :id="section.id"
          :key="section.id"
          class="legal-section"
        >
          <h2>{{ section.title }}</h2>
          <p v-for="paragraph in section.paragraphs" :key="paragraph">
            {{ paragraph }}
          </p>
          <dl v-if="section.identity" class="legal-identity">
            <div>
              <dt>Titular</dt>
              <dd>{{ operator.name || pendingValue }}</dd>
            </div>
            <div>
              <dt>Forma jurídica</dt>
              <dd>{{ operator.legalForm || pendingValue }}</dd>
            </div>
            <div>
              <dt>NIF</dt>
              <dd>{{ operator.taxId || pendingValue }}</dd>
            </div>
            <div>
              <dt>Domicilio de contacto</dt>
              <dd>{{ operator.address || pendingValue }}</dd>
            </div>
            <div v-if="operator.registry">
              <dt>Datos registrales</dt>
              <dd>{{ operator.registry }}</dd>
            </div>
            <div>
              <dt>Correo de contacto</dt>
              <dd>
                <a :href="`mailto:${operator.email}`">{{ operator.email }}</a>
              </dd>
            </div>
          </dl>
          <ul v-if="section.items">
            <li v-for="item in section.items" :key="item">{{ item }}</li>
          </ul>
          <div
            v-if="section.table"
            class="legal-table-wrap"
            tabindex="0"
            role="region"
            :aria-label="section.title"
          >
            <table>
              <caption>
                {{
                  section.title.replace(/^\d+\. /, "")
                }}
              </caption>
              <thead>
                <tr>
                  <th
                    v-for="heading in section.table.headings"
                    :key="heading"
                    scope="col"
                  >
                    {{ heading }}
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in section.table.rows" :key="row[0]">
                  <th scope="row">{{ row[0] }}</th>
                  <td v-for="(cell, index) in row.slice(1)" :key="index">
                    {{ cell }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-if="section.providers" class="legal-providers">
            <section
              v-for="provider in operator.providers"
              :key="provider.service"
              class="legal-provider"
            >
              <h3>{{ provider.service }}</h3>
              <dl>
                <div>
                  <dt>Entidad</dt>
                  <dd>{{ provider.name || pendingValue }}</dd>
                </div>
                <div>
                  <dt>Ubicación del tratamiento</dt>
                  <dd>{{ provider.location || pendingValue }}</dd>
                </div>
                <div>
                  <dt>Transferencias y garantías</dt>
                  <dd>{{ provider.safeguards || pendingValue }}</dd>
                </div>
              </dl>
            </section>
          </div>
          <ul
            v-if="section.links || section.externalLinks"
            class="legal-related"
          >
            <li v-for="link in section.links" :key="link.to">
              <RouterLink :to="link.to">{{ link.label }}</RouterLink>
            </li>
            <li v-for="link in section.externalLinks" :key="link.href">
              <a :href="link.href" target="_blank" rel="noopener noreferrer"
                >{{ link.label }} (sitio externo)</a
              >
            </li>
          </ul>
        </section>
      </article>
      <div class="legal-actions">
        <button type="button" class="button secondary" @click="printPage">
          Imprimir o guardar como PDF</button
        ><RouterLink to="/">Volver al inicio</RouterLink>
      </div>
      <footer class="legal-footer">
        <LegalLinks />
        <p>
          Contacto:
          <a :href="`mailto:${operator.email}`">{{ operator.email }}</a>
        </p>
      </footer>
    </main>
  </div>
</template>
