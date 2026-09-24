<script setup>
import { computed } from "vue";
import { useRoute } from "vue-router";
import BrandLogo from "../components/BrandLogo.vue";
import { guides } from "../content/guides.js";
const route = useRoute();
const guide = computed(() => guides.find((item) => item.path === route.path));
</script>

<template>
  <div class="marketing-page">
    <header class="marketing-header">
      <RouterLink to="/" class="marketing-logo" aria-label="Alquivo, inicio"
        ><BrandLogo
      /></RouterLink>
      <div class="marketing-header-actions">
        <RouterLink to="/login" class="button button-quiet marketing-login"
          >Entrar</RouterLink
        >
        <RouterLink to="/register" class="button">Empieza gratis</RouterLink>
      </div>
    </header>
    <main class="guide-page">
      <nav
        class="guide-breadcrumb"
        aria-label="Ruta de navegación"
        itemscope
        itemtype="https://schema.org/BreadcrumbList"
      >
        <span
          itemprop="itemListElement"
          itemscope
          itemtype="https://schema.org/ListItem"
          ><RouterLink to="/" itemprop="item"
            ><span itemprop="name">Inicio</span></RouterLink
          ><meta itemprop="position" content="1"
        /></span>
        <span aria-hidden="true">/</span>
        <span
          itemprop="itemListElement"
          itemscope
          itemtype="https://schema.org/ListItem"
          ><RouterLink
            to="/guias"
            itemprop="item"
            :aria-current="guide ? undefined : 'page'"
            ><span itemprop="name">Guías</span></RouterLink
          ><meta itemprop="position" content="2"
        /></span>
      </nav>
      <article v-if="guide" itemscope itemtype="https://schema.org/Article">
        <p class="marketing-eyebrow">Guías para propietarios</p>
        <h1 itemprop="headline">{{ guide.title }}</h1>
        <p class="guide-summary" itemprop="description">{{ guide.summary }}</p>
        <p class="guide-byline">
          Por
          <span
            itemprop="author"
            itemscope
            itemtype="https://schema.org/Organization"
            ><span itemprop="name">Alquivo</span></span
          >
          · Guía de organización, no asesoramiento fiscal ni legal.
        </p>
        <nav class="guide-index" aria-label="Contenido de la guía">
          <strong>En esta guía</strong>
          <ol>
            <li v-for="(section, index) in guide.sections" :key="section.title">
              <a :href="`#paso-${index + 1}`">{{ section.title }}</a>
            </li>
          </ol>
        </nav>
        <div itemprop="articleBody">
          <section
            v-for="(section, index) in guide.sections"
            :id="`paso-${index + 1}`"
            :key="section.title"
            class="guide-section"
          >
            <h2>{{ section.title }}</h2>
            <p v-for="paragraph in section.paragraphs" :key="paragraph">
              {{ paragraph }}
            </p>
            <ul v-if="section.checklist">
              <li v-for="item in section.checklist" :key="item">{{ item }}</li>
            </ul>
          </section>
        </div>
      </article>
      <section v-else>
        <p class="marketing-eyebrow">Guías para propietarios</p>
        <h1>Más claridad para gestionar tus alquileres.</h1>
        <p class="guide-summary">
          Ideas prácticas para ordenar contratos, cobros y gastos. Sin convertir
          la gestión de tus inmuebles en un trabajo a tiempo completo.
        </p>
      </section>
      <section class="guide-related" aria-labelledby="related-guides">
        <h2 id="related-guides">
          {{ guide ? "Sigue leyendo" : "Empieza por aquí" }}
        </h2>
        <article
          v-for="item in guides.filter((item) => item.path !== guide?.path)"
          :key="item.path"
          class="guide-card"
        >
          <h3>
            <RouterLink :to="item.path">{{ item.title }}</RouterLink>
          </h3>
          <p>{{ item.description }}</p>
        </article>
      </section>
      <section class="guide-cta">
        <h2>Pon orden en tus alquileres con Alquivo.</h2>
        <p>
          Reúne inmuebles, contratos, cobros, gastos y documentos en un mismo
          espacio.
        </p>
        <RouterLink to="/register" class="button">Crear mi cuenta</RouterLink>
        <RouterLink to="/#planes" class="text-action"
          >Ver condiciones de acceso</RouterLink
        >
      </section>
    </main>
    <footer class="marketing-footer">
      <BrandLogo compact />
      <div>
        <RouterLink to="/">Alquivo</RouterLink
        ><RouterLink to="/guias">Guías</RouterLink
        ><RouterLink to="/privacy">Privacidad</RouterLink
        ><RouterLink to="/terms">Condiciones</RouterLink>
      </div>
    </footer>
  </div>
</template>
