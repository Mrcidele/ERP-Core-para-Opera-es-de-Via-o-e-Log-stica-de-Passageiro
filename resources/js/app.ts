import { createPinia } from 'pinia';
import { createApp } from 'vue';
import App from './App.vue';
import { router } from './core/router';
import { installSession } from './core/session/installSession';
import { useAuthStore } from './modules/auth/stores/authStore';

const app = createApp(App).use(createPinia());

// A sessão é ligada antes do router para a primeira navegação já passar pela guarda.
installSession(router);

app.use(router).mount('#app');

// Confere com a API se o token salvo ainda vale e atualiza as permissões.
void useAuthStore().refresh();
