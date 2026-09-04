# Nareo Frontend

Cliente Vue 3 de la nueva aplicación Nareo.

```bash
npm install
npm run dev
```

Comprobaciones:

```bash
npm run format:check
npm run build
```

La URL de la API se configura mediante `VITE_API_URL`. La autenticación utiliza la cookie HttpOnly de Sanctum y protección CSRF.
