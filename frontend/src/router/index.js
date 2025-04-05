import { createRouter, createWebHistory } from 'vue-router';
import SpotifyPlayer from '../components/SpotifyPlayer.vue';
import SpotifyLogin from '../components/SpotifyLogin.vue';

const routes = [
    { path: '/login', component: SpotifyLogin },
    {
        path: '/',
        component: SpotifyPlayer,
        beforeEnter: (to, from, next) => {
            const token = localStorage.getItem('spotify_token');
            if (!token) {
                next('/login'); // Redirect to login if not authenticated
            } else {
                next(); // Proceed to SpotifyPlayer if authenticated
            }
        },
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

export default router;